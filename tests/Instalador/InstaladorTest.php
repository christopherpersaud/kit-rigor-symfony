<?php

declare(strict_types=1);

namespace KitRigor\Tests\Instalador;

use KitRigor\Instalador\Acao;
use KitRigor\Instalador\Instalador;
use KitRigor\Tests\Support\PastaTemporaria;
use PHPUnit\Framework\TestCase;

final class InstaladorTest extends TestCase
{
    use PastaTemporaria;

    private const string KIT = __DIR__.'/../..';

    public function testProjetoVazioRecebeModelosBlocoSkillsEHooks(): void
    {
        $acoes = $this->instalar();

        foreach (['phpstan.dist.neon', 'tests/Spec/RastreabilidadeTest.php', '.github/workflows/ci.yml', 'AGENTS.md', 'CLAUDE.md', '.agents/skills/rigor-spec', '.claude/skills/rigor-spec', '.claude/settings.json', '.codex/hooks.json'] as $caminho) {
            self::assertSame(Acao::CRIADO, $acoes[$caminho]->tipo ?? null, $caminho);
        }
        self::assertStringContainsString(Instalador::INICIO, $this->ler('AGENTS.md'));
        self::assertStringContainsString('## Specs: the module docs are the contract', $this->ler('AGENTS.md'));
        self::assertSame("# CLAUDE.md\n\n@AGENTS.md\n", $this->ler('CLAUDE.md'));
        self::assertSame($this->doKit('skills/rigor-spec/SKILL.md'), $this->ler('.agents/skills/rigor-spec/SKILL.md'));
        self::assertSame($this->doKit('skills/rigor-spec/SKILL.md'), $this->ler('.claude/skills/rigor-spec/SKILL.md'));
        self::assertTrue(is_executable($this->pasta.'/.githooks/pre-push'));
    }

    public function testHooksNoFormatoDeCadaAgente(): void
    {
        $this->instalar();

        self::assertSame(['hooks' => ['Stop' => [['hooks' => [[
            'type' => 'command',
            'command' => '"$CLAUDE_PROJECT_DIR"/vendor/bin/rigor-hook-stop',
            'timeout' => 300,
            'statusMessage' => 'Rodando a verificação no que mudou...',
        ]]]]]], json_decode($this->ler('.claude/settings.json'), true));
        self::assertSame(['hooks' => ['Stop' => [['hooks' => [[
            'type' => 'command',
            'command' => 'php "$(git rev-parse --show-toplevel)/vendor/bin/rigor-hook-stop"',
            'timeout' => 300,
        ]]]]]], json_decode($this->ler('.codex/hooks.json'), true));
    }

    public function testRodarDeNovoNaoMudaNada(): void
    {
        $this->instalar();
        $antes = $this->conteudos();

        $acoes = $this->instalar();

        self::assertSame($antes, $this->conteudos());
        self::assertSame([], array_values(array_filter($acoes, static fn (Acao $a) => \in_array($a->tipo, [Acao::CRIADO, Acao::ATUALIZADO], true))));
    }

    public function testArquivoDoProjetoQueDifereDoModeloFicaComoEstaEEhRelatado(): void
    {
        $this->gravar('phpstan.dist.neon', "parameters:\n    level: 5\n");

        $acao = $this->instalar()['phpstan.dist.neon'];

        self::assertSame(Acao::DIFERE, $acao->tipo);
        self::assertTrue($acao->pedeRevisao());
        self::assertSame("parameters:\n    level: 5\n", $this->ler('phpstan.dist.neon'));
    }

    public function testAgentsMdExistenteRecebeOBlocoNoFimEDepoisSoOBlocoEhTrocado(): void
    {
        $this->gravar('AGENTS.md', "# AGENTS.md\n\nRegras do projeto.\n");

        self::assertSame(Acao::ATUALIZADO, $this->instalar()['AGENTS.md']->tipo);
        $agents = $this->ler('AGENTS.md');
        self::assertStringStartsWith("# AGENTS.md\n\nRegras do projeto.\n\n".Instalador::INICIO, $agents);
        self::assertStringEndsWith(Instalador::FIM."\n", $agents);

        $this->gravar('AGENTS.md', str_replace('## Testing', '## Testing editado', $agents)."\n## Seção do projeto depois\n");
        self::assertSame('bloco do kit trocado', $this->instalar()['AGENTS.md']->detalhe);
        self::assertSame($agents."\n## Seção do projeto depois\n", $this->ler('AGENTS.md'));
    }

    public function testClaudeMdSemOImportDoAgentsMdEhRelatado(): void
    {
        $this->gravar('CLAUDE.md', "# CLAUDE.md\n\nOutra coisa.\n");

        $acao = $this->instalar()['CLAUDE.md'];

        self::assertSame(Acao::DIFERE, $acao->tipo);
        self::assertSame("# CLAUDE.md\n\nOutra coisa.\n", $this->ler('CLAUDE.md'));
    }

    public function testSettingsDoClaudeExistenteGanhaOHookSemPerderOResto(): void
    {
        $this->gravar('.claude/settings.json', '{"permissions": {"allow": ["Bash(ls)"]}, "env": {}, "hooks": {"Stop": [{"hooks": [{"type": "command", "command": "outro.sh"}]}]}}');

        self::assertSame(Acao::ATUALIZADO, $this->instalar()['.claude/settings.json']->tipo);

        $settings = $this->ler('.claude/settings.json');
        self::assertStringContainsString('"env": {}', $settings, 'Objeto vazio continua objeto.');
        self::assertSame([
            'permissions' => ['allow' => ['Bash(ls)']],
            'env' => [],
            'hooks' => ['Stop' => [
                ['hooks' => [['type' => 'command', 'command' => 'outro.sh']]],
                ['hooks' => [[
                    'type' => 'command',
                    'command' => '"$CLAUDE_PROJECT_DIR"/vendor/bin/rigor-hook-stop',
                    'timeout' => 300,
                    'statusMessage' => 'Rodando a verificação no que mudou...',
                ]]],
            ]],
        ], json_decode($settings, true));
    }

    public function testJsonInvalidoNaoEhTocado(): void
    {
        $this->gravar('.codex/hooks.json', '{ quebrado');

        self::assertSame(Acao::DIFERE, $this->instalar()['.codex/hooks.json']->tipo);
        self::assertSame('{ quebrado', $this->ler('.codex/hooks.json'));
    }

    public function testSkillAntigaDoKitEhSubstituidaInteira(): void
    {
        $this->gravar('.claude/skills/rigor-spec/SKILL.md', 'antiga');
        $this->gravar('.claude/skills/rigor-spec/sobrando.md', 'x');
        $this->gravar('.claude/skills/do-projeto/SKILL.md', 'do projeto');

        self::assertSame(Acao::ATUALIZADO, $this->instalar()['.claude/skills/rigor-spec']->tipo);
        self::assertFileDoesNotExist($this->pasta.'/.claude/skills/rigor-spec/sobrando.md');
        self::assertSame('do projeto', $this->ler('.claude/skills/do-projeto/SKILL.md'));
    }

    public function testScriptsDoComposerQueFaltamSaoRelatados(): void
    {
        $this->gravar('composer.json', '{"scripts": {"test": "phpunit", "phpstan": "phpstan"}}');

        $acao = $this->instalar()['composer.json'];

        self::assertSame(Acao::FALTA, $acao->tipo);
        self::assertStringStartsWith('faltam os scripts check:rapido, check, cobertura, mutacao, mutacao:diff', $acao->detalhe);
        self::assertSame('{"scripts": {"test": "phpunit", "phpstan": "phpstan"}}', $this->ler('composer.json'));
    }

    public function testSimularNaoGravaNada(): void
    {
        $acoes = new Instalador($this->pasta, self::KIT, simular: true)->instalar();

        self::assertNotEmpty($acoes);
        self::assertSame([], $this->conteudos());
    }

    /** @return array<string, Acao> por caminho */
    private function instalar(): array
    {
        $acoes = [];
        foreach (new Instalador($this->pasta, (string) realpath(self::KIT))->instalar() as $acao) {
            $acoes[$acao->caminho] = $acao;
        }

        return $acoes;
    }

    /** @return array<string, string> */
    private function conteudos(): array
    {
        $conteudos = [];
        $itens = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->pasta, \FilesystemIterator::SKIP_DOTS));
        foreach ($itens as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $conteudos[$item->getPathname()] = (string) file_get_contents($item->getPathname());
        }
        ksort($conteudos);

        return $conteudos;
    }

    private function doKit(string $relativo): string
    {
        return (string) file_get_contents(self::KIT.'/'.$relativo);
    }
}
