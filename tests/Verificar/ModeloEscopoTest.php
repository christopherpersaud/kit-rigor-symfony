<?php

declare(strict_types=1);

namespace KitRigor\Tests\Verificar;

use KitRigor\Tests\Support\PastaTemporaria;
use PHPUnit\Framework\TestCase;

/** Executa o modelo copiado para um projeto, com ferramentas que registram a seleção recebida. */
final class ModeloEscopoTest extends TestCase
{
    use PastaTemporaria {
        setUp as criarPasta;
    }

    protected function setUp(): void
    {
        $this->criarPasta();
        $kit = \dirname(__DIR__, 2);
        $this->gravar('bin/verificar-escopo.php', (string) file_get_contents($kit.'/templates/referencia/escopo/bin/verificar-escopo.php'));
        $this->gravar('vendor/autoload.php', '<?php require '.var_export($kit.'/vendor/autoload.php', true).';');
        $this->gravar('composer.json', (string) file_get_contents($kit.'/templates/referencia/escopo/configuracao.json'));
        $this->gravar('src/Servico.php', '<?php');
        $this->gravar('tests/ServicoTest.php', '<?php');
        $this->gravar('tests/OutroTest.php', '<?php');
        $this->gravar('templates/tela.html.twig', '<div>Original</div>');
        $this->gravar('bin/console', '<?php echo "LINT:".$argv[2]."\n"; exit(str_contains(file_get_contents($argv[2]), "invalido") ? 9 : 0);');
        $this->gravar('vendor/bin/phpstan', '<?php echo "PHPSTAN:".implode("|", array_slice($argv, 1))."\n";');
        $this->gravar('vendor/bin/phpunit', '<?php echo "PHPUNIT:".implode("|", array_slice($argv, 1))."\n";');
    }

    public function testLeveExecutaLintSemPhpstanOuPhpunit(): void
    {
        $this->manifesto(['templates/tela.html.twig']);

        [$codigo, $saida] = $this->executar('leve');

        self::assertSame(0, $codigo);
        self::assertStringContainsString('LINT:templates/tela.html.twig', $saida);
        self::assertStringNotContainsString('PHPSTAN:', $saida);
        self::assertStringNotContainsString('PHPUNIT:', $saida);
    }

    public function testLevePropagaFalhaDoLint(): void
    {
        $this->manifesto(['templates/tela.html.twig']);
        $this->gravar('templates/tela.html.twig', 'invalido');

        self::assertSame(9, $this->executar('leve')[0]);
    }

    public function testLeveRecusaPhpAntesDeExecutarFerramentas(): void
    {
        $this->manifesto(['src/Servico.php']);

        [$codigo, $saida, $erro] = $this->executar('leve');

        self::assertSame(2, $codigo);
        self::assertStringContainsString('não contém somente layout', $erro);
        self::assertSame('', $saida);
    }

    public function testDirecionadoEnviaSomenteProducaoAoPhpstanETestesSelecionadosAoPhpunit(): void
    {
        $this->manifesto(['src/Servico.php', 'tests/ServicoTest.php', 'templates/tela.html.twig']);

        [$codigo, $saida] = $this->executar('check');

        self::assertSame(0, $codigo);
        $linhas = explode("\n", $saida);
        $phpstan = array_values(array_filter($linhas, static fn (string $linha): bool => str_starts_with($linha, 'PHPSTAN:')));
        self::assertCount(1, $phpstan);
        self::assertStringContainsString('src/Servico.php', $phpstan[0]);
        self::assertStringNotContainsString('Test.php', $phpstan[0]);
        self::assertStringContainsString('PHPUNIT:--no-coverage|tests/ServicoTest.php', $saida);
        self::assertStringNotContainsString('tests/OutroTest.php', $saida);
    }

    public function testSelecaoExplicitaNaoAceitaPastaOuOpcaoDeSuite(): void
    {
        $this->manifesto(['src/Servico.php']);

        self::assertSame(2, $this->executar('test', ['tests'])[0]);
        self::assertSame(2, $this->executar('test', ['--testsuite=Tudo'])[0]);
    }

    /** @param list<string> $arquivos */
    private function manifesto(array $arquivos): void
    {
        $this->gravar('rigor-escopo.json', json_encode([
            'arquivos' => $arquivos,
            'testes' => ['tests/ServicoTest.php'],
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<string> $argumentos
     *
     * @return array{int, string, string}
     */
    private function executar(string $modo, array $argumentos = []): array
    {
        $processo = proc_open(
            [\PHP_BINARY, 'bin/verificar-escopo.php', $modo, ...$argumentos],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->pasta,
        );
        self::assertIsResource($processo);
        $saida = stream_get_contents($pipes[1]);
        $erro = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertIsString($saida);
        self::assertIsString($erro);

        return [proc_close($processo), $saida, $erro];
    }
}
