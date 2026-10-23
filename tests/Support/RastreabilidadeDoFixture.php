<?php

declare(strict_types=1);

namespace KitRigor\Tests\Support;

use KitRigor\Config;
use KitRigor\Spec\RastreabilidadeTestCase;

/** A rastreabilidade apontada para um projeto de exemplo de tests/Fixtures. */
final class RastreabilidadeDoFixture extends RastreabilidadeTestCase
{
    public static string $pasta = '';

    protected static function config(): Config
    {
        return Config::daPasta(self::$pasta);
    }
}
