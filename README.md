# kit-rigor-symfony

Um conjunto de verificações automáticas para projetos Symfony em que boa parte do código é
escrita por agentes de programação. O kit liga regras documentadas, referências nos testes e verificações automáticas.
**Ele apoia a revisão humana:** IDs e checks verdes não provam, sozinhos, a correção de cada
cenário ou a segurança do código. Hooks opcionais e CI ajudam a executar as verificações.

*In English:* a Symfony/PHP kit that turns module docs into traceable specs, adds golden-file
tests, an end-of-turn check for Claude Code and Codex, a coverage gate, phpat architecture rules
and a reusable GitHub Actions workflow. Portable Agent Skills, optional Claude Code/Codex Stop hooks, explicit verification on other
coding agents, Windows/Linux support and an Agensi skill-pack builder. Docs and messages
include Brazilian Portuguese. Traceability supports, and does not replace, human review.

## O que vem no kit

| Peça | O que faz | Onde |
|---|---|---|
| **Specs rastreáveis** | Regras de negócio com ID permanente (`CLI-04`) e cenários "Dado/quando/então" nos docs de módulo. A suíte reprova se uma regra ativa fica sem teste, se um teste cita uma regra que não existe ou foi revogada, ou se um ID se repete. | `KitRigor\Spec\RastreabilidadeTestCase` |
| **Testes dourados** | A saída inteira (XML, HTML, JSON) comparada com um arquivo aprovado; qualquer mudança aparece como diff. | `KitRigor\Dourado` |
| **Verificação no fim do turno** | O hook `Stop` do Claude Code e do Codex roda o `composer check:rapido` se algo mudou desde o último verde (ou um comando leve, se só mudou layout); se falhar, o agente continua o turno até corrigir. | `vendor/bin/rigor-hook-stop`, `vendor/bin/rigor-verificar` |
| **Trava de cobertura** | Piso por pasta, piso total e nenhum arquivo a 0%; os pisos só sobem. | `vendor/bin/rigor-cobertura` |
| **Regras de arquitetura** | phpat dentro do PHPStan: camadas, UseCase `final`, controller fino, mapa de dependência entre módulos. | `KitRigor\Arquitetura\RegrasSymfony` |
| **CI reutilizável** | Check completo, cobertura, migrations num banco vazio (MySQL e MariaDB) e, opcional, mutação nas linhas mudadas, chamado com `uses:`. | `.github/workflows/php-check.yml` |
| **Instruções para o agente** | Bloco do `AGENTS.md` (lido pelo Codex direto e pelo Claude via `CLAUDE.md` → `@AGENTS.md`). | `templates/AGENTS.bloco.md` |
| **Skills** | `rigor-instalar`, `rigor-spec`, `rigor-mutacao`, no padrão Agent Skills, conforme ferramentas e permissões do agente. | `skills/` |

## Como instalar num projeto

### 1. O pacote

Enquanto o kit não está no Packagist, declare o repositório:

```bash
composer config repositories.kit-rigor vcs https://github.com/christopherpersaud/kit-rigor-symfony
```

Depois instale:

```bash
composer require --dev "christopherpersaud/kit-rigor-symfony:^0.4"
```

### 2. O instalador

```bash
vendor/bin/rigor instalar --simular
vendor/bin/rigor instalar
```

O instalador preserva arquivos não gerenciados e atualiza somente as peças do próprio kit:

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

## Agentes, perfis e Windows

As opções abaixo estão disponíveis a partir da versão 0.4.0. Confira
`php vendor/bin/rigor --help` ao atualizar uma instalação antiga.

`SKILL.md` é portátil; hooks e ferramentas de execução dependem do harness. A biblioteca exige
PHP 8.4+, Composer 2.2+, Git e compatibilidade com PHPUnit 12/13. Não atualize uma aplicação
legada automaticamente para atender esses requisitos.

```bash
# Só specs/testes dourados e instruções portáteis; sem configurar Claude/Codex.
php vendor/bin/rigor instalar --simular --perfil=essencial --agentes=nenhum
php vendor/bin/rigor instalar --perfil=essencial --agentes=nenhum

# Modelos completos e apenas as integrações escolhidas.
php vendor/bin/rigor instalar --perfil=completo --agentes=cursor,gemini,copilot,opencode
```

| Integração | O que é instalado |
|---|---|
| Núcleo, sempre | `AGENTS.md` e `.agents/skills/` |
| Claude Code | `CLAUDE.md`, `.claude/skills/` e hook Stop |
| Codex | Hook Stop, sujeito à confiança do usuário |
| Cursor / OpenCode | Skills compartilhadas e AGENTS.md; nenhum hook específico é instalado |
| Gemini CLI | Skills compartilhadas e `GEMINI.md` remetendo ao AGENTS.md |
| Copilot | Skills compartilhadas e `.github/copilot-instructions.md` remetendo ao AGENTS.md |
| Agensi / MCP | Entrega as instruções; a biblioteca é instalada pelo Composer no projeto |

Sem um hook configurado, confiável e funcional, execute `php vendor/bin/rigor-verificar`
explicitamente antes de concluir mudanças em arquivos verificados. O CI completo permanece
independente do agente. Uma conversa sem arquivos/terminal só pode oferecer orientação.
A descoberta das skills foi conferida com a documentação dos agentes; não é uma certificação
por execução de sessões completas em todos eles.

O perfil `essencial` copia o teste de rastreabilidade e o catálogo de módulos, sem modelos
PHPStan/phpat, cobertura, Infection ou CI. O `completo` preserva o conjunto anterior de modelos;
a skill ajusta as verificações realmente adotadas. Sem novas opções, o instalador mantém o
comportamento anterior: completo com Claude e Codex. Repita explicitamente o perfil e os
agentes escolhidos nas atualizações; uma seleção não remove configurações antigas.

Windows nativo usa `NUL` e caminhos normalizados. Os comandos dos hooks resolvem a raiz pelo
Git dentro do PHP, sem expansão Bash. Use `php vendor/bin/...` no terminal e `@php` nos scripts
Composer. Comandos definidos pelo projeto precisam funcionar no shell do ambiente; os modelos
continuam sendo referências a adaptar. ParaTest e ferramentas opcionais dependem de suporte
próprio: preserve PHPUnit sequencial quando necessário. No PowerShell, defina variáveis com
`$env:NOME='valor'` e restaure o valor anterior ao terminar.

## Distribuição para Agensi

```bash
composer package:agensi
```

Requer a extensão `zip` do PHP e gera `dist/kit-rigor-symfony-agensi.zip`: um pack com README,
licença e as três skills com suas referências. Não inclui `vendor`, código executável da
biblioteca, arquivos Git ou dados de projetos. A Agensi aceita packs com README na raiz e
`skills/<nome>/SKILL.md`.

As instruções precisam da versão Composer correspondente às opções descritas. Publique essa
versão antes de disponibilizar o pack: gerar o ZIP não publica código nem cria uma tag.
O texto sugerido e o checklist estão em [packaging/agensi/listing.md](packaging/agensi/listing.md).

A entrega MCP não oferece uma pasta local permanente com a biblioteca. Não execute `bin/rigor`
como script temporário isolado; instale o pacote Composer e use os arquivos dele. A skill de
instalação explica onde encontrar os modelos na biblioteca instalada.

## Desenvolver e testar

```bash
composer update --no-interaction
composer check
composer package:agensi
```

O CI do kit executa estilo, PHPStan nível 10 e PHPUnit no Ubuntu e no Windows, com PHP 8.4/8.5.
O teste de symlink exige permissão do sistema e é pulado com motivo quando ela não existe;
a proteção também é exercitada no Linux. O pacote gerado tem um teste de conteúdo e referências.
O workflow reutilizável para aplicações continua usando Linux; ele é independente dos hooks.

## Atualizar

```bash
composer update christopherpersaud/kit-rigor-symfony
php vendor/bin/rigor instalar --perfil=<perfil-escolhido> --agentes=<agentes-escolhidos>
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
            "ignorar": ["*.md", ".agent-memory/*", ".agents/*", ".claude/skills/*"],
            "leve": {
                "comando": "composer check:leve",
                "arquivos": ["templates/*", "assets/*", "!templates/pdf/*"]
            }
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

`verificar.leve` é opcional (sem ele, todo turno com mudança roda o `comando`). Quando tudo o
que mudou desde o último verde **completo** casa com `leve.arquivos`, o hook de fim de turno
roda `leve.comando` no lugar do completo: um ajuste de layout passa só pelos lints e pelo que o
projeto puser no comando leve, em segundos. Os padrões são fnmatch na ordem, e um `!` na frente
tira da lista o que casar (um template com teste dourado, por exemplo). O verde leve não conta
como completo: a primeira mudança fora da lista, o `vendor/bin/rigor-verificar` (pre-push) e o
CI rodam o comando completo por cima do layout que só passou no leve.

### Adoção por escopo (opcional)

Para projetos que preferem validar só a tarefa localmente, há um conjunto de modelos em
[`templates/referencia/escopo/`](templates/referencia/escopo/LEIA-ME.md). Ele separa:

| Onde | O que valida |
|---|---|
| Layout no fim do turno | Lints dos templates selecionados e build quando há assets; sem PHPStan/PHPUnit |
| Código ou tarefa mista | PHPStan, lints e testes selecionados pelo projeto |
| CI | Build de produção, PHPStan e PHPUnit completos, independentes da seleção local |

O projeto opta pelo manifesto com `extra.rigor.verificar.escopo` (ex.: `rigor-escopo.json`).
`KitRigor\Verificar\Escopo` lê as listas `arquivos` e `testes` e recusa pastas, opções,
travessias e links para fora do projeto. As pastas de teste vêm de `testes-php`.

O manifesto é metadado: atualizar a seleção junto com uma `div` não impede o caminho leve.
Antes de escolher esse caminho, o kit confere o manifesto. JSON inválido, PHP na seleção ou
arquivo fora dos padrões leves exige o comando direcionado. O manifesto continua sendo
verificado mesmo se houver uma regra de ignorar JSON. Não é preciso marcar todo JSON como layout.

A escolha dos arquivos e das regressões afetadas é manual. Exclua do leve e-mails, PDFs,
exportações e templates com testes dourados. Sem verde anterior na worktree, a primeira
verificação é direcionada; o verde leve não substitui a verificação do pre-push.

Esse modo não é instalado nem ativado automaticamente. A skill preserva os comandos, o
executor PHPUnit e os níveis de análise do projeto. Cobertura, mutação e migrations continuam
sendo decisões separadas. O CI deve chamar o comando completo próprio (`composer check:ci`
no modelo), nunca depender apenas do manifesto da última tarefa.

### Tempo e relatórios do CI

O workflow reutilizável aceita `timeout-check` (padrão 20 minutos), `relatorios` (caminhos para
guardar como artefato; vazio desliga) e `retencao-relatorios` (padrão 14 dias). A retenção vale
também para um check que falhou. O projeto precisa gerar os arquivos, como log e JUnit; a
receita por escopo mostra os comandos. Esses inputs não ativam cobertura ou mutação.

O job de check usa `COMPOSER_PROCESS_TIMEOUT=1800` e preserva o limite externo do job. Nos
scripts completos, desative também o limite interno do Composer com
`Composer\Config::disableProcessTimeout`. O passo de check usa Bash com `pipefail`, para que
salvar a saída com `tee` preserve o código de falha.

## Cache e builds no CI

O workflow obtém o cache de downloads do Composer com `composer config cache-files-dir`,
em vez de presumir uma pasta. O cache do npm continua gerenciado pelo `setup-node`.

`cache-phpstan` aceita as pastas de `tmpDir`, uma por linha. É opcional e só roda no check,
depois do preparo. Cada execução salva uma chave nova, incluindo sistema, versão do PHP, lock
e configuração; a próxima restaura a mais recente compatível. O PHPStan invalida os resultados
dos arquivos alterados. Não inclua o cache inteiro do Symfony nem bancos ou sessões de teste.

`preparo` continua comum aos jobs. `preparo-cobertura`, quando preenchido, substitui o comando
somente na cobertura. Para Encore, faça `composer check` executar `npm run build` **antes**
do check rápido, deixe `preparo` vazio e configure:

```yaml
preparo-cobertura: npm run dev
cache-phpstan: |
    var/cache/phpstan
    var/cache/phpstan-tests
```

Assim o check testa com assets de produção e a cobertura usa seu build de desenvolvimento,
sem um build de desenvolvimento extra no check. Os jobs permanecem independentes e paralelos.
Se a mutação estiver ligada e precisar de assets, configure seu preparo no comando de mutação
ou mantenha o preparo comum. Os projetos existentes continuam com o mesmo comportamento.

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
