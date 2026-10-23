<?php

declare(strict_types=1);

namespace KitRigor\Spec;

use function Symfony\Component\String\u;

/**
 * Lê as regras dos docs de módulo e as citações dos testes, para o RastreabilidadeTestCase conferir
 * que uma coisa bate com a outra. A convenção está no bloco do kit no AGENTS.md, seção "Specs".
 */
final class LeitorDeSpecs
{
    /** Prefixo de três letras, traço e dois dígitos: CLI-04. */
    public const string ID = '[A-Z]{3}-\d{2}';

    /** @return list<Regra> */
    public static function regras(string $arquivo, string $conteudo): array
    {
        $regras = [];
        $atual = null;
        $cenarios = 0;

        foreach (self::linhasForaDeCodigo($conteudo) as $i => $linha) {
            if (str_starts_with($linha, '#')) {
                if (null !== $atual) {
                    $regras[] = new Regra($atual['id'], $atual['titulo'], $atual['situacao'], $cenarios, $arquivo, $atual['linha']);
                    $atual = null;
                }
                if (preg_match('/^### ('.self::ID.') (.+?)(?: \((pendente|revogada)\))?\s*$/u', $linha, $m)) {
                    $atual = ['id' => $m[1], 'titulo' => $m[2], 'situacao' => $m[3] ?? Regra::ATIVA, 'linha' => $i + 1];
                    $cenarios = 0;
                }

                continue;
            }

            if (null !== $atual && preg_match('/^\s*- \*\*Dado\*\* .+\*\*então\*\*/u', $linha)) {
                ++$cenarios;
            }
        }

        if (null !== $atual) {
            $regras[] = new Regra($atual['id'], $atual['titulo'], $atual['situacao'], $cenarios, $arquivo, $atual['linha']);
        }

        return $regras;
    }

    /** O doc tem a seção `## Regras` fora dos blocos de código. */
    public static function temSecaoRegras(string $conteudo): bool
    {
        foreach (self::linhasForaDeCodigo($conteudo) as $linha) {
            if (preg_match('/^## Regras\s*$/', $linha)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Citacao> `#[Group('CLI-04')]`, com ou sem o namespace completo do atributo */
    public static function citacoesPhp(string $arquivo, string $conteudo): array
    {
        return self::citacoes($arquivo, $conteudo, '/#\[\s*(?:\\\\?PHPUnit\\\\Framework\\\\Attributes\\\\)?Group\(\s*[\'"]('.self::ID.')[\'"]\s*\)\s*\]/');
    }

    /** @return list<Citacao> `describe('CLI-04 ...')`, `test(...)` ou `it(...)` começando pelo ID */
    public static function citacoesJs(string $arquivo, string $conteudo): array
    {
        return self::citacoes($arquivo, $conteudo, '/\b(?:describe|test|it)\(\s*[\'"`]('.self::ID.')\b/');
    }

    /** @return array<int, string> as linhas do Markdown fora dos blocos de código, pelo índice original */
    private static function linhasForaDeCodigo(string $conteudo): array
    {
        $linhas = [];
        $dentroDeCodigo = false;
        foreach (preg_split('/\R/u', $conteudo) ?: [] as $i => $linha) {
            if (u($linha)->trimStart()->startsWith('```')) {
                $dentroDeCodigo = !$dentroDeCodigo;

                continue;
            }
            if (!$dentroDeCodigo) {
                $linhas[$i] = $linha;
            }
        }

        return $linhas;
    }

    /** @return list<Citacao> */
    private static function citacoes(string $arquivo, string $conteudo, string $padrao): array
    {
        preg_match_all($padrao, $conteudo, $encontrados, \PREG_OFFSET_CAPTURE);

        return array_map(
            static fn (array $m) => new Citacao($m[0], $arquivo, substr_count($conteudo, "\n", 0, $m[1]) + 1),
            $encontrados[1],
        );
    }
}
