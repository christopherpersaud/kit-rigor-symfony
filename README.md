# kit-rigor-symfony

Um conjunto de verificações automáticas para projetos Symfony em que boa parte do código é
escrita por agentes (Claude Code, Codex). A ideia é reduzir a revisão humana a um ponto só:
**aprovar a spec**. O resto é conferido por máquina, a cada turno do agente e a cada push.

*In English:* a Symfony/PHP kit that turns module docs into traceable specs, adds golden-file
tests, an end-of-turn check for Claude Code and Codex, a coverage gate, phpat architecture rules
and a reusable GitHub Actions workflow. Docs and messages are in Brazilian Portuguese.

## O que vem no kit

| Peça | O que faz | Onde |
|---|---|---|
| **Specs rastreáveis** | Regras de negócio com ID permanente (`CLI-04`) e cenários "Dado/quando/então" nos docs de módulo. A suíte reprova se uma regra ativa fica sem teste, se um teste cita uma regra que não existe ou foi revogada, ou se um ID se repete. | `KitRigor\Spec\RastreabilidadeTestCase` |
| **Testes dourados** | A saída inteira (XML, HTML, JSON) comparada com um arquivo aprovado; qualquer mudança aparece como diff. | `KitRigor\Dourado` |
| **Verificação no fim do turno** | O hook `Stop` do Claude Code e do Codex roda o `composer check:rapido` se algo mudou desde o último verde; se falhar, o agente continua o turno até corrigir. | `vendor/bin/rigor-hook-stop`, `vendor/bin/rigor-verificar` |
| **Trava de cobertura** | Piso por pasta, piso total e nenhum arquivo a 0%; os pisos só sobem. | `vendor/bin/rigor-cobertura` |
| **Regras de arquitetura** | phpat dentro do PHPStan: camadas, UseCase `final`, controller fino, mapa de dependência entre módulos. | `KitRigor\Arquitetura\RegrasSymfony` |
| **CI reutilizável** | Check completo, cobertura, migrations num banco vazio (MySQL e MariaDB) e, opcional, mutação nas linhas mudadas, chamado com `uses:`. | `.github/workflows/php-check.yml` |
| **Instruções para o agente** | Bloco do `AGENTS.md` (lido pelo Codex direto e pelo Claude via `CLAUDE.md` → `@AGENTS.md`). | `templates/AGENTS.bloco.md` |
| **Skills** | `rigor-instalar`, `rigor-spec`, `rigor-mutacao`, no formato `SKILL.md` que os dois agentes leem. | `skills/` |

## Como instalar num projeto

### 1. O pacote

Enquanto o kit não está no Packagist, declare o repositório:

```bash
composer config repositories.kit-rigor vcs https://github.com/christopherpersaud/kit-rigor-symfony
```

Depois instale:

```bash
composer require --dev christopherpersaud/kit-rigor-symfony
```

### 2. O instalador

```bash
vendor/bin/rigor instalar --simular
vendor/bin/rigor instalar
```

O instalador nunca sobrescreve um arquivo do projeto:

- **Modelos** (`templates/projeto/`): copiados só quando faltam. Um que já existe e difere do
  modelo aparece como `difere`, para você comparar.
- **`AGENTS.md`:** recebe o bloco do kit entre os marcadores `<!-- kit-rigor:inicio -->` e
  `<!-- kit-rigor:fim -->`. Rodar de novo troca só o bloco.
- **`CLAUDE.md`:** criado com `@AGENTS.md` se faltar.
- **Skills:** vão para `.agents/skills/` (Codex) e `.claude/skills/` (Claude Code).
- **Hook `Stop`:** acrescentado ao `.claude/settings.json` e ao `.codex/hooks.json`.
- **Scripts do `composer.json`:** os que faltam são listados, não gravados. O modelo está em
  `templates/referencia/composer-scripts.json`.

### 3. O ajuste

Peça ao agente: **"instale o kit de rigor"**. A skill `rigor-instalar` ajusta ao projeto o que
o instalador não pode decidir sozinho:

- os scripts do composer;
- o mapa de módulos do phpat;
- os pisos de cobertura medidos;
- o bootstrap do banco de teste;
- as opções do CI.

Ela pergunta antes de tudo o que é decisão sua.

### Codex: um passo a mais

Os hooks de projeto do Codex só rodam depois de você confiar neles com `/hooks`, e só se a pasta
`.codex/` do projeto for confiável.

## Atualizar

```bash
composer update christopherpersaud/kit-rigor-symfony
vendor/bin/rigor instalar
```

No CI, mova a versão do `uses:` para a mesma tag.

## As skills sem instalar o pacote (opcional)

As skills também podem ser instaladas de forma global, como plugin:

- **Claude Code:** `/plugin marketplace add christopherpersaud/kit-rigor-symfony` e depois
  `/plugin install kit-rigor-symfony@kit-rigor-symfony`.
- **Codex:** `codex plugin marketplace add christopherpersaud/kit-rigor-symfony`.

Use **um** dos caminhos, o do projeto (cópia pelo instalador) ou o plugin. Com os dois, a mesma
skill aparece duas vezes.

## Configuração (`extra.rigor` no composer.json)

Tudo é opcional. Os padrões são os de um projeto Symfony:

```json
"extra": {
    "rigor": {
        "specs": "docs/modulos",
        "testes-php": ["tests"],
        "testes-js": ["assets"],
        "dourados": "tests/Dourado",
        "verificar": {
            "comando": "composer check:rapido",
            "caminhos": ["."],
            "ignorar": ["*.md", ".agent-memory/*", ".agents/*", ".claude/skills/*"]
        },
        "cobertura": {
            "fonte": "src",
            "trava": "tests/cobertura.php",
            "agrupar": ["Domain"]
        }
    }
}
```

Em `verificar.ignorar` ficam os arquivos que não disparam a verificação (fnmatch). Os docs de
spec contam sempre, mesmo sendo Markdown, porque a rastreabilidade os lê.

## Formato de uma regra

```markdown
## Regras

### CLI-04 O documento é único por Conta

A mesma Conta não cadastra o mesmo CPF/CNPJ duas vezes; outra Conta pode.

- **Dado** um cliente com o CPF X na Conta, **quando** cadastra outro com X, **então** 409.
- **Dado** o CPF X na Conta B, **quando** a Conta A cadastra X, **então** grava.
```

O teste que prova a regra leva o ID:

```php
#[Group('CLI-04')]
public function testMesmoDocumentoNaMesmaContaResponde409(): void
```

## Requisitos

- **PHP 8.4+** e **PHPUnit 12 ou 13**.
- **Para a cobertura:** pcov ou xdebug.
- **Recomendados:** `brianium/paratest`, `phpat/phpat`, `phpstan/phpstan` com as extensões
  Symfony, Doctrine e PHPUnit, e `infection/infection`.

## Origem

Extraído do nfdash (emissão de NFCom), onde o conjunto nasceu em outubro de 2026.

## Licença

MIT.
