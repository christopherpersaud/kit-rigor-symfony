<?php

declare(strict_types=1);

namespace KitRigor\Tests\Arquitetura;

use KitRigor\Arquitetura\RegrasSymfony;
use PHPat\Test\Builder\Rule;
use PHPUnit\Framework\TestCase;

/**
 * O mapa de módulos vira uma regra por módulo que tem algo proibido. Que o phpat aplica as regras
 * de verdade se prova no projeto, pelo PHPStan; aqui se confere o mapa.
 */
final class RegrasSymfonyTest extends TestCase
{
    public function testUmaRegraPorModuloComDependenciaProibida(): void
    {
        $regras = new class extends RegrasSymfony {
            protected function modulos(): array
            {
                return [
                    'Comum' => [],
                    'Cliente' => [],
                    'Pedido' => ['Cliente'],
                    'Fiscal' => ['Cliente', 'Pedido'],
                ];
            }
        };

        $porModulo = iterator_to_array($regras->testModuloSoDependeDosModulosPermitidos());

        self::assertSame(['Comum', 'Cliente', 'Pedido'], array_keys($porModulo), 'Fiscal pode depender de todos: não gera regra.');
        self::assertContainsOnlyInstancesOf(Rule::class, $porModulo);
    }

    public function testSemModulosNaoHaRegraDeModulo(): void
    {
        $regras = new class extends RegrasSymfony {
            protected function modulos(): array
            {
                return [];
            }
        };

        self::assertSame([], iterator_to_array($regras->testModuloSoDependeDosModulosPermitidos()));
        self::assertInstanceOf(Rule::class, $regras->testControllerNaoUsaRepository());
    }
}
