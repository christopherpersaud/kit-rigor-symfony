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
 *
 * Verificação leve (`verificar.leve`, só no hook de fim de turno): se tudo o que mudou desde o
 * último verde completo é de layout (`leve.arquivos`), roda o comando leve no lugar do completo.
 * O verde leve não conta como completo: a próxima mudança fora da lista, o pre-push e o CI ainda
 * passam a suíte inteira por cima do que o leve deixou passar.
 *
 * Estado e log em var/check/: `verde.json` (o sha1 de cada arquivo no último verde completo),
 * `verde-leve` (a impressão digital do último verde leve) e `ultimo.log`.
 */
final readonly class Verificador
{
    public const string PASTA_ESTADO = 'var/check';
    public const string LOG = 'var/check/ultimo.log';

    public function __construct(
        private Config $config,
    ) {
    }

    /**
     * @param bool $permitirLeve se tudo o que mudou desde o último verde completo casa com
     *                           `verificar.leve.arquivos`, roda o comando leve no lugar do completo
     */
    public function verificar(bool $mesmoSemMudanca = false, bool $permitirLeve = false): Verificacao
    {
        $estado = $this->config->caminho(self::PASTA_ESTADO);
        if (!is_dir($estado)) {
            mkdir($estado, 0o777, true);
        }

        $conteudo = $this->conteudo();
        $atual = self::impressao($conteudo);
        $verde = $this->ultimoVerde();
        if (!$mesmoSemMudanca && null !== $verde && $atual === self::impressao($verde)) {
            return new Verificacao(Verificacao::SEM_MUDANCA);
        }

        if (!$mesmoSemMudanca && $permitirLeve && null !== $this->config->comandoLeve && null !== $verde && $this->soMudouLeve($verde, $conteudo)) {
            if ($atual === @file_get_contents($estado.'/verde-leve')) {
                return new Verificacao(Verificacao::SEM_MUDANCA, comando: $this->config->comandoLeve, leve: true);
            }
            $verificacao = $this->rodar($this->config->comandoLeve, true);
            $verificacao->passou() ? file_put_contents($estado.'/verde-leve', $atual) : @unlink($estado.'/verde-leve');

            return $verificacao;
        }

        $verificacao = $this->rodar($this->config->comandoVerificar, false);
        @unlink($estado.'/verde');
        if ($verificacao->passou()) {
            file_put_contents($estado.'/verde.json', json_encode($conteudo, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
        } else {
            @unlink($estado.'/verde.json');
        }

        return $verificacao;
    }

    /** sha1 dos caminhos e do conteúdo dos arquivos verificados, em ordem de bytes. */
    public function impressaoDigital(): string
    {
        return self::impressao($this->conteudo());
    }

    /** @return array<string, string> o sha1 de cada arquivo verificado */
    public function conteudo(): array
    {
        $conteudo = [];
        foreach ($this->arquivos() as $arquivo) {
            $conteudo[$arquivo] = (string) sha1_file($this->config->caminho($arquivo));
        }

        return $conteudo;
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
            if ($arquivo !== $this->config->escopoVerificacao && !u($arquivo)->startsWith($specs->toString()) && $this->ignorado($arquivo)) {
                continue;
            }
            $arquivos[] = $arquivo;
        }
        $arquivos = array_values(array_unique($arquivos));
        sort($arquivos, \SORT_STRING);

        return $arquivos;
    }

    /** @param array<string, string> $conteudo */
    private static function impressao(array $conteudo): string
    {
        $hash = hash_init('sha1');
        foreach ($conteudo as $arquivo => $sha1) {
            hash_update($hash, $arquivo."\0".$sha1."\n");
        }

        return hash_final($hash);
    }

    /** @return array<string, string>|null o conteúdo da última verificação completa verde */
    private function ultimoVerde(): ?array
    {
        $json = json_decode((string) @file_get_contents($this->config->caminho(self::PASTA_ESTADO.'/verde.json')), true);
        if (!\is_array($json)) {
            return null;
        }

        $verde = [];
        foreach ($json as $arquivo => $sha1) {
            if (\is_string($sha1)) {
                $verde[(string) $arquivo] = $sha1;
            }
        }

        return $verde;
    }

    /**
     * @param array<string, string> $verde
     * @param array<string, string> $atual
     */
    private function soMudouLeve(array $verde, array $atual): bool
    {
        if (null !== $this->config->escopoVerificacao) {
            try {
                if (!(new Escopo($this->config))->somenteLayout()) {
                    return false;
                }
            } catch (\InvalidArgumentException|\JsonException|\RuntimeException) {
                // O comando direcionado deve informar o erro do manifesto, nunca aprovar um leve.
                return false;
            }
        }
        $mudados = array_keys(array_diff_assoc($verde, $atual) + array_diff_assoc($atual, $verde));
        foreach ($mudados as $arquivo) {
            if ($arquivo === $this->config->escopoVerificacao) {
                continue;
            }
            if (null !== $this->config->escopoVerificacao && str_ends_with(strtolower((string) $arquivo), '.php')) {
                return false;
            }
            if (!$this->config->arquivoLeve((string) $arquivo)) {
                return false;
            }
        }

        return [] !== $mudados;
    }

    private function rodar(string $comando, bool $leve): Verificacao
    {
        $inicio = microtime(true);
        $log = fopen($this->config->caminho(self::LOG), 'w');
        if (false === $log) {
            return new Verificacao(Verificacao::FALHOU, 'não deu para gravar '.self::LOG.'.', self::LOG, comando: $comando, leve: $leve);
        }
        $processo = proc_open($comando, [0 => ['file', '/dev/null', 'r'], 1 => $log, 2 => $log], $pipes, $this->config->raiz);
        $codigo = false === $processo ? 127 : proc_close($processo);
        fclose($log);
        $segundos = (int) round(microtime(true) - $inicio);

        if (0 === $codigo) {
            return new Verificacao(Verificacao::VERDE, segundos: $segundos, comando: $comando, leve: $leve);
        }

        return new Verificacao(Verificacao::FALHOU, $this->fimDoLog(), self::LOG, $segundos, $comando, $leve);
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
