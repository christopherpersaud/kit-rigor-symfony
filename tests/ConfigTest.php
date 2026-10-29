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

    public function testVerificacaoLeveVemDeVerificarLeve(): void
    {
        $this->gravar('composer.json', json_encode(['extra' => ['rigor' => [
            'verificar' => ['leve' => ['comando' => 'composer check:leve', 'arquivos' => ['templates/*', '!templates/pdf/*']]],
        ]]], \JSON_THROW_ON_ERROR));

        $config = Config::daPasta($this->pasta);

        self::assertSame('composer check:leve', $config->comandoLeve);
        self::assertSame(['templates/*', '!templates/pdf/*'], $config->arquivosLeves);
        self::assertSame('composer check:rapido', $config->comandoVerificar);
    }

    public function testArquivoLeveCasaNaOrdemEAExclamacaoTira(): void
    {
        $config = new Config($this->pasta, arquivosLeves: ['templates/*', '!templates/pdf/*', 'templates/pdf/capa.twig']);

        self::assertTrue($config->arquivoLeve('templates/nota/lista.html.twig'));
        self::assertFalse($config->arquivoLeve('templates/pdf/nota.html.twig'));
        self::assertTrue($config->arquivoLeve('templates/pdf/capa.twig'));
        self::assertFalse($config->arquivoLeve('src/A.php'));
        self::assertFalse(new Config($this->pasta)->arquivoLeve('templates/a.twig'), 'Sem a lista, nada é leve.');
    }

    public function testSemComposerJsonValeOPadrao(): void
    {
        self::assertEquals(new Config($this->pasta), Config::daPasta($this->pasta));
    }
}
