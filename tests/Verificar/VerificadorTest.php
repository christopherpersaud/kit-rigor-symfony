<?php

declare(strict_types=1);

namespace KitRigor\Tests\Verificar;

use KitRigor\Config;
use KitRigor\Tests\Support\PastaTemporaria;
use KitRigor\Verificar\Verificacao;
use KitRigor\Verificar\Verificador;
use PHPUnit\Framework\TestCase;

final class VerificadorTest extends TestCase
{
    use PastaTemporaria {
        setUp as criarPasta;
    }

    protected function setUp(): void
    {
        $this->criarPasta();
        $this->iniciarGit();
        $this->gravar('.gitignore', "/var/\n/ignorado.php\n");
        $this->gravar('src/A.php', '<?php // a');
    }

    public function testRodaNaPrimeiraVezENaoRodaDeNovoSemMudanca(): void
    {
        $verificador = $this->verificador('echo rodou >> var/contador.log');

        self::assertSame(Verificacao::VERDE, $verificador->verificar()->situacao);
        self::assertSame(Verificacao::SEM_MUDANCA, $verificador->verificar()->situacao);
        self::assertSame("rodou\n", $this->ler('var/contador.log'));
    }

    public function testMudancaNumArquivoVerificadoRodaDeNovo(): void
    {
        $verificador = $this->verificador('true');
        $verificador->verificar();

        $this->gravar('src/A.php', '<?php // mudou');

        self::assertSame(Verificacao::VERDE, $verificador->verificar()->situacao);
    }

    public function testComTudoRodaMesmoSemMudanca(): void
    {
        $verificador = $this->verificador('true');
        $verificador->verificar();

        self::assertSame(Verificacao::VERDE, $verificador->verificar(mesmoSemMudanca: true)->situacao);
    }

    public function testMarkdownEIgnoradosDoGitNaoContamMasOsDocsDeSpecSim(): void
    {
        $this->gravar('README.md', 'a');
        $this->gravar('ignorado.php', 'a');
        $this->gravar('docs/modulos/modulo-a.md', 'a');

        self::assertSame(['.gitignore', 'docs/modulos/modulo-a.md', 'src/A.php'], $this->verificador('true')->arquivos());
    }

    public function testCaminhosEIgnoradosVemDaConfiguracao(): void
    {
        $this->gravar('src/B.php', 'b');
        $this->gravar('outro/C.php', 'c');
        $config = new Config($this->pasta, comandoVerificar: 'true', caminhosVerificados: ['src'], ignorados: ['src/B.php']);

        self::assertSame(['src/A.php'], new Verificador($config)->arquivos());
    }

    public function testArquivoApagadoMudaAImpressaoDigital(): void
    {
        $this->gravar('src/B.php', 'b');
        $verificador = $this->verificador('true');
        $antes = $verificador->impressaoDigital();

        unlink($this->pasta.'/src/B.php');

        self::assertNotSame($antes, $verificador->impressaoDigital());
    }

    public function testFalhaDevolveOFimDoLogSemCoresEApagaOVerde(): void
    {
        $this->verificador('true')->verificar();
        $verificador = $this->verificador('printf "\033[31mErro em X\033[0m\n\n  detalhe\n"; exit 3');
        $this->gravar('src/A.php', '<?php // mudou');

        $verificacao = $verificador->verificar();

        self::assertSame(Verificacao::FALHOU, $verificacao->situacao);
        self::assertFalse($verificacao->passou());
        self::assertSame("Erro em X\n  detalhe", $verificacao->trecho);
        self::assertSame('var/check/ultimo.log', $verificacao->log);
        self::assertFileDoesNotExist($this->pasta.'/var/check/verde');
        self::assertSame(Verificacao::FALHOU, $verificador->verificar()->situacao, 'Depois de uma falha, roda de novo mesmo sem mudança.');
    }

    public function testOFimDoLogTemNoMaximoSessentaLinhas(): void
    {
        $verificacao = $this->verificador('seq 1 100; exit 1')->verificar();

        $linhas = explode("\n", $verificacao->trecho);
        self::assertCount(60, $linhas);
        self::assertSame(['41', '100'], [$linhas[0], $linhas[59]]);
    }

    private function verificador(string $comando): Verificador
    {
        return new Verificador(new Config($this->pasta, comandoVerificar: $comando));
    }
}
