<?php

declare(strict_types=1);

namespace KitRigor\Verificar;

use KitRigor\Config;
use Symfony\Component\String\UnicodeString;

use function Symfony\Component\String\u;

/**
 * Roda o comando de verificação do projeto (`composer check:rapido`) se algum arquivo verificado
 * mudou desde a última verificação verde. Usado pelo hook de fim de turno do agente e pelo pre-push
 * do git; também serve à mão (`vendor/bin/rigor-verificar`).
 *
 * A impressão digital é o conteúdo dos arquivos na pasta de trabalho (rastreados e novos, fora os
 * ignorados pelo git e os de `ignorar`), não o HEAD: o que ficou verde antes de virar commit
 * continua verde. Os docs de spec contam sempre, mesmo sendo Markdown: a rastreabilidade os lê.
 * Estado e log em var/check/.
 */
final readonly class Verificador
{
    public const string PASTA_ESTADO = 'var/check';
    public const string LOG = 'var/check/ultimo.log';

    public function __construct(
        private Config $config,
    ) {
    }

    public function verificar(bool $mesmoSemMudanca = false): Verificacao
    {
        $estado = $this->config->caminho(self::PASTA_ESTADO);
        if (!is_dir($estado)) {
            mkdir($estado, 0o777, true);
        }

        $atual = $this->impressaoDigital();
        if (!$mesmoSemMudanca && $atual === @file_get_contents($estado.'/verde')) {
            return new Verificacao(Verificacao::SEM_MUDANCA);
        }

        $inicio = microtime(true);
        $log = fopen($this->config->caminho(self::LOG), 'w');
        if (false === $log) {
            return new Verificacao(Verificacao::FALHOU, 'não deu para gravar '.self::LOG.'.', self::LOG);
        }
        $processo = proc_open($this->config->comandoVerificar, [0 => ['file', '/dev/null', 'r'], 1 => $log, 2 => $log], $pipes, $this->config->raiz);
        $codigo = false === $processo ? 127 : proc_close($processo);
        fclose($log);
        $segundos = (int) round(microtime(true) - $inicio);

        if (0 === $codigo) {
            file_put_contents($estado.'/verde', $atual);

            return new Verificacao(Verificacao::VERDE, segundos: $segundos);
        }

        @unlink($estado.'/verde');

        return new Verificacao(Verificacao::FALHOU, $this->fimDoLog(), self::LOG, $segundos);
    }

    /** sha1 dos caminhos e do conteúdo dos arquivos verificados, em ordem de bytes. */
    public function impressaoDigital(): string
    {
        $hash = hash_init('sha1');
        foreach ($this->arquivos() as $arquivo) {
            hash_update($hash, $arquivo."\0".sha1_file($this->config->caminho($arquivo))."\n");
        }

        return hash_final($hash);
    }

    /** @return list<string> */
    public function arquivos(): array
    {
        $comando = array_merge(['git', 'ls-files', '-c', '-o', '--exclude-standard', '--deduplicate', '-z', '--'], $this->config->caminhosVerificados);
        $git = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $this->config->raiz);
        if (false === $git) {
            return [];
        }
        $saida = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($git);

        $specs = u($this->config->specs)->trimEnd('/')->append('/');
        $arquivos = [];
        foreach (explode("\0", $saida) as $arquivo) {
            if ('' === $arquivo || !is_file($this->config->caminho($arquivo))) {
                continue;
            }
            if (!u($arquivo)->startsWith($specs->toString()) && $this->ignorado($arquivo)) {
                continue;
            }
            $arquivos[] = $arquivo;
        }
        $arquivos = array_values(array_unique($arquivos));
        sort($arquivos, \SORT_STRING);

        return $arquivos;
    }

    private function ignorado(string $arquivo): bool
    {
        foreach ($this->config->ignorados as $padrao) {
            if (fnmatch($padrao, $arquivo)) {
                return true;
            }
        }

        return false;
    }

    /** As últimas 60 linhas não vazias do log, sem as cores do terminal. */
    private function fimDoLog(): string
    {
        $linhas = [];
        foreach (file($this->config->caminho(self::LOG)) ?: [] as $linha) {
            $limpa = u($linha)->replaceMatches('/\x1b\[[0-9;]*m/', '')->trimEnd();
            if (!$limpa->trim()->isEmpty()) {
                $linhas[] = $limpa;
            }
        }

        return implode("\n", array_map(static fn (UnicodeString $l) => $l->toString(), \array_slice($linhas, -60)));
    }
}
