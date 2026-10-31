<?php

declare(strict_types=1);

namespace KitRigor\Verificar;

use KitRigor\Config;

/** Manifesto opcional: arquivos individuais e testes afetados, selecionados pelo projeto. */
final readonly class Escopo
{
    /** @var list<string> */
    public array $arquivos;

    /** @var non-empty-list<string> */
    public array $testes;

    public function __construct(private Config $config)
    {
        $manifesto = $config->escopoVerificacao;
        if (null === $manifesto || '' === $manifesto) {
            throw new \InvalidArgumentException('Configure extra.rigor.verificar.escopo para usar um manifesto.');
        }
        $this->validarArquivos([$manifesto]);
        $json = file_get_contents($config->caminho($manifesto));
        if (false === $json) {
            throw new \RuntimeException('Não foi possível ler o manifesto de verificação.');
        }
        $dados = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($dados)) {
            throw new \InvalidArgumentException('O manifesto deve conter as listas arquivos e testes.');
        }
        $this->arquivos = $this->validarArquivos($dados['arquivos'] ?? null);
        $this->testes = $this->validarTestes($dados['testes'] ?? null);
    }

    /** @return list<string> */
    public function validarArquivos(mixed $caminhos): array
    {
        return $this->validarCaminhos($caminhos, false);
    }

    /** @return non-empty-list<string> */
    public function validarTestes(mixed $caminhos): array
    {
        $validos = $this->validarCaminhos($caminhos, true);
        if ([] === $validos) {
            throw new \InvalidArgumentException('Selecione ao menos um arquivo de teste.');
        }

        return $validos;
    }

    /** @param list<string>|null $arquivos */
    public function somenteLayout(?array $arquivos = null): bool
    {
        foreach ($arquivos ?? $this->arquivos as $arquivo) {
            if ($arquivo === $this->config->escopoVerificacao) {
                continue;
            }
            if (str_ends_with(strtolower($arquivo), '.php') || !$this->config->arquivoLeve($arquivo)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function validarCaminhos(mixed $caminhos, bool $testes): array
    {
        if (!\is_array($caminhos) || !array_is_list($caminhos)) {
            throw new \InvalidArgumentException('O escopo precisa de listas de arquivos individuais.');
        }
        $raiz = realpath($this->config->raiz);
        if (false === $raiz) {
            throw new \InvalidArgumentException('A raiz do projeto não existe.');
        }
        $validos = [];
        foreach ($caminhos as $caminho) {
            if (!\is_string($caminho) || '' === $caminho || str_starts_with($caminho, '-') || str_contains($caminho, '..')) {
                throw new \InvalidArgumentException('Informe caminhos relativos de arquivos individuais.');
            }
            $real = realpath($raiz.'/'.$caminho);
            if (false === $real || !is_file($real) || !str_starts_with($real, $raiz.\DIRECTORY_SEPARATOR)) {
                throw new \InvalidArgumentException('Arquivo inválido no escopo: '.$caminho);
            }
            if ($testes && (!str_ends_with($caminho, 'Test.php') || !$this->estaNosTestes($caminho))) {
                throw new \InvalidArgumentException('Selecione arquivos de teste individuais nas pastas de testes do projeto.');
            }
            $validos[] = $caminho;
        }

        return array_values(array_unique($validos));
    }

    private function estaNosTestes(string $caminho): bool
    {
        foreach ($this->config->testesPhp as $pasta) {
            if (str_starts_with($caminho, rtrim($pasta, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
