<?php

declare(strict_types=1);

namespace KitRigor\Tests;

use KitRigor\Config;
use KitRigor\Tests\Support\PastaTemporaria;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    use PastaTemporaria;

    public function testSemExtraRigorValeOPadraoDoSymfony(): void
    {
        $this->gravar('composer.json', '{"name": "x/y"}');

        self::assertEquals(new Config($this->pasta), Config::daPasta($this->pasta));
    }

    public function testExtraRigorTrocaSoAsChavesInformadas(): void
    {
        $this->gravar('composer.json', json_encode(['extra' => ['rigor' => [
            'specs' => 'docs/specs',
            'testes-js' => [],
            'verificar' => ['comando' => 'make check', 'ignorar' => ['*.txt', 3]],
            'cobertura' => ['fonte' => 'lib', 'agrupar' => ['Modulo']],
        ]]], \JSON_THROW_ON_ERROR));

        $config = Config::daPasta($this->pasta);

        self::assertSame('docs/specs', $config->specs);
        self::assertSame([], $config->testesJs);
        self::assertSame(['tests'], $config->testesPhp);
        self::assertSame('make check', $config->comandoVerificar);
        self::assertSame(['*.txt'], $config->ignorados);
        self::assertSame(['.'], $config->caminhosVerificados);
        self::assertSame('lib', $config->fonteCobertura);
        self::assertSame(['Modulo'], $config->agrupar);
        self::assertSame('tests/cobertura.php', $config->travaCobertura);
        self::assertSame($this->pasta.'/lib', $config->caminho('lib'));
    }

    public function testSemComposerJsonValeOPadrao(): void
    {
        self::assertEquals(new Config($this->pasta), Config::daPasta($this->pasta));
    }
}
