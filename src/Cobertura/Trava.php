<?php

declare(strict_types=1);

namespace KitRigor\Cobertura;

use function Symfony\Component\String\u;

/**
 * Confere um relatório clover contra a trava do projeto (tests/cobertura.php): a pasta de código
 * inteira fica no piso `total`, cada pasta no seu piso, e nenhum arquivo com código fica com 0% de
 * cobertura, fora os da lista `sem_teste`.
 *
 * As pastas são as de primeiro nível da fonte (src/Controller, src/Entity...); as de `$agrupar`
 * (por padrão Domain) se dividem por subpasta: Domain/Cliente, Domain/Nfcom...
 *
 * A cobertura diz que a linha rodou, não que algum teste conferiu o resultado: a trava pega o
 * código novo sem teste e a queda de cobertura numa pasta.
 */
final readonly class Trava
{
    /**
     * @param array{margem: int, total: int, padrao: int, pisos: array<string, int>, sem_teste: array<string, string>} $trava
     * @param string                                                                                                   $raiz    raiz do projeto: os caminhos de sem_teste são relativos a ela
     * @param string                                                                                                   $fonte   pasta do código, relativa à raiz
     * @param list<string>                                                                                             $agrupar
     */
    public function __construct(
        private array $trava,
        private string $raiz,
        private string $fonte = 'src',
        private array $agrupar = ['Domain'],
    ) {
    }

    /** @param string $clover conteúdo do relatório clover do PHPUnit */
    public function conferir(string $clover): Resultado
    {
        $xml = @simplexml_load_string($clover);
        if (false === $xml) {
            return new Resultado([], ['o relatório de cobertura não é um XML clover válido.'], []);
        }

        [$pastas, $zerados] = $this->medir($xml);

        $relatorio = [];
        $erros = [];
        $avisos = [];
        $linhasTotal = array_sum(array_column($pastas, 0));
        $percentualTotal = 0 === $linhasTotal ? 100.0 : 100 * array_sum(array_column($pastas, 1)) / $linhasTotal;
        $relatorio[] = \sprintf('Cobertura da fonte inteira: %.1f%% (piso %d%%)', $percentualTotal, $this->trava['total']);
        if ($percentualTotal < $this->trava['total']) {
            $erros[] = \sprintf('a fonte caiu para %.1f%%, abaixo do piso total de %d%%.', $percentualTotal, $this->trava['total']);
        }

        $relatorio[] = '';
        $relatorio[] = 'Cobertura de linhas por pasta (piso da trava):';
        foreach ($pastas as $pasta => [$linhas, $cobertas]) {
            $percentual = 100 * $cobertas / $linhas;
            $piso = $this->trava['pisos'][$pasta] ?? $this->trava['padrao'];
            $abaixo = $percentual < $piso;
            $relatorio[] = \sprintf('  %-22s %5.1f%%  piso %3d%%  (%d/%d)%s', $pasta, $percentual, $piso, $cobertas, $linhas, $abaixo ? '  << abaixo do piso' : '');
            if ($abaixo) {
                $erros[] = \sprintf('%s caiu para %.1f%%, abaixo do piso de %d%%.', $pasta, $percentual, $piso);
            } elseif ($this->sugerido($percentual) > $piso) {
                $avisos[] = \sprintf("o piso de '%s' pode subir de %d para %d.", $pasta, $piso, $this->sugerido($percentual));
            }
        }

        foreach ($zerados as $caminho => $linhas) {
            if (!isset($this->trava['sem_teste'][$caminho])) {
                $erros[] = \sprintf('%s não tem teste nenhum (0 de %d linhas).', $caminho, $linhas);
            }
        }
        foreach (array_keys($this->trava['sem_teste']) as $caminho) {
            if (!isset($zerados[$caminho])) {
                $avisos[] = \sprintf('%s já tem teste ou não existe mais: tire da lista sem_teste.', $caminho);
            }
        }

        return new Resultado($relatorio, $erros, $avisos);
    }

    /**
     * Linhas e linhas cobertas por pasta, e os arquivos com código sem nenhuma linha coberta.
     *
     * @return array{0: array<string, array{0: int, 1: int}>, 1: array<string, int>}
     */
    private function medir(\SimpleXMLElement $xml): array
    {
        $raiz = u($this->raiz)->trimEnd('/')->append('/');
        $pastas = [];
        $zerados = [];
        foreach ($xml->xpath('//file') ?: [] as $arquivo) {
            $nome = u((string) $arquivo['name']);
            $caminho = ($nome->startsWith($raiz->toString()) ? $nome->slice($raiz->length()) : $nome)->toString();
            $linhas = (int) $arquivo->metrics['statements'];
            $cobertas = (int) $arquivo->metrics['coveredstatements'];
            if (0 === $linhas) {
                continue;
            }

            $pasta = $this->pasta(u($caminho)->trimPrefix(u($this->fonte)->trimEnd('/')->append('/')->toString())->toString());
            $pastas[$pasta][0] = ($pastas[$pasta][0] ?? 0) + $linhas;
            $pastas[$pasta][1] = ($pastas[$pasta][1] ?? 0) + $cobertas;
            if (0 === $cobertas) {
                $zerados[$caminho] = $linhas;
            }
        }
        ksort($pastas);

        return [$pastas, $zerados];
    }

    /** Caminho dentro da fonte: Domain/Cliente/X.php → Domain/Cliente; Entity/X.php → Entity; Kernel.php → "." */
    private function pasta(string $caminho): string
    {
        $partes = explode('/', $caminho);

        return match (true) {
            1 === \count($partes) => '.',
            \in_array($partes[0], $this->agrupar, true) && \count($partes) > 2 => $partes[0].'/'.$partes[1],
            default => $partes[0],
        };
    }

    private function sugerido(float $percentual): int
    {
        return max(0, (int) floor($percentual) - $this->trava['margem']);
    }
}
