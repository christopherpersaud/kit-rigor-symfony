<?php

declare(strict_types=1);

namespace KitRigor\Instalador;

/** O que o instalador fez (ou faria, simulando) com um arquivo do projeto. */
final readonly class Acao
{
    public const string CRIADO = 'criado';
    public const string ATUALIZADO = 'atualizado';
    public const string IGUAL = 'igual';
    public const string DIFERE = 'difere';
    public const string FALTA = 'falta';

    public function __construct(
        public string $tipo,
        public string $caminho,
        public string $detalhe = '',
    ) {
    }

    /** Pede atenção de quem instala: um arquivo do projeto que o kit não mexe sozinho. */
    public function pedeRevisao(): bool
    {
        return \in_array($this->tipo, [self::DIFERE, self::FALTA], true);
    }

    public function __toString(): string
    {
        return \sprintf('%-10s %s%s', $this->tipo, $this->caminho, '' === $this->detalhe ? '' : ': '.$this->detalhe);
    }
}
