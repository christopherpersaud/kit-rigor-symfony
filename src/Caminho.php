<?php

declare(strict_types=1);

namespace KitRigor;

/** Caminhos de configuração e relatórios usam / também no Windows. */
final class Caminho
{
    public static function normalizar(string $caminho): string
    {
        return str_replace('\\', '/', $caminho);
    }
}
