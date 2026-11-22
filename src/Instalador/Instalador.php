<?php

declare(strict_types=1);

namespace KitRigor\Instalador;

use Composer\InstalledVersions;
use KitRigor\Caminho;

use function Symfony\Component\String\u;

/**
 * Instala ou atualiza o kit num projeto, sem nunca sobrescrever o que é do projeto:
 *
 * - modelos (templates/projeto/): copiados só quando o arquivo não existe; um que já existe e difere
 *   do modelo é só relatado, para comparar à mão;
 * - bloco do kit no AGENTS.md: trocado inteiro entre os marcadores (o resto do arquivo fica);
 * - CLAUDE.md: criado com `@AGENTS.md` quando falta (o Codex lê o AGENTS.md direto);
 * - skills (skills/rigor-*): copiadas para .agents/skills/ (Codex) e .claude/skills/ (Claude Code),
 *   substituindo a versão anterior do kit;
 * - pre-commit do Git: instala o modelo e migra o pre-push quando ele é o original do kit;
 * - hook Stop: instalado só por opção explícita; por padrão remove as entradas gerenciadas
 *   pelo kit, preservando o restante da configuração;
 * - scripts do composer: os que faltam são relatados (o composer.json é editado à mão ou pela skill).
 *
 * Rodar de novo é seguro: é assim que se atualiza o projeto depois de um `composer update` do kit.
 */
final class Instalador
{
    public const string PACOTE = 'christopherpersaud/kit-rigor-symfony';
    public const string INICIO = '<!-- kit-rigor:inicio';
    public const string FIM = '<!-- kit-rigor:fim -->';
    public const array AGENTES = ['claude', 'codex', 'cursor', 'copilot', 'gemini', 'opencode'];

    private const string PRE_COMMIT = '.githooks/pre-commit';
    private const string HOOK = 'rigor-hook-stop';

    /** @var list<Acao> */
    private array $acoes = [];

    /** @param list<string> $agentes integrações opcionais; .agents/skills e AGENTS.md são sempre instalados */
    public function __construct(
        private readonly string $projeto,
        private readonly string $kit,
        private readonly bool $simular = false,
        private readonly array $agentes = ['claude', 'codex'],
        private readonly string $perfil = 'completo',
        private readonly bool $stop = false,
    ) {
        if ([] !== array_diff($agentes, self::AGENTES)) {
            throw new \InvalidArgumentException('Agente inválido. Use '.implode(', ', self::AGENTES).' ou nenhum.');
        }
        if (!\in_array($perfil, ['essencial', 'completo'], true)) {
            throw new \InvalidArgumentException('Perfil inválido. Use essencial ou completo.');
        }
        if ($stop && [] === array_intersect($agentes, ['claude', 'codex'])) {
            throw new \InvalidArgumentException('--stop requer Claude Code ou Codex em --agentes.');
        }
    }

    /** @return list<Acao> */
    public function instalar(): array
    {
        $this->acoes = [];
        $this->copiarModelos();
        $this->migrarPrePush();
        $this->blocoDoAgentsMd();
        if (\in_array('claude', $this->agentes, true)) {
            $this->claudeMd();
        }
        foreach (['gemini' => 'GEMINI.md', 'copilot' => '.github/copilot-instructions.md'] as $agente => $arquivo) {
            if (\in_array($agente, $this->agentes, true)) {
                $this->instrucoesDoAgente($arquivo);
            }
        }
        $this->skills();
        $this->configurarHooks();
        $this->scriptsDoComposer();
        $this->formatacaoDoComposer();

        return $this->acoes;
    }

    private function copiarModelos(): void
    {
        $modelos = $this->kit.'/templates/projeto';
        foreach ($this->arquivosEm($modelos) as $relativo) {
            if ('essencial' === $this->perfil && !\in_array($relativo, [self::PRE_COMMIT, 'tests/Spec/RastreabilidadeTest.php', 'docs/modulos/README.md'], true)) {
                continue;
            }
            $destino = $this->projeto.'/'.$relativo;
            $conteudo = (string) file_get_contents($modelos.'/'.$relativo);
            if (!file_exists($destino)) {
                $this->gravar($destino, $conteudo, self::PRE_COMMIT === $relativo || is_executable($modelos.'/'.$relativo));
                $this->acoes[] = new Acao(Acao::CRIADO, $relativo);
            } elseif ((string) file_get_contents($destino) === $conteudo) {
                $this->acoes[] = new Acao(Acao::IGUAL, $relativo);
            } else {
                $this->acoes[] = new Acao(Acao::DIFERE, $relativo, 'compare com '.$this->doKit('templates/projeto/'.$relativo));
            }
        }
    }

    private function migrarPrePush(): void
    {
        $relativo = '.githooks/pre-push';
        $prePush = $this->projeto.'/'.$relativo;
        if (!is_file($prePush) && !is_link($prePush)) {
            return;
        }

        // Modelos publicados antes da migração. Preserve qualquer personalização do projeto.
        $originais = [
            'b6596cc9abfc9c801b4f2a98c6bc3288b19ebb077932fcbabc31c3877656872a',
            '1a9a3920cc47c25486321e86e9096b00194c823038aa476dd785bbcf70fc984b',
        ];
        if (is_link($prePush) || !\in_array(hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($prePush))), $originais, true)) {
            $this->acoes[] = new Acao(Acao::DIFERE, $relativo, 'hook do projeto preservado; confira se ele ainda roda o check rápido no push');

            return;
        }

        $destino = $this->projeto.'/'.self::PRE_COMMIT;
        $modelo = (string) file_get_contents($this->kit.'/templates/projeto/'.self::PRE_COMMIT);
        $destinoExiste = file_exists($destino) || is_link($destino);
        if ((!$this->simular || $destinoExiste) && (
            !is_file($destino)
            || $modelo !== (string) file_get_contents($destino)
            || ('Windows' !== \PHP_OS_FAMILY && !is_executable($destino))
        )) {
            $this->acoes[] = new Acao(Acao::DIFERE, $relativo, 'integre o pre-commit do kit antes de retirar a verificação do pre-push');

            return;
        }
        if (!$this->simular && !@unlink($prePush)) {
            $this->acoes[] = new Acao(Acao::DIFERE, $relativo, 'não foi possível remover o hook antigo do kit');

            return;
        }

        $this->acoes[] = new Acao(Acao::REMOVIDO, $relativo, 'check rápido transferido para o pre-commit do kit');
    }

    private function blocoDoAgentsMd(): void
    {
        $versao = InstalledVersions::isInstalled(self::PACOTE) ? (InstalledVersions::getPrettyVersion(self::PACOTE) ?? 'dev') : 'dev';
        $bloco = \sprintf(
            "%s %s: gerado por vendor/bin/rigor instalar; mude no kit, não aqui -->\n%s\n%s",
            self::INICIO,
            $versao,
            u((string) file_get_contents($this->kit.'/templates/'.('essencial' === $this->perfil ? 'AGENTS.essencial.bloco.md' : 'AGENTS.bloco.md')))->trimEnd(),
            self::FIM,
        );

        $caminho = $this->projeto.'/AGENTS.md';
        if (!is_file($caminho)) {
            $this->gravar($caminho, "# AGENTS.md\n\n".$bloco."\n");
            $this->acoes[] = new Acao(Acao::CRIADO, 'AGENTS.md', 'com o bloco do kit');

            return;
        }

        $atual = u((string) file_get_contents($caminho));
        $inicio = $atual->indexOf(self::INICIO);
        $fim = $atual->indexOf(self::FIM);
        if (null === $inicio || null === $fim || $fim < $inicio) {
            $this->gravar($caminho, $atual->trimEnd()->append("\n\n", $bloco, "\n")->toString());
            $this->acoes[] = new Acao(Acao::ATUALIZADO, 'AGENTS.md', 'bloco do kit acrescentado no fim');

            return;
        }

        $novo = $atual->slice(0, $inicio)->append($bloco, $atual->slice($fim + u(self::FIM)->length())->toString());
        if ($novo->equalsTo($atual)) {
            $this->acoes[] = new Acao(Acao::IGUAL, 'AGENTS.md', 'bloco do kit');

            return;
        }
        $this->gravar($caminho, $novo->toString());
        $this->acoes[] = new Acao(Acao::ATUALIZADO, 'AGENTS.md', 'bloco do kit trocado');
    }

    private function claudeMd(): void
    {
        $caminho = $this->projeto.'/CLAUDE.md';
        if (!is_file($caminho)) {
            $this->gravar($caminho, "# CLAUDE.md\n\n@AGENTS.md\n");
            $this->acoes[] = new Acao(Acao::CRIADO, 'CLAUDE.md', 'importa o AGENTS.md');
        } elseif (!u((string) file_get_contents($caminho))->containsAny('@AGENTS.md')) {
            $this->acoes[] = new Acao(Acao::DIFERE, 'CLAUDE.md', 'não importa o AGENTS.md: acrescente a linha @AGENTS.md para o Claude ler o bloco do kit');
        } else {
            $this->acoes[] = new Acao(Acao::IGUAL, 'CLAUDE.md');
        }
    }

    private function skills(): void
    {
        foreach (glob($this->kit.'/skills/rigor-*', \GLOB_ONLYDIR) ?: [] as $skill) {
            $nome = basename($skill);
            $pastas = ['.agents/skills'];
            if (\in_array('claude', $this->agentes, true)) {
                $pastas[] = '.claude/skills';
            }
            foreach ($pastas as $pasta) {
                $destino = $this->projeto.'/'.$pasta.'/'.$nome;
                $relativo = $pasta.'/'.$nome;
                $novos = [];
                foreach ($this->arquivosEm($skill) as $arquivo) {
                    $novos[$arquivo] = (string) file_get_contents($skill.'/'.$arquivo);
                }
                $atuais = [];
                foreach (is_dir($destino) ? $this->arquivosEm($destino) : [] as $arquivo) {
                    $atuais[$arquivo] = (string) file_get_contents($destino.'/'.$arquivo);
                }
                if ($novos === $atuais) {
                    $this->acoes[] = new Acao(Acao::IGUAL, $relativo);

                    continue;
                }
                if (!$this->simular) {
                    $this->apagar($destino);
                }
                foreach ($novos as $arquivo => $conteudo) {
                    $this->gravar($destino.'/'.$arquivo, $conteudo);
                }
                $this->acoes[] = new Acao([] === $atuais ? Acao::CRIADO : Acao::ATUALIZADO, $relativo);
            }
        }
    }

    private function configurarHooks(): void
    {
        $comando = [
            'type' => 'command',
            'command' => self::comandoDoHook(),
            'timeout' => 300,
        ];
        $this->configurarHook(
            '.claude/settings.json',
            $this->stop && \in_array('claude', $this->agentes, true)
                ? $comando + ['statusMessage' => 'Rodando a verificação no que mudou...']
                : null,
        );
        $this->configurarHook(
            '.codex/hooks.json',
            $this->stop && \in_array('codex', $this->agentes, true) ? $comando : null,
        );
    }

    /** @param array<string, string|int>|null $comando null remove só o hook do kit */
    private function configurarHook(string $relativo, ?array $comando): void
    {
        $caminho = $this->projeto.'/'.$relativo;
        $existia = is_file($caminho);
        if (!$existia && null === $comando) {
            return;
        }

        $texto = $existia ? (string) file_get_contents($caminho) : '{}';
        $config = json_decode($texto);
        if (!$config instanceof \stdClass) {
            $this->acoes[] = new Acao(Acao::DIFERE, $relativo, 'não é um objeto JSON válido: confira a configuração do hook Stop à mão');

            return;
        }

        $hooks = $config->hooks ?? new \stdClass();
        if (!$hooks instanceof \stdClass) {
            $this->acoes[] = new Acao(Acao::DIFERE, $relativo, '"hooks" não é um objeto: confira a configuração do hook Stop à mão');

            return;
        }

        $stop = $hooks->Stop ?? [];
        if (!\is_array($stop)) {
            $this->acoes[] = new Acao(Acao::DIFERE, $relativo, '"Stop" não é uma lista: confira o hook à mão');

            return;
        }

        $encontrou = false;
        foreach ($stop as $indice => $grupo) {
            if (!$grupo instanceof \stdClass || !\is_array($grupo->hooks ?? null)) {
                continue;
            }
            $alterouGrupo = false;
            foreach ($grupo->hooks as $posicao => $hook) {
                if (!self::hookDoKit($hook)) {
                    continue;
                }
                if (null !== $comando && !$encontrou) {
                    $grupo->hooks[$posicao] = (object) $comando;
                } else {
                    unset($grupo->hooks[$posicao]);
                    $alterouGrupo = true;
                }
                $encontrou = true;
            }
            if ($alterouGrupo) {
                if ([] === $grupo->hooks) {
                    unset($stop[$indice]);
                } else {
                    $grupo->hooks = array_values($grupo->hooks);
                }
            }
        }

        if (null !== $comando) {
            if (!$encontrou) {
                $stop[] = (object) ['hooks' => [(object) $comando]];
            }
            $config->hooks = $hooks;
            $hooks->Stop = array_values($stop);
        } elseif (!$encontrou) {
            return;
        } elseif ([] !== $stop) {
            $hooks->Stop = array_values($stop);
        } else {
            unset($hooks->Stop);
            if ([] === get_object_vars($hooks)) {
                unset($config->hooks);
            }
        }

        if (json_decode($texto) == $config) {
            $this->acoes[] = new Acao(Acao::IGUAL, $relativo, 'hook Stop do kit');

            return;
        }

        $this->gravar($caminho, json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
        $this->acoes[] = new Acao(
            $existia ? Acao::ATUALIZADO : Acao::CRIADO,
            $relativo,
            null === $comando ? 'hook Stop do kit removido' : 'hook Stop do kit',
        );
    }

    private static function hookDoKit(mixed $hook): bool
    {
        return $hook instanceof \stdClass
            && 'command' === ($hook->type ?? null)
            && \in_array($hook->command ?? null, [
                self::comandoDoHook(),
                '"$CLAUDE_PROJECT_DIR"/vendor/bin/'.self::HOOK,
                'php "$(git rev-parse --show-toplevel)/vendor/bin/'.self::HOOK.'"',
            ], true);
    }

    /** Sem expansão $() ou variáveis do shell: funciona em Bash, cmd e PowerShell, inclusive em subpastas. */
    private static function comandoDoHook(): string
    {
        return 'php -r "require trim(shell_exec(\'git rev-parse --show-toplevel\')).\'/vendor/bin/'.self::HOOK.'\';"';
    }

    private function instrucoesDoAgente(string $arquivo): void
    {
        if (!is_file($this->projeto.'/'.$arquivo)) {
            $this->gravar($this->projeto.'/'.$arquivo, "Read AGENTS.md in the project root and follow its instructions, including the final verification.\n");
            $this->acoes[] = new Acao(Acao::CRIADO, $arquivo, 'remete ao AGENTS.md');
        } elseif (!str_contains((string) file_get_contents($this->projeto.'/'.$arquivo), 'AGENTS.md')) {
            $this->acoes[] = new Acao(Acao::DIFERE, $arquivo, 'acrescente uma instrução para ler AGENTS.md na raiz; arquivo preservado');
        } else {
            $this->acoes[] = new Acao(Acao::IGUAL, $arquivo);
        }
    }

    private function scriptsDoComposer(): void
    {
        $modelo = json_decode((string) file_get_contents($this->kit.'/templates/referencia/composer-scripts.json'), true);
        $projeto = json_decode((string) @file_get_contents($this->projeto.'/composer.json'), true);
        $existentes = \is_array($projeto) && \is_array($projeto['scripts'] ?? null) ? $projeto['scripts'] : [];

        $esperados = 'essencial' === $this->perfil ? ['test', 'check:rapido', 'check'] : array_keys(\is_array($modelo) ? $modelo : []);
        $faltam = array_values(array_diff($esperados, array_keys($existentes)));
        if ([] === $faltam) {
            $this->acoes[] = new Acao(Acao::IGUAL, 'composer.json', 'scripts do kit');

            return;
        }
        $this->acoes[] = new Acao(Acao::FALTA, 'composer.json', \sprintf(
            'faltam os scripts %s (modelo em %s; o check:rapido se ajusta ao que o projeto tem instalado)',
            implode(', ', $faltam),
            $this->doKit('templates/referencia/composer-scripts.json'),
        ));
    }

    private function formatacaoDoComposer(): void
    {
        $projeto = json_decode((string) @file_get_contents($this->projeto.'/composer.json'), true);
        $scripts = \is_array($projeto) && \is_array($projeto['scripts'] ?? null) ? $projeto['scripts'] : [];
        if (!isset($scripts['formatar'])) {
            return;
        }
        if (!isset($scripts['formatar:verificar'])) {
            $this->acoes[] = new Acao(Acao::FALTA, 'composer.json', 'formatação detectada: defina formatar:verificar sem escrita para o CI; preserve a ferramenta e o estilo do projeto');

            return;
        }
        if (!str_contains(json_encode($scripts['check:rapido'] ?? [], \JSON_THROW_ON_ERROR), 'rigor-formatar')) {
            $this->acoes[] = new Acao(Acao::DIFERE, 'composer.json', 'formatação detectada: para adotar, acrescente @php vendor/bin/rigor-formatar no início de check:rapido (formata localmente, só verifica no CI)');
        }
    }

    private function gravar(string $caminho, string $conteudo, bool $executavel = false): void
    {
        if ($this->simular) {
            return;
        }
        if (!is_dir(\dirname($caminho))) {
            mkdir(\dirname($caminho), 0o777, true);
        }
        file_put_contents($caminho, $conteudo);
        if ($executavel) {
            chmod($caminho, 0o755);
        }
    }

    private function apagar(string $pasta): void
    {
        if (!is_dir($pasta)) {
            return;
        }
        $itens = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($itens as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($pasta);
    }

    /** @return list<string> arquivos da pasta, relativos a ela, em ordem */
    private function arquivosEm(string $pasta): array
    {
        $arquivos = [];
        $itens = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS));
        foreach ($itens as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            if ($item->isFile()) {
                $arquivos[] = Caminho::normalizar(u($item->getPathname())->slice(u($pasta)->length() + 1)->toString());
            }
        }
        sort($arquivos);

        return $arquivos;
    }

    /** Caminho de um arquivo do kit como o projeto o vê (vendor/...). */
    private function doKit(string $relativo): string
    {
        $kit = u($this->kit);

        return $kit->startsWith($this->projeto.'/') ? $kit->slice(u($this->projeto)->length() + 1)->append('/', $relativo)->toString() : $this->kit.'/'.$relativo;
    }
}
