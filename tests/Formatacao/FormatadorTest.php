<?php

declare(strict_types=1);

namespace KitRigor\Tests\Formatacao;

use KitRigor\Tests\Support\PastaTemporaria;
use PHPUnit\Framework\TestCase;

final class FormatadorTest extends TestCase
{
    use PastaTemporaria;

    public function testLocalFormataComOsScriptsDoProjeto(): void
    {
        $this->projeto();

        self::assertSame(0, $this->executar(false));
        self::assertSame("<?php // formatado\n", $this->ler('src/A.php'));
    }

    public function testCiRecusaArquivoNaoFormatadoSemAlteraLo(): void
    {
        $this->projeto();

        self::assertNotSame(0, $this->executar(true));
        self::assertSame('<?php // original', $this->ler('src/A.php'));
    }

    public function testCiAceitaArquivoJaFormatado(): void
    {
        $this->projeto();
        $this->gravar('src/A.php', "<?php // formatado\n");

        self::assertSame(0, $this->executar(true));
        self::assertSame("<?php // formatado\n", $this->ler('src/A.php'));
    }

    public function testSemFormatadorNadaMudaLocalOuNoCi(): void
    {
        $this->gravar('composer.json', '{"scripts":{"test":"phpunit"}}');
        $this->gravar('src/A.php', '<?php // original');

        self::assertSame(0, $this->executar(false));
        self::assertSame(0, $this->executar(true));
        self::assertSame('<?php // original', $this->ler('src/A.php'));
    }

    public function testCiSemScriptDeConferenciaFalhaSemFormatar(): void
    {
        $this->projeto(verificar: false);

        self::assertSame(1, $this->executar(true));
        self::assertSame('<?php // original', $this->ler('src/A.php'));
        self::assertSame(0, $this->executar(false));
    }

    public function testErroDoFormatadorNaoEhIgnorado(): void
    {
        $this->gravar('composer.json', '{"scripts":{"formatar":"@php falha.php"}}');
        $this->gravar('falha.php', '<?php exit(7);');

        self::assertNotSame(0, $this->executar(false));
    }

    private function projeto(bool $verificar = true): void
    {
        $scripts = ['formatar' => '@php formatar.php'];
        if ($verificar) {
            $scripts['formatar:verificar'] = '@php verificar.php';
        }
        $this->gravar('composer.json', json_encode(['scripts' => $scripts], \JSON_THROW_ON_ERROR));
        $this->gravar('src/A.php', '<?php // original');
        $this->gravar('formatar.php', '<?php file_put_contents("src/A.php", "<?php // formatado\n");');
        $this->gravar('verificar.php', '<?php exit(file_get_contents("src/A.php") === "<?php // formatado\n" ? 0 : 3);');
    }

    private function executar(bool $ci): int
    {
        // Processo separado: STDOUT/STDERR são os descritores reais usados pelo comando.
        $autoload = var_export(\dirname(__DIR__, 2).'/vendor/autoload.php', true);
        $raiz = var_export($this->pasta, true);
        $this->gravar('executar.php', '<?php require '.$autoload.'; exit(new \\KitRigor\\Formatacao\\Formatador('.$raiz.')->executar('.($ci ? 'true' : 'false').'));');
        $processo = proc_open([\PHP_BINARY, $this->pasta.'/executar.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->pasta);
        self::assertIsResource($processo);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($processo);
    }
}
