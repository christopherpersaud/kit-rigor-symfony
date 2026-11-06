<?php

declare(strict_types=1);

namespace KitRigor\Tests;

use KitRigor\Tests\Support\PastaTemporaria;
use PHPUnit\Framework\TestCase;

final class EmpacotamentoTest extends TestCase
{
    use PastaTemporaria;

    public function testZipContemSkillsEReferenciasSemBibliotecaOuDependencias(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('A extensão zip é necessária para conferir a distribuição.');
        }
        $arquivo = $this->pasta.'/agensi.zip';
        $processo = proc_open([\PHP_BINARY, \dirname(__DIR__).'/bin/rigor-empacotar', $arquivo], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($processo);
        $saida = stream_get_contents($pipes[1]);
        $erro = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($processo), (string) $saida.(string) $erro);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($arquivo));
        self::assertNotFalse($zip->getFromName('README.md'));
        self::assertNotFalse($zip->getFromName('LICENSE'));
        foreach (['rigor-instalar', 'rigor-spec', 'rigor-mutacao'] as $skill) {
            $texto = $zip->getFromName('skills/'.$skill.'/SKILL.md');
            self::assertIsString($texto);
            self::assertStringStartsWith("---\nname: ".$skill."\n", $texto);
        }
        self::assertNotFalse($zip->getFromName('skills/rigor-instalar/references/instalacao.md'));
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $nome = $zip->getNameIndex($i);
            self::assertIsString($nome);
            self::assertTrue('LICENSE' === $nome || str_ends_with($nome, '.md'), $nome);
            self::assertFalse(str_contains($nome, '..') || str_contains($nome, 'vendor/') || str_contains($nome, '.git/'), $nome);
        }
        $zip->close();
    }
}
