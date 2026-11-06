# Instalar ou atualizar o kit de rigor

O kit tem duas partes:

- **Determinística:** o comando `vendor/bin/rigor instalar` copia os modelos, troca o bloco do
  `AGENTS.md` e instala as skills e integrações selecionadas. Preserva os arquivos não
  gerenciados e atualiza somente as peças do próprio kit.
- **De julgamento:** ajustar o que depende do projeto. Esse ajuste é com você, seguindo os
  passos abaixo.

Respeite as escolhas já autorizadas e as configurações do projeto. As marcações **(usuário)** indicam decisões que precisam estar definidas; não peça confirmação novamente quando o pedido ou o projeto já as definir.

## 1. Conferir o projeto

Confirme PHP 8.4+, Composer 2.2+, Git e compatibilidade com PHPUnit 12 ou 13. Confira também as restrições de Symfony/String no Composer. Se o projeto não for compatível, explique o impedimento sem atualizar PHP, Symfony ou PHPUnit por conta própria. Faça uma simulação do Composer antes de instalar.

Escolha o perfil: **essencial** para specs e testes dourados, ou **completo** para os modelos de análise, arquitetura, cobertura e CI, ajustados ao projeto. Novas adoções começam pelo essencial quando o pedido não definir outro escopo. Uma atualização preserva o perfil e os agentes anteriormente escolhidos.

- **Versões e componentes:** leia `composer.json` e `symfony.lock` para saber a versão do PHP e
  do Symfony e o que está instalado: Doctrine ORM, Doctrine Migrations, Twig, Encore/AssetMapper,
  testes JS, `dama/doctrine-test-bundle`, `brianium/paratest`.
- **Configuração existente:** veja se já existem `AGENTS.md`, `CLAUDE.md`, `.claude/settings.json`,
  `.codex/hooks.json`, `phpstan*.neon`, `.php-cs-fixer.dist.php`, `.github/workflows/`.
- **Git:** confira se `git status` está limpo, para a mudança do kit ficar separada do resto.

## 2. Instalar o pacote e rodar o instalador

Enquanto o pacote não está no Packagist, o projeto declara o repositório antes:
`composer config repositories.kit-rigor vcs https://github.com/christopherpersaud/kit-rigor-symfony`.

```bash
composer require --dev "christopherpersaud/kit-rigor-symfony:^0.4" --dry-run
composer require --dev "christopherpersaud/kit-rigor-symfony:^0.4"
```

No perfil essencial, não instale ferramentas adicionais além das dependências do pacote. No completo, escolha somente as ferramentas das verificações adotadas, preservando as versões compatíveis e o executor do projeto. A lista abaixo é uma referência, não uma ordem para instalar tudo:

- `phpstan/phpstan`
- `phpstan/extension-installer`
- `phpstan/phpstan-symfony`
- `phpstan/phpstan-phpunit`
- `phpstan/phpstan-doctrine` (só se o projeto usa Doctrine)
- `phpat/phpat`
- `brianium/paratest`
- `friendsofphp/php-cs-fixer`
- `infection/infection`

Antes de usar essas opções, confira `php vendor/bin/rigor --help`. Se a versão instalada não oferecer `--perfil` e `--agentes`, explique que ela precisa da versão do pacote que inclui essa distribuição; não rode o instalador antigo silenciosamente nem configure agentes adicionais.

Depois rode:

```bash
php vendor/bin/rigor instalar --simular --perfil=essencial --agentes=nenhum
php vendor/bin/rigor instalar --perfil=essencial --agentes=nenhum
```

Troque os argumentos conforme a escolha autorizada. `--agentes=claude,codex,cursor,copilot,gemini,opencode` aceita uma lista de integrações; `nenhum` instala só AGENTS.md e `.agents/skills`. Apenas Claude/Codex recebem hooks Stop. Cursor/OpenCode usam AGENTS.md; Gemini/Copilot recebem uma instrução para lê-lo. A seleção não remove integrações antigas: relate as existentes antes de desligá-las.

Localize a biblioteca com `composer show christopherpersaud/kit-rigor-symfony --path`. Todos os caminhos `templates/...` deste guia pertencem à **pasta desse pacote instalado**, normalmente `vendor/christopherpersaud/kit-rigor-symfony/`, e não à pasta da skill nem ao diretório temporário do MCP. Não execute os arquivos do pacote como scripts temporários isolados.

Leia a saída:

- **`criado` e `atualizado`:** o instalador gravou o arquivo.
- **`igual`:** o arquivo já estava como o modelo.
- **`difere` e `falta`:** pedem o seu ajuste, nos passos seguintes.

## 3. Ajustar ao projeto

No perfil essencial, configure `test` com o PHPUnit existente, `check:rapido` com os checks realmente adotados e `check` como a verificação completa independente do agente. Mantenha `tests/Spec/RastreabilidadeTest.php` na suíte; os testes dourados usam `KitRigor\Dourado`. Não aplique as configurações de arquitetura, cobertura ou mutação abaixo sem adoção explícita. No completo, adapte as opções pertinentes:

- **`composer.json`, scripts:** use `templates/referencia/composer-scripts.json` do kit.
  - Os comandos do modelo não são copiados literalmente: use `@php vendor/bin/...` nos scripts Composer, preserve o bundler e o runner do projeto e substitua expressões de shell específicas do sistema. Não imponha ParaTest no Windows; use o PHPUnit sequencial se o runner paralelo não suportar o ambiente.
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
- **Adoção por escopo:** se o usuário quiser validação local selecionada por tarefa, use
  `templates/referencia/escopo/LEIA-ME.md` do pacote (em
  `vendor/christopherpersaud/kit-rigor-symfony/` no projeto, ou na raiz da cópia fonte do kit).
  Separe layout leve, código/testes direcionados e
  CI completo; não ative esse modo nem substitua scripts existentes por consequência de uma
  atualização. O manifesto é opt-in (`extra.rigor.verificar.escopo`); `KitRigor\Verificar\Escopo`
  valida os caminhos e permite tratá-lo como metadado sem classificar todo JSON como layout.
  Mantenha fora do leve PHP, testes, e-mails, PDFs, exportações e templates com dourados.
  A seleção de arquivos e regressões é manual e muda em cada tarefa; CI completo não pode
  depender dessa seleção. Preserve PHPUnit sequencial ou paralelo, níveis do PHPStan e
  decisões de cobertura/mutação/migrations do projeto.
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
  - em adoção por escopo, configure `comando-check` com o comando completo próprio do CI
    (modelo `composer check:ci`), independente do manifesto; valide também o build de produção;
  - ajuste `timeout-check` ao tempo real da suíte; scripts completos precisam desativar o
    limite interno de cinco minutos do Composer. Para log/JUnit, configure `relatorios` e
    `retencao-relatorios`; eles são opcionais e não ligam cobertura;

  - com Encore, faça `composer check` gerar o build de produção antes do check rápido;
    deixe `preparo` vazio e use `preparo-cobertura: npm run dev`, evitando build de dev extra
    no check. `preparo` continua disponível para consumidores que precisam do comando comum;
  - configure `cache-phpstan` com as pastas `tmpDir` das análises, uma por linha (ex.:
    `var/cache/phpstan` e `var/cache/phpstan-tests`), nunca o cache inteiro do Symfony;
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
# PHPStan só quando adotado pelo projeto
composer check:rapido
php vendor/bin/rigor-verificar --tudo
```

Os checks adotados têm de passar. Sem hook Stop configurado, confiável e funcional no harness atual, execute `php vendor/bin/rigor-verificar` explicitamente antes de concluir qualquer tarefa com arquivos verificados alterados. Não presuma que a presença de SKILL.md ou do MCP instala hooks. Mostre ao usuário o `git status` e o `git diff`. Commit, branch
e push só quando o usuário pedir.

Em instalação com escopo, valide também a decisão do hook (layout + manifesto usa leve;
PHP volta ao direcionado; sem mudanças não repete), a reprovação de um lint inválido e o CI
completo autorizado em ambiente limpo. Um verde leve é suficiente para encerrar tarefa de
layout: não rode o direcionado por cima dele. Na instalação, confirme primeiro um verde
direcionado para estabelecer a referência da worktree.

## Atualizar depois

```bash
composer update christopherpersaud/kit-rigor-symfony
php vendor/bin/rigor instalar --perfil=<perfil-escolhido> --agentes=<agentes-escolhidos>
```

O bloco do `AGENTS.md` e as skills `rigor-*` são trocados. Os modelos que mudaram no kit
aparecem como `difere`: decida com o usuário o que trazer.
