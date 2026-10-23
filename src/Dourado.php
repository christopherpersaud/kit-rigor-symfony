<?php

declare(strict_types=1);

namespace KitRigor;

use PHPUnit\Framework\Assert;

/**
 * Testes dourados (golden files): a saída inteira é comparada com um arquivo aprovado na pasta de
 * dourados (`tests/Dourado/`, ou `extra.rigor.dourados` do composer.json). Qualquer mudança na
 * saída, prevista ou não, aparece como diff.
 *
 * Quando a mudança é intencional (regra nova ou alterada na spec), regenere com
 *   ATUALIZAR_DOURADOS=1 composer test -- --filter <teste>
 * e revise o `git diff` da pasta: ele é a prova do que mudou na saída. Sem a variável, um arquivo
 * que falta ou difere reprova o teste; nunca regenere só para fazer o teste passar.
 *
 * O que muda a cada execução (datas, assinaturas, bytes de imagem) sai antes da comparação; o resto
 * do cenário tem de ser determinístico (datas, números e códigos fixos).
 */
final class Dourado
{
    private static ?string $pasta = null;

    /** Troca a pasta dos dourados (caminho absoluto), por exemplo no bootstrap dos testes. */
    public static function usarPasta(?string $pasta): void
    {
        self::$pasta = $pasta;
    }

    /** Compara o texto como está. */
    public static function texto(string $arquivo, string $atual): void
    {
        self::comparar($arquivo, $atual);
    }

    /** JSON com indentação e chaves na ordem em que vieram, para o diff ser legível. */
    public static function json(string $nome, mixed $dados): void
    {
        self::comparar($nome.'.json', json_encode($dados, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * XML com indentação, sem as assinaturas digitais (dependem do certificado de teste; confira a
     * assinatura num teste próprio) e com o texto dos elementos de `$ignorar` trocado por um marcador.
     *
     * @param list<string> $ignorar nomes locais de elementos cujo texto muda a cada execução (ex.: dhEvento)
     */
    public static function xml(string $nome, \DOMDocument|string $xml, array $ignorar = [], bool $semAssinatura = true): void
    {
        $copia = new \DOMDocument();
        $copia->preserveWhiteSpace = false;
        $copia->formatOutput = true;
        $copia->loadXML($xml instanceof \DOMDocument ? (string) $xml->saveXML() : $xml);

        $xpath = new \DOMXPath($copia);
        if ($semAssinatura) {
            foreach ($xpath->query('//*[local-name()="Signature"]') ?: [] as $assinatura) {
                if ($assinatura instanceof \DOMElement) {
                    $assinatura->parentNode?->removeChild($assinatura);
                }
            }
        }
        foreach ($ignorar as $elemento) {
            foreach ($xpath->query(\sprintf('//*[local-name()="%s"]', $elemento)) ?: [] as $no) {
                if ($no instanceof \DOMElement) {
                    $no->textContent = '(ignorado no teste dourado)';
                }
            }
        }

        self::comparar($nome.'.xml', (string) $copia->saveXML());
    }

    /**
     * HTML com as imagens embutidas (`data:image/...;base64,...`) trocadas por um marcador: os bytes
     * dependem das versões do GD e da libpng de cada máquina. Confira o conteúdo da imagem (o texto
     * de um QR code, por exemplo) em outro dourado.
     *
     * @param array<string, string> $trocas outras expressões regulares a trocar, padrão => substituto
     */
    public static function html(string $nome, string $html, array $trocas = []): void
    {
        $trocas = ['#data:image/([a-z+]+);base64,[A-Za-z0-9+/=]+#' => 'data:image/$1;base64,(imagem)'] + $trocas;
        foreach ($trocas as $padrao => $substituto) {
            $html = (string) preg_replace($padrao, $substituto, $html);
        }

        self::comparar($nome.'.html', $html);
    }

    private static function comparar(string $arquivo, string $atual): void
    {
        $pasta = self::$pasta ?? Config::doProjeto()->caminho(Config::doProjeto()->dourados);
        $caminho = $pasta.'/'.$arquivo;

        if ('1' === getenv('ATUALIZAR_DOURADOS')) {
            if (!is_dir(\dirname($caminho))) {
                mkdir(\dirname($caminho), 0o775, true);
            }
            file_put_contents($caminho, $atual);
            Assert::assertFileExists($caminho);

            return;
        }

        Assert::assertFileExists($caminho, \sprintf('Falta o arquivo dourado %s. Gere com ATUALIZAR_DOURADOS=1 e revise o git diff.', $caminho));
        Assert::assertSame(
            (string) file_get_contents($caminho),
            $atual,
            \sprintf('A saída mudou em relação a %s. Se a mudança for intencional e estiver na spec, regenere com ATUALIZAR_DOURADOS=1 e revise o git diff.', $caminho),
        );
    }
}
