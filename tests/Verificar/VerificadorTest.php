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
        self::assertFileDoesNotExist($this->pasta.'/var/check/verde.json');
        self::assertSame(Verificacao::FALHOU, $verificador->verificar()->situacao, 'Depois de uma falha, roda de novo mesmo sem mudança.');
    }

    public function testOFimDoLogTemNoMaximoSessentaLinhas(): void
    {
        $verificacao = $this->verificador('seq 1 100; exit 1')->verificar();

        $linhas = explode("\n", $verificacao->trecho);
        self::assertCount(60, $linhas);
        self::assertSame(['41', '100'], [$linhas[0], $linhas[59]]);
    }

    public function testSoLayoutMudouRodaOComandoLeveUmaVezSo(): void
    {
        $this->gravar('templates/a.html.twig', 'a');
        $verificador = $this->verificadorComLeve();
        $verificador->verificar(permitirLeve: true);

        $this->gravar('templates/a.html.twig', 'mudou');
        $leve = $verificador->verificar(permitirLeve: true);
        $deNovo = $verificador->verificar(permitirLeve: true);

        self::assertSame([Verificacao::VERDE, true, 'echo leve >> var/rodou.log'], [$leve->situacao, $leve->leve, $leve->comando]);
        self::assertSame([Verificacao::SEM_MUDANCA, true], [$deNovo->situacao, $deNovo->leve]);
        self::assertSame("completo\nleve\n", $this->ler('var/rodou.log'));
    }

    public function testLayoutNovoOuApagadoTambemEhLeve(): void
    {
        $this->gravar('templates/a.html.twig', 'a');
        $verificador = $this->verificadorComLeve();
        $verificador->verificar(permitirLeve: true);

        unlink($this->pasta.'/templates/a.html.twig');
        $this->gravar('templates/b.html.twig', 'b');

        self::assertTrue($verificador->verificar(permitirLeve: true)->leve);
    }

    public function testLayoutJuntoComOutroArquivoRodaOCompleto(): void
    {
        $verificador = $this->verificadorComLeve();
        $verificador->verificar(permitirLeve: true);

        $this->gravar('templates/a.html.twig', 'a');
        $this->gravar('src/A.php', '<?php // mudou');
        $verificacao = $verificador->verificar(permitirLeve: true);

        self::assertSame([Verificacao::VERDE, false, 'echo completo >> var/rodou.log'], [$verificacao->situacao, $verificacao->leve, $verificacao->comando]);
    }

    public function testOVerdeLeveNaoContaComoCompleto(): void
    {
        $verificador = $this->verificadorComLeve();
        $verificador->verificar(permitirLeve: true);
        $this->gravar('templates/a.html.twig', 'a');
        $verificador->verificar(permitirLeve: true);

        $completo = $verificador->verificar();
        $leveDepoisDoCompleto = $verificador->verificar(permitirLeve: true);

        self::assertSame([Verificacao::VERDE, false], [$completo->situacao, $completo->leve], 'Sem permitirLeve (pre-push), o layout que só passou no leve roda o completo.');
        self::assertSame(Verificacao::SEM_MUDANCA, $leveDepoisDoCompleto->situacao);
        self::assertSame("completo\nleve\ncompleto\n", $this->ler('var/rodou.log'));
    }

    public function testSemUmVerdeCompletoAntesNaoHaLeve(): void
    {
        $this->gravar('templates/a.html.twig', 'a');

        self::assertFalse($this->verificadorComLeve()->verificar(permitirLeve: true)->leve);
    }

    public function testArquivoTiradoDaListaComExclamacaoRodaOCompleto(): void
    {
        $verificador = $this->verificadorComLeve();
        $verificador->verificar(permitirLeve: true);

        $this->gravar('templates/pdf/nota.html.twig', 'a');

        self::assertFalse($verificador->verificar(permitirLeve: true)->leve);
    }

    public function testFalhaNoLeveRodaDeNovoSemMudancaEMantemOVerdeCompleto(): void
    {
        $this->verificadorComLeve()->verificar(permitirLeve: true);
        $verificador = new Verificador(new Config($this->pasta, comandoVerificar: 'true', comandoLeve: 'echo "lint:twig falhou"; exit 1', arquivosLeves: ['templates/*']));
        $this->gravar('templates/a.html.twig', 'a');

        $primeira = $verificador->verificar(permitirLeve: true);
        $segunda = $verificador->verificar(permitirLeve: true);

        self::assertSame([Verificacao::FALHOU, true, 'lint:twig falhou'], [$primeira->situacao, $primeira->leve, $primeira->trecho]);
        self::assertSame(Verificacao::FALHOU, $segunda->situacao);
        self::assertFileExists($this->pasta.'/var/check/verde.json');
    }

    public function testSemComandoLeveConfiguradoRodaOCompleto(): void
    {
        $verificador = $this->verificador('true');
        $verificador->verificar(permitirLeve: true);

        $this->gravar('templates/a.html.twig', 'a');

        self::assertFalse($verificador->verificar(permitirLeve: true)->leve);
    }

    private function verificadorComLeve(): Verificador
    {
        return new Verificador(new Config(
            $this->pasta,
            comandoVerificar: 'echo completo >> var/rodou.log',
            comandoLeve: 'echo leve >> var/rodou.log',
            arquivosLeves: ['templates/*', '!templates/pdf/*'],
        ));
    }

    public function testLayoutEManifestoAtualizadosUsamOLeveSemClassificarJsonComoLayout(): void
    {
        $this->gravar('templates/a.html.twig', 'a');
        $this->gravar('tests/ATest.php', '<?php');
        $this->gravar('selecao.json', '{"arquivos":["src/A.php"],"testes":["tests/ATest.php"]}');
        $verificador = $this->verificadorComManifesto();
        $verificador->verificar();

        $this->gravar('templates/a.html.twig', 'div alterada');
        $this->gravar('selecao.json', '{"arquivos":["templates/a.html.twig"],"testes":["tests/ATest.php"]}');

        self::assertTrue($verificador->verificar(permitirLeve: true)->leve);
        self::assertSame(Verificacao::SEM_MUDANCA, $verificador->verificar(permitirLeve: true)->situacao);
        $this->gravar('src/A.php', '<?php // novo');
        self::assertFalse($verificador->verificar(permitirLeve: true)->leve);
        self::assertSame("completo\nleve\ncompleto\n", $this->ler('var/rodou.log'));
    }

    public function testManifestoInvalidoExigeComandoDirecionado(): void
    {
        $this->gravar('tests/ATest.php', '<?php');
        $this->gravar('selecao.json', '{"arquivos":[],"testes":["tests/ATest.php"]}');
        $verificador = $this->verificadorComManifesto();
        $verificador->verificar();
        $this->gravar('selecao.json', '{ quebrado');

        self::assertFalse($verificador->verificar(permitirLeve: true)->leve);
        self::assertSame("completo\ncompleto\n", $this->ler('var/rodou.log'));
    }

    public function testManifestoComPhpNaoPermiteLeveMesmoQuandoSoATelaMudou(): void
    {
        $this->gravar('templates/a.html.twig', 'a');
        $this->gravar('tests/ATest.php', '<?php');
        $this->gravar('selecao.json', '{"arquivos":["src/A.php"],"testes":["tests/ATest.php"]}');
        $verificador = $this->verificadorComManifesto();
        $verificador->verificar();
        $this->gravar('templates/a.html.twig', 'mudou');

        self::assertFalse($verificador->verificar(permitirLeve: true)->leve);
    }

    public function testManifestoContinuaContandoMesmoSeJsonEstiverIgnorado(): void
    {
        $this->gravar('selecao.json', '{}');
        $config = new Config($this->pasta, ignorados: ['*.json'], escopoVerificacao: 'selecao.json');

        self::assertContains('selecao.json', new Verificador($config)->arquivos());
    }

    private function verificadorComManifesto(): Verificador
    {
        return new Verificador(new Config(
            $this->pasta,
            comandoVerificar: 'echo completo >> var/rodou.log',
            comandoLeve: 'echo leve >> var/rodou.log',
            arquivosLeves: ['templates/*'],
            escopoVerificacao: 'selecao.json',
        ));
    }

    private function verificador(string $comando): Verificador
    {
        return new Verificador(new Config($this->pasta, comandoVerificar: $comando));
    }
}
