<?php

declare(strict_types=1);

namespace App\Tests\Arquitetura;

use KitRigor\Arquitetura\RegrasSymfony;

/**
 * Regras de arquitetura de src/, conferidas pelo phpat dentro do PHPStan (registradas no
 * phpstan.dist.neon). As regras gerais vêm do kit (KitRigor\Arquitetura\RegrasSymfony); aqui ficam
 * o mapa de módulos e as exceções do projeto, que são decisão do usuário.
 */
final class RegrasDeArquitetura extends RegrasSymfony
{
    /**
     * Módulos de src/Domain e os módulos de que cada um pode depender, além do Comum (que todos
     * usam e que não depende de nenhum). Um módulo novo entra aqui na mesma mudança que o cria.
     */
    protected function modulos(): array
    {
        return [
            // 'Comum' => [],
            // 'Cliente' => [],
            // 'Pedido' => ['Cliente'],
        ];
    }

    /** Controllers que ainda leem repositories direto, aceitos até serem refeitos. */
    protected function controllersComRepository(): array
    {
        return [];
    }
}
