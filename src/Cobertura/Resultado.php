<?php

declare(strict_types=1);

namespace KitRigor\Cobertura;

/** O que a trava de cobertura achou: o relatório por pasta, o que reprova e o que só avisa. */
final readonly class Resultado
{
    /**
     * @param list<string> $relatorio
     * @param list<string> $erros
     * @param list<string> $avisos
     */
    public function __construct(
        public array $relatorio,
        public array $erros,
        public array $avisos,
    ) {
    }

    public function passou(): bool
    {
        return [] === $this->erros;
    }
}
