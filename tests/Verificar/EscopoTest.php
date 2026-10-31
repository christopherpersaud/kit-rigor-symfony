<?php

declare(strict_types=1);

namespace KitRigor\Tests\Verificar;

use KitRigor\Config;
use KitRigor\Tests\Support\PastaTemporaria;
use KitRigor\Verificar\Escopo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EscopoTest extends TestCase
{
    use PastaTemporaria {
        setUp as criarPasta;
    }

    protected function setUp(): void
    {
        $this->criarPasta();
        $this->gravar('src/Servico.php', '<?php');
        $this->gravar('templates/tela.html.twig', '<div>Original</div>');
        $this->gravar('templates/_email/mensagem.html.twig', 'mensagem');
        $this->gravar('templates/relatorio_pdf.html.twig', 'documento');
        $this->gravar('tests/ServicoTest.php', '<?php');
        $this->gravarManifesto(['templates/tela.html.twig']);
    }

    public function testCarregaArquivosETestesSemDuplicarEConsideraManifestoMetadado(): void
    {
        $this->gravarManifesto(['templates/tela.html.twig', 'templates/tela.html.twig', 'selecao.json']);

        $escopo = new Escopo($this->config());

        self::assertSame(['templates/tela.html.twig', 'selecao.json'], $escopo->arquivos);
        self::assertSame(['tests/ServicoTest.php'], $escopo->testes);
        self::assertTrue($escopo->somenteLayout());
    }

    /** @return iterable<string, array{string}> */
    public static function arquivosNaoLeves(): iterable
    {
        yield 'PHP' => ['src/Servico.php'];
        yield 'e-mail' => ['templates/_email/mensagem.html.twig'];
        yield 'PDF' => ['templates/relatorio_pdf.html.twig'];
    }

    #[DataProvider('arquivosNaoLeves')]
    public function testCodigoEDocumentosNaoSaoLayout(string $arquivo): void
    {
        $this->gravarManifesto([$arquivo]);

        self::assertFalse(new Escopo($this->config())->somenteLayout());
    }

    public function testPhpNaoViraLayoutMesmoComPadraoAbrangente(): void
    {
        $this->gravarManifesto(['src/Servico.php']);
        $config = new Config($this->pasta, arquivosLeves: ['*'], escopoVerificacao: 'selecao.json');

        self::assertFalse(new Escopo($config)->somenteLayout());
    }

    /** @return iterable<string, array{mixed}> */
    public static function selecoesInvalidas(): iterable
    {
        yield 'pasta' => [['tests']];
        yield 'opção' => [['--testsuite=Tudo']];
        yield 'travessia' => [['tests/../src/Servico.php']];
        yield 'inexistente' => [['src/Inexistente.php']];
        yield 'valor não textual' => [[123]];
        yield 'objeto no lugar da lista' => [['arquivo' => 'src/Servico.php']];
        yield 'nulo' => [null];
    }

    #[DataProvider('selecoesInvalidas')]
    public function testRecusaSelecaoQueNaoSejaDeArquivosIndividuais(mixed $caminhos): void
    {
        $escopo = new Escopo($this->config());
        $this->expectException(\InvalidArgumentException::class);

        $escopo->validarArquivos($caminhos);
    }

    public function testRecusaLinkParaForaDoProjeto(): void
    {
        $fora = tempnam(sys_get_temp_dir(), 'rigor-fora-');
        self::assertIsString($fora);
        symlink($fora, $this->pasta.'/externo.php');

        try {
            $this->expectException(\InvalidArgumentException::class);
            new Escopo($this->config())->validarArquivos(['externo.php']);
        } finally {
            unlink($fora);
        }
    }

    public function testRecusaManifestoForaDoProjeto(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Escopo(new Config($this->pasta, escopoVerificacao: '../selecao.json'));
    }

    public function testJsonInvalidoNaoViraSelecaoVazia(): void
    {
        $this->gravar('selecao.json', '{ inválido');
        $this->expectException(\JsonException::class);

        new Escopo($this->config());
    }

    public function testNaoPermiteArquivosDeProducaoNaSelecaoDeTestes(): void
    {
        $escopo = new Escopo($this->config());
        $this->expectException(\InvalidArgumentException::class);

        $escopo->validarTestes(['src/Servico.php']);
    }

    public function testExigeAoMenosUmTeste(): void
    {
        $escopo = new Escopo($this->config());
        $this->expectException(\InvalidArgumentException::class);

        $escopo->validarTestes([]);
    }

    public function testRespeitaPastasDeTesteConfiguradas(): void
    {
        $this->gravar('provas/ServicoTest.php', '<?php');
        $this->gravar('selecao.json', '{"arquivos":[],"testes":["provas/ServicoTest.php"]}');

        $escopo = new Escopo(new Config($this->pasta, testesPhp: ['provas'], escopoVerificacao: 'selecao.json'));

        self::assertSame(['provas/ServicoTest.php'], $escopo->testes);
    }

    /** @param list<string> $arquivos */
    private function gravarManifesto(array $arquivos): void
    {
        $this->gravar('selecao.json', json_encode([
            'arquivos' => $arquivos,
            'testes' => ['tests/ServicoTest.php'],
        ], \JSON_THROW_ON_ERROR));
    }

    private function config(): Config
    {
        return new Config(
            $this->pasta,
            arquivosLeves: ['templates/*.twig', '!templates/_email/*', '!templates/*pdf*'],
            escopoVerificacao: 'selecao.json',
        );
    }
}
