<?php

declare(strict_types=1);

namespace KitRigor\Spec;

/**
 * Uma regra de negócio declarada num doc de módulo (`### CLI-04 Título`), com a situação lida do
 * sufixo do título e quantos cenários "Dado/quando/então" ela tem.
 */
final readonly class Regra
{
    public const string ATIVA = 'ativa';
    public const string PENDENTE = 'pendente';
    public const string REVOGADA = 'revogada';

    public function __construct(
        public string $id,
        public string $titulo,
        public string $situacao,
        public int $cenarios,
        public string $arquivo,
        public int $linha,
    ) {
    }

    public function prefixo(): string
    {
        return explode('-', $this->id)[0];
    }

    public function onde(): string
    {
        return $this->arquivo.':'.$this->linha;
    }
}
