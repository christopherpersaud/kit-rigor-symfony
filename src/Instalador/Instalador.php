<?php

declare(strict_types=1);

namespace KitRigor\Instalador;

use Composer\InstalledVersions;

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
 * - hook de fim de turno: acrescentado ao .claude/settings.json e ao .codex/hooks.json se ainda
 *   não estiver lá;
 * - scripts do composer: os que faltam são relatados (o composer.json é editado à mão ou pela skill).
 *
 * Rodar de novo é seguro: é assim que se atualiza o projeto depois de um `composer update` do kit.
 */
final class Instalador
{
    public const string PACOTE = 'christopherpersaud/kit-rigor-symfony';
    public const string INICIO = '<!-- kit-rigor:inicio';
    public const string FIM = '<!-- kit-rigor:fim -->';

    /** Trecho que identifica o hook do kit nos arquivos de configuração dos agentes. */
    private const string HOOK = 'rigor-hook-stop';

    /** @var list<Acao> */
    private array $acoes = [];

    public function __construct(
        private readonly string $projeto,
        private readonly string $kit,
        private readonly bool $simular = false,
    ) {
    }

    /** @return list<Acao> */
    public function instalar(): array
    {
        $this->acoes = [];
        $this->copiarModelos();
        $this->blocoDoAgentsMd();
        $this->claudeMd();
        $this->skills();
        $this->hookDoClaude();
        $this->hookDoCodex();
        $this->scriptsDoComposer();

        return $this->acoes;
    }

    private function copiarModelos(): void
    {
        $modelos = $this->kit.'/templates/projeto';
        foreach ($this->arquivosEm($modelos) as $relativo) {
            $destino = $this->projeto.'/'.$relativo;
            $conteudo = (string) file_get_contents($modelos.'/'.$relativo);
            if (!file_exists($destino)) {
                $this->gravar($destino, $conteudo, is_executable($modelos.'/'.$relativo));
                $this->acoes[] = new Acao(Acao::CRIADO, $relativo);
            } elseif ((string) file_get_contents($destino) === $conteudo) {
                $this->acoes[] = new Acao(Acao::IGUAL, $relativo);
            } else {
                $this->acoes[] = new Acao(Acao::DIFERE, $relativo, 'compare com '.$this->doKit('templates/projeto/'.$relativo));
            }
        }
    }

    private function blocoDoAgentsMd(): void
    {
        $versao = InstalledVersions::isInstalled(self::PACOTE) ? (InstalledVersions::getPrettyVersion(self::PACOTE) ?? 'dev') : 'dev';
        $bloco = \sprintf(
            "%s %s: gerado por vendor/bin/rigor instalar; mude no kit, não aqui -->\n%s\n%s",
            self::INICIO,
            $versao,
            u((string) file_get_contents($this->kit.'/templates/AGENTS.bloco.md'))->trimEnd(),
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
            foreach (['.agents/skills', '.claude/skills'] as $pasta) {
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

    private function hookDoClaude(): void
    {
        $this->acrescentarHook('.claude/settings.json', [
            'type' => 'command',
            'command' => '"$CLAUDE_PROJECT_DIR"/vendor/bin/'.self::HOOK,
            'timeout' => 300,
            'statusMessage' => 'Rodando a verificação no que mudou...',
        ]);
    }

    /** O Codex roda o hook na pasta da sessão, que pode ser uma subpasta: o caminho parte da raiz do git. */
    private function hookDoCodex(): void
    {
        $this->acrescentarHook('.codex/hooks.json', [
            'type' => 'command',
            'command' => 'php "$(git rev-parse --show-toplevel)/vendor/bin/'.self::HOOK.'"',
            'timeout' => 300,
        ]);
    }

    /** @param array<string, string|int> $comando */
    private function acrescentarHook(string $relativo, array $comando): void
    {
        $caminho = $this->projeto.'/'.$relativo;
        $existia = is_file($caminho);
        $texto = $existia ? (string) file_get_contents($caminho) : '{}';
        if (u($texto)->containsAny(self::HOOK)) {
            $this->acoes[] = new Acao(Acao::IGUAL, $relativo, 'hook Stop do kit');

            return;
        }

        $config = json_decode($texto);
        if (!$config instanceof \stdClass) {
            $this->acoes[] = new Acao(Acao::DIFERE, $relativo, 'não é um objeto JSON válido: acrescente o hook Stop à mão (modelo no README do kit)');

            return;
        }
        $config->hooks ??= new \stdClass();
        if (!$config->hooks instanceof \stdClass) {
            $this->acoes[] = new Acao(Acao::DIFERE, $relativo, '"hooks" não é um objeto: acrescente o hook Stop à mão');

            return;
        }
        $config->hooks->Stop = [...(\is_array($config->hooks->Stop ?? null) ? $config->hooks->Stop : []), ['hooks' => [$comando]]];

        $this->gravar($caminho, json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
        $this->acoes[] = new Acao($existia ? Acao::ATUALIZADO : Acao::CRIADO, $relativo, 'hook Stop do kit');
    }

    private function scriptsDoComposer(): void
    {
        $modelo = json_decode((string) file_get_contents($this->kit.'/templates/referencia/composer-scripts.json'), true);
        $projeto = json_decode((string) @file_get_contents($this->projeto.'/composer.json'), true);
        $existentes = \is_array($projeto) && \is_array($projeto['scripts'] ?? null) ? $projeto['scripts'] : [];

        $faltam = array_values(array_diff(array_keys(\is_array($modelo) ? $modelo : []), array_keys($existentes)));
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
                $arquivos[] = u($item->getPathname())->slice(u($pasta)->length() + 1)->toString();
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
