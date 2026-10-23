<?php

declare(strict_types=1);

namespace KitRigor\Tests\Spec;

use KitRigor\Tests\Support\RastreabilidadeDoFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Cada verificação da rastreabilidade num projeto em ordem (passa) e num projeto com um problema
 * de cada tipo (reprova apontando exatamente o problema).
 */
final class RastreabilidadeTestCaseTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../Fixtures';

    /** @return iterable<string, array{non-empty-string}> */
    public static function verificacoes(): iterable
    {
        foreach (['testCadaDocComSecaoRegrasDeclaraRegras', 'testCadaIdEhUnicoESegueOPrefixoDoDoc', 'testDocComRegrasTemASecaoRegras', 'testTodaRegraAtivaTemAoMenosUmCenario', 'testTodaRegraAtivaTemTeste', 'testNenhumTesteCitaRegraInexistenteOuRevogada'] as $metodo) {
            yield $metodo => [$metodo];
        }
    }

    /** @param non-empty-string $metodo */
    #[DataProvider('verificacoes')]
    public function testProjetoEmOrdemPassaEmTodas(string $metodo): void
    {
        self::assertNull(self::problemas('projeto-ok', $metodo));
    }

    public function testDocComSecaoRegrasSemNenhumaRegraLida(): void
    {
        self::assertSame(['docs/modulos/modulo-vazio.md'], self::problemas('projeto-com-problemas', 'testCadaDocComSecaoRegrasDeclaraRegras'));
    }

    public function testIdRepetidoEPrefixoForaDoDoc(): void
    {
        self::assertSame([
            'SRV-03 em docs/modulos/modulo-cliente.md:13: o doc usa o prefixo CLI',
            'CLI-02 repetido em docs/modulos/modulo-servico.md:7 (já em docs/modulos/modulo-cliente.md:9)',
            'CLI-02 em docs/modulos/modulo-servico.md:7: o doc usa o prefixo SRV',
        ], self::problemas('projeto-com-problemas', 'testCadaIdEhUnicoESegueOPrefixoDoDoc'));
    }

    public function testDocComRegrasSemASecaoRegras(): void
    {
        self::assertSame(['docs/modulos/modulo-servico.md'], self::problemas('projeto-com-problemas', 'testDocComRegrasTemASecaoRegras'));
    }

    public function testRegraAtivaSemCenario(): void
    {
        self::assertSame(['CLI-01 Sem cenário (docs/modulos/modulo-cliente.md:5)'], self::problemas('projeto-com-problemas', 'testTodaRegraAtivaTemAoMenosUmCenario'));
    }

    public function testRegraAtivaSemTeste(): void
    {
        self::assertSame([
            'CLI-02 Sem teste (docs/modulos/modulo-cliente.md:9)',
            'CLI-02 Repetida em outro doc (docs/modulos/modulo-servico.md:7)',
        ], self::problemas('projeto-com-problemas', 'testTodaRegraAtivaTemTeste'));
    }

    public function testTesteCitandoRegraInexistenteOuRevogada(): void
    {
        self::assertSame([
            'CLI-99 em tests/Provas.php:3: regra inexistente',
            'CLI-05 em tests/Provas.php:4: regra revogada',
        ], self::problemas('projeto-com-problemas', 'testNenhumTesteCitaRegraInexistenteOuRevogada'));
    }

    /**
     * @param non-empty-string $metodo
     *
     * @return list<mixed>|null o que a verificação apontou, ou null se passou
     */
    private static function problemas(string $projeto, string $metodo): ?array
    {
        RastreabilidadeDoFixture::$pasta = (string) realpath(self::FIXTURES.'/'.$projeto);
        $caso = new RastreabilidadeDoFixture($metodo);

        try {
            $caso->{$metodo}();
        } catch (ExpectationFailedException $falha) {
            $atual = $falha->getComparisonFailure()?->getActual();
            self::assertIsArray($atual, $falha->getMessage());

            return array_values($atual);
        }

        return null;
    }
}
