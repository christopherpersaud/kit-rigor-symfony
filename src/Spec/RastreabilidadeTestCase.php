<?php

declare(strict_types=1);

namespace KitRigor\Spec;

use KitRigor\Config;
use PHPUnit\Framework\TestCase;

use function Symfony\Component\String\u;

/**
 * Liga as regras dos docs de módulo (`docs/modulos/*.md`, `### CLI-04 Título`) aos testes que as
 * provam (`#[Group('CLI-04')]` no PHP, o ID no nome do describe/test no JS). Falha quando uma regra
 * ativa fica sem teste, quando um teste cita uma regra que não existe ou foi revogada, e quando o
 * catálogo fica inconsistente. Convenção no bloco do kit no AGENTS.md, seção "Specs".
 *
 * O projeto tem uma classe vazia que estende esta:
 *
 *     final class RastreabilidadeTest extends RastreabilidadeTestCase
 *     {
 *     }
 *
 * As pastas vêm do `extra.rigor` do composer.json (ver Config); para mudar só aqui, sobrescreva
 * config() ou foraDaVarredura().
 */
abstract class RastreabilidadeTestCase extends TestCase
{
    public function testCadaDocComSecaoRegrasDeclaraRegras(): void
    {
        $vazios = [];
        foreach (self::docs() as $arquivo) {
            if (LeitorDeSpecs::temSecaoRegras(self::ler($arquivo)) && [] === LeitorDeSpecs::regras($arquivo, self::ler($arquivo))) {
                $vazios[] = $arquivo;
            }
        }

        self::assertSame([], $vazios, 'Doc com a seção "## Regras" e nenhuma regra lida: o formato do título (### ABC-01 Título) mudou ou o leitor quebrou.');
    }

    public function testCadaIdEhUnicoESegueOPrefixoDoDoc(): void
    {
        $problemas = [];
        $vistos = [];
        $prefixos = [];

        foreach (static::regras() as $regra) {
            if (isset($vistos[$regra->id])) {
                $problemas[] = \sprintf('%s repetido em %s (já em %s)', $regra->id, $regra->onde(), $vistos[$regra->id]);
            }
            $vistos[$regra->id] = $regra->onde();

            $prefixo = $prefixos[$regra->arquivo] ??= $regra->prefixo();
            if ($prefixo !== $regra->prefixo()) {
                $problemas[] = \sprintf('%s em %s: o doc usa o prefixo %s', $regra->id, $regra->onde(), $prefixo);
            }
        }

        self::assertSame([], $problemas);
    }

    public function testDocComRegrasTemASecaoRegras(): void
    {
        $semSecao = [];
        foreach (array_unique(array_map(static fn (Regra $r) => $r->arquivo, static::regras())) as $arquivo) {
            if (!LeitorDeSpecs::temSecaoRegras(self::ler($arquivo))) {
                $semSecao[] = $arquivo;
            }
        }

        self::assertSame([], $semSecao, 'Doc que declara regras precisa da seção "## Regras".');
    }

    public function testTodaRegraAtivaTemAoMenosUmCenario(): void
    {
        $semCenario = array_map(
            static fn (Regra $r) => \sprintf('%s %s (%s)', $r->id, $r->titulo, $r->onde()),
            array_values(array_filter(static::regras(), static fn (Regra $r) => Regra::ATIVA === $r->situacao && 0 === $r->cenarios)),
        );

        self::assertSame([], $semCenario, 'Regra ativa sem cenário "- **Dado** ..., **quando** ..., **então** ...".');
    }

    public function testTodaRegraAtivaTemTeste(): void
    {
        $citadas = array_flip(array_map(static fn (Citacao $c) => $c->id, static::citacoes()));
        $semTeste = array_map(
            static fn (Regra $r) => \sprintf('%s %s (%s)', $r->id, $r->titulo, $r->onde()),
            array_values(array_filter(static::regras(), static fn (Regra $r) => Regra::ATIVA === $r->situacao && !isset($citadas[$r->id]))),
        );

        self::assertSame([], $semTeste, "Regra ativa sem nenhum teste. Marque o teste que a prova com #[Group('ID')], ou o ID no describe/test do JS.");
    }

    public function testNenhumTesteCitaRegraInexistenteOuRevogada(): void
    {
        $situacao = [];
        foreach (static::regras() as $regra) {
            $situacao[$regra->id] = $regra->situacao;
        }

        $invalidas = [];
        foreach (static::citacoes() as $citacao) {
            $s = $situacao[$citacao->id] ?? null;
            if (null === $s || Regra::REVOGADA === $s) {
                $invalidas[] = \sprintf('%s em %s: %s', $citacao->id, $citacao->onde(), null === $s ? 'regra inexistente' : 'regra revogada');
            }
        }

        self::assertSame([], $invalidas);
    }

    protected static function config(): Config
    {
        return Config::doProjeto();
    }

    /** @return list<string> prefixos de caminho (relativos à raiz) cujos arquivos citam IDs como exemplo, não como prova */
    protected static function foraDaVarredura(): array
    {
        return [];
    }

    /** @return list<Regra> */
    protected static function regras(): array
    {
        $regras = [];
        foreach (self::docs() as $arquivo) {
            array_push($regras, ...LeitorDeSpecs::regras($arquivo, self::ler($arquivo)));
        }

        return $regras;
    }

    /** @return list<Citacao> */
    protected static function citacoes(): array
    {
        $config = static::config();
        $citacoes = [];
        foreach ($config->testesPhp as $pasta) {
            foreach (self::arquivos($pasta, '.php') as $arquivo) {
                array_push($citacoes, ...LeitorDeSpecs::citacoesPhp($arquivo, self::ler($arquivo)));
            }
        }
        foreach ($config->testesJs as $pasta) {
            foreach (self::arquivos($pasta, '.test.js') as $arquivo) {
                array_push($citacoes, ...LeitorDeSpecs::citacoesJs($arquivo, self::ler($arquivo)));
            }
        }

        return $citacoes;
    }

    /** @return list<string> os docs de módulo, relativos à raiz, em ordem */
    private static function docs(): array
    {
        $config = static::config();
        $docs = array_map(
            static fn (string $caminho) => self::relativo($caminho),
            glob($config->caminho($config->specs).'/*.md') ?: [],
        );
        sort($docs);

        return $docs;
    }

    /** @return list<string> caminhos relativos à raiz, fora das pastas de exemplo */
    private static function arquivos(string $pasta, string $sufixo): array
    {
        $raiz = static::config()->caminho($pasta);
        if (!is_dir($raiz)) {
            return [];
        }

        $arquivos = [];
        $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterador as $info) {
            if (!$info instanceof \SplFileInfo) {
                continue;
            }
            $arquivo = self::relativo($info->getPathname());
            if (!str_ends_with($arquivo, $sufixo)) {
                continue;
            }
            foreach (static::foraDaVarredura() as $fora) {
                if (str_starts_with($arquivo, $fora)) {
                    continue 2;
                }
            }
            $arquivos[] = $arquivo;
        }
        sort($arquivos);

        return $arquivos;
    }

    private static function relativo(string $caminho): string
    {
        $raiz = (string) realpath(static::config()->raiz);

        return u((string) realpath($caminho))->slice(u($raiz)->length())->trimStart('/')->toString();
    }

    private static function ler(string $arquivo): string
    {
        return (string) file_get_contents(static::config()->caminho($arquivo));
    }
}
