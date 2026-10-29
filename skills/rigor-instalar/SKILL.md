---
name: rigor-instalar
description: Instala ou atualiza o kit de rigor (christopherpersaud/kit-rigor-symfony) num projeto Symfony/PHP, com specs rastreáveis, testes dourados, verificação no fim do turno do agente, trava de cobertura, phpat e CI reutilizável, ajustando ao que o projeto tem. Use quando o usuário pedir para instalar, configurar, atualizar ou conferir o kit de rigor, a verificação de fim de turno ou o CI do kit. Não use para escrever regras de negócio (rigor-spec) nem para rodar mutação (rigor-mutacao).
---

# Instalar ou atualizar o kit de rigor

O kit tem duas partes:

- **Determinística:** o comando `vendor/bin/rigor instalar` copia os modelos, troca o bloco do
  `AGENTS.md`, instala as skills e o hook de fim de turno, e nunca sobrescreve um arquivo do
  projeto.
- **De julgamento:** ajustar o que depende do projeto. Esse ajuste é com você, seguindo os
  passos abaixo.

Pergunte ao usuário antes de qualquer decisão que seja dele, marcada com **(usuário)**.

## 1. Conferir o projeto

- **Versões e componentes:** leia `composer.json` e `symfony.lock` para saber a versão do PHP e
  do Symfony e o que está instalado: Doctrine ORM, Doctrine Migrations, Twig, Encore/AssetMapper,
  testes JS, `dama/doctrine-test-bundle`, `brianium/paratest`.
- **Configuração existente:** veja se já existem `AGENTS.md`, `CLAUDE.md`, `.claude/settings.json`,
  `.codex/hooks.json`, `phpstan*.neon`, `.php-cs-fixer.dist.php`, `.github/workflows/`.
- **Git:** confira se `git status` está limpo, para a mudança do kit ficar separada do resto.

## 2. Instalar o pacote e rodar o instalador

```bash
composer require --dev christopherpersaud/kit-rigor-symfony
```

Enquanto o pacote não está no Packagist, o projeto declara o repositório antes:
`composer config repositories.kit-rigor vcs https://github.com/christopherpersaud/kit-rigor-symfony`.

Instale também as ferramentas que o kit usa e o projeto ainda não tem, cada uma com
`composer require --dev`:

- `phpstan/phpstan`
- `phpstan/extension-installer`
- `phpstan/phpstan-symfony`
- `phpstan/phpstan-phpunit`
- `phpstan/phpstan-doctrine` (só se o projeto usa Doctrine)
- `phpat/phpat`
- `brianium/paratest`
- `friendsofphp/php-cs-fixer`
- `infection/infection`

Depois rode:

```bash
vendor/bin/rigor instalar --simular   # mostra o que faria
vendor/bin/rigor instalar
```

Leia a saída:

- **`criado` e `atualizado`:** o instalador gravou o arquivo.
- **`igual`:** o arquivo já estava como o modelo.
- **`difere` e `falta`:** pedem o seu ajuste, nos passos seguintes.

## 3. Ajustar ao projeto

- **`composer.json`, scripts:** use `templates/referencia/composer-scripts.json` do kit.
  - Monte o `check:rapido` só com o que o projeto tem: `lint:twig` só com Twig,
    `doctrine:schema:validate` só com Doctrine, ESLint, testes JS e build só com Node.
  - No `mutacao:diff`, troque `master` pela branch principal do projeto.
  - Um script que já existe com outro conteúdo **não é sobrescrito**: mostre a diferença ao
    usuário.
- **`extra.rigor` no `composer.json`:** só se o layout fugir do padrão. O padrão é specs em
  `docs/modulos`, testes em `tests` e `assets`, fonte em `src` e dourados em `tests/Dourado`.
  As chaves estão em `KitRigor\Config`.
- **Verificação leve (`extra.rigor.verificar.leve`):** opcional, só se o usuário quiser que um
  turno só de layout não rode a suíte inteira. Pergunte quais pastas contam como layout e monte
  um `check:leve` com os lints delas (`lint:twig`, ESLint, testes JS) e, se houver, um teste
  rápido que renderize as páginas. Tire da lista com `!` o template que tem teste dourado.
- **Arquivos que `difere`:** compare o do projeto com o modelo do kit. Proponha só o que falta
  (uma regra, uma extensão) e preserve o que é escolha do projeto.
- **Projeto que já tinha uma versão própria destas peças** (como o nfdash, de onde o kit saiu):
  - **`AGENTS.md`:** se o texto do projeto já tem seções equivalentes às do bloco do kit (Specs,
    Testing, Checks, Static analysis), o agente leria tudo duas vezes. Proponha tirá-las do texto
    do projeto e deixar lá só o que é específico dele: nomes das testsuites, exceções, decisões.
  - **Hook de fim de turno próprio** (ex.: `.claude/hooks/verificar.sh` + `bin/verificar`):
    remova-o do `.claude/settings.json` e apague os scripts. Os dois gravam `var/check/verde`
    com impressões digitais diferentes, então um invalidaria o outro e a verificação rodaria
    sempre.
  - **Cópias locais do código do kit** (leitor de specs, `Dourado`, `bin/cobertura`): troque
    pelo kit.
    - `tests/Spec/RastreabilidadeTest.php` vira a classe vazia do modelo.
    - `RegrasDeArquitetura` passa a estender `RegrasSymfony` e fica só com `modulos()` e
      `controllersComRepository()`.
    - O script `cobertura` chama `vendor/bin/rigor-cobertura`.
    - O `KitRigor\Dourado::html()` troca imagens embutidas por `(imagem)`. Se o helper antigo
      usava outro marcador, passe a troca equivalente ou regenere os dourados e mostre o diff.
- **`phpstan.base.neon`:**
  - ajuste `phpVersion` à versão do `composer.json`;
  - ajuste `containerXmlPath` ao nome do kernel;
  - com Doctrine, copie `templates/referencia/tests/phpstan-object-manager.php` e descomente
    `doctrine:`. Fixe nele a `DATABASE_URL` da plataforma de produção: o tipo que o
    phpstan-doctrine deduz para as consultas depende dela, e o `.env` versionado (que o CI usa)
    pode trazer outro banco que o `.env.local`.
- **PHPStan nível 10:** um projeto que nunca rodou nesse nível costuma ter muitos erros.
  **(usuário)** Escolha entre corrigir já ou começar num nível menor e subir. Baseline e
  comentários de ignorar não entram.
- **`tests/Arquitetura/RegrasDeArquitetura.php`:** leia `src/Domain/*` e as dependências reais
  entre os módulos (os `use`). Proponha o mapa `modulos()` e as exceções de
  `controllersComRepository()`. **(usuário)** O mapa e as exceções são decisão do usuário.
  Sem `src/Domain`, deixe o mapa vazio. Uma regra que não se aplica é desligada sobrescrevendo o
  método com `return [];`.
- **`tests/bootstrap.php`:** com Doctrine e dama, funda o modelo
  `templates/referencia/tests/bootstrap-sqlite-por-processo.php`, que dá um banco SQLite por
  processo para o paratest. Não copie por cima do bootstrap que existe.
- **`phpunit.dist.xml`:** acrescente `failOn*`, `date.timezone` UTC e uma `testsuite` por
  módulo, como em `templates/referencia/LEIA-ME.md`.
- **`.github/workflows/ci.yml`:**
  - fixe a versão do kit igual à do `composer.lock`;
  - ligue `migrations: true` só com Doctrine Migrations, e ajuste `bancos` se a produção não for
    MySQL/MariaDB;
  - confira se a primeira migration parte de um banco vazio (cria as tabelas que as seguintes
    alteram). Se não parte, o job de migrations reprova sempre: deixe `migrations: false` com o
    motivo no arquivo e proponha uma migration inicial como trabalho à parte;
  - acrescente a `extensoes` toda extensão `ext-*` do `require` do `composer.json` que falte na
    lista padrão (ex.: `bcmath`), senão o `composer install` do CI falha;
  - ligue `preparo: npm run dev` se os testes funcionais renderizam assets do Encore;
  - **(usuário)** pergunte se liga a mutação no CI (`mutacao: true`, limite em `mutacao-msi`):
    o job muta as linhas de `src/Domain` e `src/Entity` mudadas em cada push ou PR e reprova
    abaixo do limite, contando os mutantes não cobertos. Ligue depois do primeiro CI verde, para
    a instalação não entrar no diff;
  - se o projeto já tinha um CI, **(usuário)** pergunte se substitui ou se convivem.
- **Cobertura (`tests/cobertura.php`):** rode `composer cobertura` uma vez com o modelo, que traz
  `total` 0 e nenhum piso.
  - Preencha cada piso com `floor(medido) - 2` e o `total` do mesmo jeito.
  - Para os arquivos a 0%, **(usuário)** pergunte se ganham teste agora ou se entram em
    `sem_teste`, sempre com o motivo.
- **Pre-push do git (opcional):** **(usuário)** pergunte antes de rodar
  `git config core.hooksPath .githooks`, porque muda a configuração local do git.
- **Codex:** os hooks do projeto só rodam depois de o usuário confiar neles com `/hooks` no
  Codex. Avise o usuário.

## 4. Specs

- **Sem `docs/modulos/modulo-*.md`:** a rastreabilidade passa vazia. Converter os módulos em
  spec é trabalho à parte, com a skill `rigor-spec`, um módulo por vez e com aprovação do usuário.
- **Docs que já existem:** não invente regras. Proponha a conversão.

## 5. Validar

```bash
composer phpstan
composer check:rapido
vendor/bin/rigor-verificar --tudo
```

Os dois últimos têm de passar. Mostre ao usuário o `git status` e o `git diff`. Commit, branch
e push só quando o usuário pedir.

## Atualizar depois

```bash
composer update christopherpersaud/kit-rigor-symfony
vendor/bin/rigor instalar
```

O bloco do `AGENTS.md` e as skills `rigor-*` são trocados. Os modelos que mudaram no kit
aparecem como `difere`: decida com o usuário o que trazer.
