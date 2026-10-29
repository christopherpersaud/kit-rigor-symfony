<?php

declare(strict_types=1);

namespace KitRigor\Verificar;

/**
 * Resultado de uma verificação: nada mudou desde o último verde, passou ou falhou; `comando` é o
 * que rodou e `leve` diz se foi o comando leve, no lugar do completo.
 */
final readonly class Verificacao
{
    public const string SEM_MUDANCA = 'sem-mudanca';
    public const string VERDE = 'verde';
    public const string FALHOU = 'falhou';

    public function __construct(
        public string $situacao,
        public string $trecho = '',
        public ?string $log = null,
        public int $segundos = 0,
        public string $comando = '',
        public bool $leve = false,
    ) {
    }

    public function passou(): bool
    {
        return self::FALHOU !== $this->situacao;
    }
}
