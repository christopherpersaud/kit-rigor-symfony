<?php

declare(strict_types=1);

namespace KitRigor\Tests\Spec;

use KitRigor\Spec\Citacao;
use KitRigor\Spec\LeitorDeSpecs;
use KitRigor\Spec\Regra;
use PHPUnit\Framework\TestCase;

/** O leitor que o RastreabilidadeTestCase usa: se ele errar, a rastreabilidade passa sem conferir nada. */
final class LeitorDeSpecsTest extends TestCase
{
    public function testLeRegrasSituacaoECenarios(): void
    {
        $doc = <<<'MD'
            # Módulo

            ## Regras

            ### ABC-01 Primeira regra

            Texto.

            - **Dado** algo, **quando** faz, **então** acontece.
            - **Dado** outra coisa, **quando** faz, **então** outra.
            - Um item que não é cenário.

            ### ABC-02 Regra antiga (revogada)

            ```markdown
            ### ABC-99 Exemplo dentro de código não conta
            ```

            ## Outra seção

            - **Dado** fora de regra, **quando** x, **então** não conta.

            ## O que ainda não está pronto

            ### ABC-03 Paginação (pendente)
            MD;

        $regras = LeitorDeSpecs::regras('docs/x.md', $doc);

        self::assertSame(['ABC-01', 'ABC-02', 'ABC-03'], array_map(static fn (Regra $r) => $r->id, $regras));
        self::assertSame([Regra::ATIVA, Regra::REVOGADA, Regra::PENDENTE], array_map(static fn (Regra $r) => $r->situacao, $regras));
        self::assertSame([2, 0, 0], array_map(static fn (Regra $r) => $r->cenarios, $regras));
        self::assertSame(['Primeira regra', 'Regra antiga', 'Paginação'], array_map(static fn (Regra $r) => $r->titulo, $regras));
        self::assertSame('docs/x.md:5', $regras[0]->onde());
        self::assertSame('ABC', $regras[0]->prefixo());
    }

    /** O ✅ termina no byte 0x85, que é o NEL do \R quando a expressão não é Unicode. */
    public function testLeDocComCaractereQueTerminaNoByte85(): void
    {
        $doc = "# Módulo\n\n- ✅ Registro marcado como excluído\n\n## Regras\n\n### ABC-01 Regra\n\n- **Dado** algo, **quando** faz, **então** acontece.\n";

        self::assertTrue(LeitorDeSpecs::temSecaoRegras($doc));
        $regras = LeitorDeSpecs::regras('docs/x.md', $doc);
        self::assertSame(['ABC-01'], array_map(static fn (Regra $r) => $r->id, $regras));
        self::assertSame('docs/x.md:7', $regras[0]->onde());
    }

    public function testSecaoRegrasSoContaForaDeBlocoDeCodigo(): void
    {
        self::assertTrue(LeitorDeSpecs::temSecaoRegras("# X\n\n## Regras\n"));
        self::assertFalse(LeitorDeSpecs::temSecaoRegras("# X\n\n```markdown\n## Regras\n```\n"));
        self::assertFalse(LeitorDeSpecs::temSecaoRegras("# X\n\n### Regras\n"));
    }

    public function testCitacoesDoPhpPeloAtributoGroup(): void
    {
        $php = <<<'PHP'
            <?php
            #[Group('ABC-01')]
            #[\PHPUnit\Framework\Attributes\Group("ABC-02")]
            #[Group('integracao')]
            public function testX(): void {}
            PHP;

        self::assertSame(
            ['ABC-01 tests/X.php:2', 'ABC-02 tests/X.php:3'],
            array_map(static fn (Citacao $c) => $c->id.' '.$c->onde(), LeitorDeSpecs::citacoesPhp('tests/X.php', $php)),
        );
    }

    public function testCitacoesDoJsPeloNomeDoDescribeOuTest(): void
    {
        $js = <<<'JS'
            describe('ABC-01 busca', () => {
                test(`ABC-02 destaque`, () => {});
                it('sem id', () => {});
                // ABC-03 só em comentário não conta
            });
            JS;

        self::assertSame(['ABC-01', 'ABC-02'], array_map(static fn (Citacao $c) => $c->id, LeitorDeSpecs::citacoesJs('a.test.js', $js)));
    }
}
