<?php

declare(strict_types=1);

namespace KitRigor\Spec;

/** Um teste que diz provar uma regra: `#[Group('CLI-04')]` no PHP ou o ID no nome do describe/test no JS. */
final readonly class Citacao
{
    public function __construct(
        public string $id,
        public string $arquivo,
        public int $linha,
    ) {
    }

    public function onde(): string
    {
        return $this->arquivo.':'.$this->linha;
    }
}
