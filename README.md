# kit-rigor-symfony

Um conjunto de verificações automáticas para projetos Symfony em que boa parte do código é
escrita por agentes de programação. O kit liga regras documentadas, referências nos testes e verificações automáticas.
**Ele apoia a revisão humana:** IDs e checks verdes não provam, sozinhos, a correção de cada
cenário ou a segurança do código. Hooks opcionais e CI ajudam a executar as verificações.

*In English:* a Symfony/PHP kit that turns module docs into traceable specs, adds golden-file
tests, a local pre-commit check, a coverage gate, phpat architecture rules
and a reusable GitHub Actions workflow. Portable Agent Skills, optional Claude Code/Codex Stop hooks, explicit verification on other
coding agents, Windows/Linux support and an Agensi skill-pack builder. Docs and messages
include Brazilian Portuguese. Traceability supports, and does not replace, human review.

## O que vem no kit

| Peça | O que faz | Onde |
|---|---|---|
| **Specs rastreáveis** | Regras de negócio com ID permanente (`CLI-04`) e cenários "Dado/quando/então" nos docs de módulo. A suíte reprova se uma regra ativa fica sem teste, se um teste cita uma regra que não existe ou foi revogada, ou se um ID se repete. | `KitRigor\Spec\RastreabilidadeTestCase` |
| **Testes dourados** | A saída inteira (XML, HTML, JSON) comparada com um arquivo aprovado; qualquer mudança aparece como diff. | `KitRigor\Dourado` |
| **Verificação antes do commit** | Roda o check local no pre-commit do Git. O hook `Stop` é opcional para sessões autônomas no Claude Code e no Codex. | `vendor/bin/rigor-verificar`, `vendor/bin/rigor-hook-stop` |
| **Trava de cobertura** | Piso por pasta, piso total e nenhum arquivo a 0%; os pisos só sobem. | `vendor/bin/rigor-cobertura` |
| **Regras de arquitetura** | phpat dentro do PHPStan: camadas, UseCase `final`, controller fino, mapa de dependência entre módulos. | `KitRigor\Arquitetura\RegrasSymfony` |
| **CI reutilizável** | Check completo, cobertura, migrations num banco vazio (MySQL e MariaDB) e, opcional, mutação nas linhas mudadas, chamado com `uses:`. | `.github/workflows/php-check.yml` |
| **Instruções para o agente** | Bloco do `AGENTS.md` (lido pelo Codex direto e pelo Claude via `CLAUDE.md` → `@AGENTS.md`). | `templates/AGENTS.bloco.md` |
| **Skills** | `rigor-instalar`, `rigor-spec`, `rigor-mutacao`, no padrão Agent Skills, conforme ferramentas e permissões do agente. | `skills/` |

## Fluxo de desenvolvimento

| Quando | O que rodar |
|---|---|
| Durante a implementação | Testes do arquivo, módulo ou regra alterada, por exemplo `composer test -- tests/XTest.php`, `composer test -- --testsuite <módulo>` ou `composer test -- --group CLI-04`. |
| Quando você pedir um commit | O Git roda `php vendor/bin/rigor-verificar --pre-commit`. O hook usa `composer check:rapido` por padrão e bloqueia o commit se o check falhar. |
| No pull request | O workflow roda o check completo em ambiente limpo, com os jobs adotados pelo projeto. Configure os checks do PR como obrigatórios para o merge na proteção da branch ou no ruleset do GitHub. |

O modelo de CI do perfil completo dispara em `pull_request` e em pushes na branch principal.
Se o projeto já tem CI ou usa o perfil essencial, configure o check completo no PR no
workflow do próprio projeto.

## Primeiros passos

O caminho de uma primeira instalação, na ordem. Os detalhes de cada passo estão em
[Como instalar num projeto](#como-instalar-num-projeto) e no guia da skill
[`rigor-instalar`](skills/rigor-instalar/references/instalacao.md).

1. **Confira os requisitos** numa branch com o `git status` limpo:

   ```bash
   php -v                              # 8.4 ou mais
   composer --version                  # 2.2 ou mais
   composer show phpunit/phpunit       # 12 ou 13
   php -m | grep -E 'pcov|xdebug'      # driver de cobertura
   ```

2. **Migre o PHPUnit antes, se for preciso.** Num projeto em PHPUnit 9 a 11, faça a migração
   como tarefa própria e deixe a suíte verde antes de instalar o kit. Os pontos mais comuns:
   - annotations viram atributos (`#[DataProvider]`);
   - o listener do Symfony vira a extensão `Symfony\Bridge\PhpUnit\SymfonyExtension`;
   - mock sem expectativa vira `createStub()`;
   - as deprecations precisam de `ignoreSuppressionOfDeprecations` e, com Doctrine, de
     `Deprecation::enableWithTriggerError()` (veja
     [`templates/referencia/LEIA-ME.md`](templates/referencia/LEIA-ME.md)).

3. **Instale e simule** antes de gravar:

   ```bash
   composer config repositories.kit-rigor vcs https://github.com/christopherpersaud/kit-rigor-symfony
   composer require --dev "christopherpersaud/kit-rigor-symfony:^0.5"
   php vendor/bin/rigor instalar --simular --perfil=essencial --agentes=nenhum
   ```

   Rode de novo sem `--simular`, com o perfil e os agentes escolhidos.

4. **Peça ao agente: "instale o kit de rigor".** A skill mede o projeto e pergunta o que é
   decisão sua. No perfil completo, conte com estes achados num projeto legado:
   - **PHPStan:** o phpstan-doctrine e o phpat valem em qualquer nível, então até o nível 0
     pode reprovar. Separe bug real (DQL com campo inexistente, classe removida) de convenção
     (propriedade nullable em coluna NOT NULL) e comece no maior nível que passa limpo.
   - **Cobertura:** os pisos vêm da medição. Os arquivos a 0% ganham teste ou entram em
     `sem_teste`, com o motivo.
   - **Arquitetura:** o mapa de módulos sai das dependências reais. Os controllers que já leem
     repository entram como exceção até serem refeitos.

5. **Valide a instalação.** Rode `php vendor/bin/rigor-verificar --tudo`.

6. **Suba o CI.** O token precisa do escopo `workflow` para enviar `.github/workflows/`
   (`gh auth refresh -h github.com -s workflow`). Com Encore, configure `preparo-cobertura` e
   `preparo-mutacao` (veja [Cache e builds no CI](#cache-e-builds-no-ci)). Ligue a mutação
   depois do primeiro CI verde.

## Como instalar num projeto

### 1. O pacote

Enquanto o kit não está no Packagist, declare o repositório:

```bash
composer config repositories.kit-rigor vcs https://github.com/christopherpersaud/kit-rigor-symfony
```

Depois instale:

```bash
composer require --dev "christopherpersaud/kit-rigor-symfony:^0.5"
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
- **Hook `Stop`:** o instalador só adiciona o hook com `--stop`. Uma instalação sem essa
  opção remove as entradas Stop geradas pelo kit em versões anteriores. As outras entradas
  e configurações dos agentes são preservadas.
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

### Ativar o pre-commit do Git

O instalador copia `.githooks/pre-commit` nos dois perfis. Em cada clone que usa os hooks
do kit, ative o diretório:

```bash
git config --local core.hooksPath .githooks
```

Se o clone já usa `.githooks`, o novo hook entra em vigor na atualização. Se usa outro
diretório ou gerenciador de hooks, preserve-o e inclua o comando
`php vendor/bin/rigor-verificar --pre-commit` no pre-commit existente. Enquanto o hook
estiver inativo, o agente roda esse comando depois de preparar o índice e antes de commitar.

A atualização retira o `.githooks/pre-push` original do kit depois de instalar o
pre-commit. Um hook personalizado é preservado e aparece na saída para revisão.

O check roda sobre a cópia de trabalho. Para garantir que o resultado corresponda ao
commit, ele exige que os arquivos monitorados coincidam com o índice do Git. Se houver
alterações monitoradas fora do índice, inclusive formatação feita durante o check, o hook
bloqueia o commit e lista os caminhos. Revise o diff e adicione ao índice só o que deve
entrar no commit. O hook nunca executa `git add`.

### Hook Stop para sessões autônomas

Ative na cópia de trabalho que vai usar para a sessão autônoma, escolhendo o agente:

```bash
php vendor/bin/rigor instalar --agentes=codex --stop
```

O instalador grava `.codex/hooks.json` para Codex e `.claude/settings.json` para Claude Code,
conforme o agente selecionado. No Codex, confie no hook do projeto com `/hooks` em uma pasta
confiável. Enquanto instalado, ele se aplica às sessões desse agente na cópia de trabalho.
Quando a verificação falha, o hook pode bloquear o encerramento do turno e devolver a falha
ao agente para correção.

Rode o instalador com o perfil e os agentes escolhidos, sem `--stop`, para voltar ao fluxo
interativo ou atualizar uma instalação antiga. Use `--simular` para conferir a remoção antes
de gravar. Repita `--stop` numa atualização se quiser manter o hook nessa cópia de trabalho.

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
| Claude Code | `CLAUDE.md` e `.claude/skills/`. Hook Stop com `--stop` |
| Codex | Hook Stop com `--stop`, sujeito à confiança do usuário |
| Cursor / OpenCode | Skills compartilhadas e AGENTS.md; nenhum hook específico é instalado |
| Gemini CLI | Skills compartilhadas e `GEMINI.md` remetendo ao AGENTS.md |
| Copilot | Skills compartilhadas e `.github/copilot-instructions.md` remetendo ao AGENTS.md |
| Agensi / MCP | Entrega as instruções; a biblioteca é instalada pelo Composer no projeto |

A descoberta das skills foi conferida com a documentação dos agentes; não é uma certificação
por execução de sessões completas em todos eles.

O perfil `essencial` copia o teste de rastreabilidade e o catálogo de módulos, sem modelos
PHPStan/phpat, cobertura, Infection ou CI. O `completo` preserva o conjunto anterior de modelos;
a skill ajusta as verificações adotadas. O padrão é o perfil completo, com as instruções do
Claude Code e as skills compartilhadas do Codex. Repita o perfil e os agentes escolhidos
nas atualizações.

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

## Formatação automática opcional

A partir de 0.5.0, acrescente `@php vendor/bin/rigor-formatar` no início de
`check:rapido` para usar os scripts de formatação que o projeto já tem. O kit não instala
Mago nem impõe estilo ou caminhos. Por exemplo, num projeto que escolheu Mago:

```json
"scripts": {
    "formatar": "mago format",
    "formatar:verificar": "mago format --check",
    "check:rapido": [
        "@php vendor/bin/rigor-formatar",
        "@test"
    ]
}
```

Localmente, o comando executa `composer formatar`. Com `CI` definido (exceto vazio, `0`
ou `false`), executa apenas `composer formatar:verificar`, que deve conferir sem escrever.
Erro de qualquer script reprova o check. Se existe `formatar` mas falta `formatar:verificar`,
o CI falha com orientação, sem rodar o formatador. Sem `formatar`, o comando não faz nada:
a adoção é opcional e mantém projetos sem formatador funcionando.

Para começar de uma configuração pronta, copie
`vendor/christopherpersaud/kit-rigor-symfony/templates/referencia/mago.toml` para a raiz do
projeto e ajuste `php-version`, `paths` e o estilo. É só uma sugestão: o instalador não copia
esse arquivo.

O instalador detecta esses scripts e sugere o ajuste, preservando o `composer.json` e a
configuração de estilo existentes. Não ponha `@formatar` diretamente no check compartilhado
com o CI. Com adoção por escopo, o comando pode ser o primeiro passo de `check:rapido` e
`check:ci`; mantenha o `check:leve` restrito aos arquivos de layout. O verificador registra
o conteúdo depois do check completo, incluindo a formatação automática, como último verde.

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

`verificar.leve` é uma opção para o hook `Stop` autônomo. Quando tudo o
que mudou desde o último verde **completo** casa com `leve.arquivos`, o hook de fim de turno
roda `leve.comando` no lugar do completo: um ajuste de layout passa só pelos lints e pelo que o
projeto puser no comando leve, em segundos. Os padrões são fnmatch na ordem, e um `!` na frente
tira da lista o que casar (um template com teste dourado, por exemplo). O verde leve não conta
como completo. No pre-commit, o verificador roda o comando local configurado. O CI roda a validação completa do projeto.

### Adoção por escopo (opcional)

Para projetos que preferem validar só a tarefa localmente, há um conjunto de modelos em
[`templates/referencia/escopo/`](templates/referencia/escopo/LEIA-ME.md). Ele separa:

| Onde | O que valida |
|---|---|
| Layout com Stop autônomo | Lints dos templates selecionados e build quando há assets; sem PHPStan/PHPUnit |
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
verificação é direcionada; o verde leve não substitui a verificação do pre-commit.

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

`preparo` continua comum aos jobs. `preparo-cobertura` e `preparo-mutacao`, quando
preenchidos, substituem o comando somente no job deles. Para Encore, faça `composer check`
executar `npm run build` **antes** do check rápido, deixe `preparo` vazio e configure:

```yaml
preparo-cobertura: npm run dev
preparo-mutacao: npm run dev   # só com mutacao: true
cache-phpstan: |
    var/cache/phpstan
    var/cache/phpstan-tests
```

Assim o check testa com assets de produção e a cobertura e a mutação usam o build de
desenvolvimento, sem um build de desenvolvimento extra no check. Os jobs permanecem
independentes e paralelos. Sem o build, os testes que renderizam as tags do Encore falham e o
Infection para antes de mutar ("Project tests must be in a passing state"). Os projetos
existentes continuam com o mesmo comportamento.

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
