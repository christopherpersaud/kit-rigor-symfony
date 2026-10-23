<?php

declare(strict_types=1);

namespace KitRigor\Hook;

use KitRigor\Config;
use KitRigor\Verificar\Verificador;

/**
 * Hook de fim de turno (evento `Stop`) do Claude Code e do Codex, que usam o mesmo contrato: o JSON
 * da sessão chega pela entrada padrão, e um `{"decision": "block", "reason": ...}` na saída faz o
 * agente continuar o turno com o `reason` como instrução.
 *
 * - Nada mudou desde o último verde: sai em silêncio (perguntas e respostas não custam nada).
 * - Falhou: bloqueia com o fim da saída; o log inteiro fica em var/check/.
 * - Depois de MAX_BLOQUEIOS falhas seguidas na mesma sessão, deixa encerrar com um aviso ao
 *   usuário, para um ambiente quebrado não prender o agente num loop.
 * - VERIFICAR_DESLIGADO=1 no ambiente desliga o hook (decisão só do usuário).
 */
final readonly class Stop
{
    public const int MAX_BLOQUEIOS = 3;

    public function __construct(
        private Config $config,
        private Verificador $verificador,
    ) {
    }

    /** @return string|null o JSON a escrever na saída padrão, ou null para deixar o turno terminar */
    public function executar(string $entrada): ?string
    {
        if ('1' === getenv('VERIFICAR_DESLIGADO') || !file_exists($this->config->caminho('.git'))) {
            return null;
        }

        $sessao = json_decode($entrada, true);
        $id = \is_array($sessao) && \is_string($sessao['session_id'] ?? null) ? $sessao['session_id'] : 'sem-sessao';
        $contador = $this->config->caminho(Verificador::PASTA_ESTADO.'/bloqueios-'.preg_replace('/[^A-Za-z0-9_-]/', '_', $id));

        $verificacao = $this->verificador->verificar();
        if ($verificacao->passou()) {
            @unlink($contador);

            return null;
        }

        $bloqueios = (int) @file_get_contents($contador) + 1;
        if ($bloqueios > self::MAX_BLOQUEIOS) {
            @unlink($contador);

            return self::json(['systemMessage' => \sprintf(
                'A verificação continua falhando depois de %d tentativas de correção. O turno terminou com a verificação vermelha; veja %s.',
                self::MAX_BLOQUEIOS,
                $verificacao->log,
            )]);
        }
        file_put_contents($contador, (string) $bloqueios);

        return self::json(['decision' => 'block', 'reason' => \sprintf(
            "A verificação (%s) falhou (tentativa %d de %d). Corrija a causa e não encerre o turno com a verificação vermelha; não desligue a checagem nem a contorne. Log completo em %s. Fim da saída:\n\n%s",
            $this->config->comandoVerificar,
            $bloqueios,
            self::MAX_BLOQUEIOS,
            $verificacao->log,
            $verificacao->trecho,
        )]);
    }

    /** @param array<string, string> $dados */
    private static function json(array $dados): string
    {
        return json_encode($dados, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
