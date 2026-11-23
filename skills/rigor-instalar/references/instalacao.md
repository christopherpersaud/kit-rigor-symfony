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

- **PHPUnit 9 a 11:** a migração é uma decisão do usuário e vem antes do kit, com a suíte verde.
  Os pontos que costumam aparecer: annotations (`@dataProvider`) viram atributos; o
  `<listeners>` do Symfony vira `<extensions><bootstrap class="Symfony\Bridge\PhpUnit\SymfonyExtension"/>`;
  `SYMFONY_PHPUNIT_VERSION`, `SYMFONY_DEPRECATIONS_HELPER` e `convertDeprecationsToExceptions` saem;
  mock sem expectativa vira `createStub()`. O PHPUnit 10+ não relata deprecations silenciadas com
  `@`, que é como o Symfony e o Doctrine as emitem: use `ignoreSuppressionOfDeprecations="true"`
  no `<source>` e chame `Doctrine\Deprecations\Deprecation::enableWithTriggerError()` no
  `tests/bootstrap.php`, senão o relatório fica vazio sem estar limpo.
- **Driver de cobertura:** sem pcov ou xdebug, a cobertura e a mutação não rodam na máquina
  (no CI, o workflow instala o pcov). Avise o usuário em vez de presumir que estão verdes.

- **Versões e componentes:** leia `composer.json` e `symfony.lock` para saber a versão do PHP e
  do Symfony e o que está instalado: Doctrine ORM, Doctrine Migrations, Twig, Encore/AssetMapper,
  testes JS, `dama/doctrine-test-bundle`, `brianium/paratest`.
- **Configuração existente:** veja se já existem `AGENTS.md`, `CLAUDE.md`, `.claude/settings.json`,
  `.codex/hooks.json`, `phpstan*.neon`, `.php-cs-fixer.dist.php`, `.github/workflows/`.
- **Git:** confira se `git status` está limpo, para a mudança do kit ficar separada do resto.

## 2. Apresentar as escolhas antes de instalar

Na primeira instalação, explique ao usuário em linguagem simples o que cada perfil entrega e
quais verificações opcionais fazem sentido para o projeto. Faça isso antes de rodar
`composer config`, `composer require` ou `vendor/bin/rigor instalar` com gravação.

| Perfil | O que entrega |
|---|---|
| **Essencial** | Specs rastreáveis com PHPUnit, testes dourados para comparar saídas com exemplos aprovados, instruções e skills para o agente e modelo de hook Git para o check rápido antes do commit. Usa os checks existentes no projeto. |
| **Completo** | Tudo do essencial e modelos para análise estática com PHPStan, regras de arquitetura com phpat, cobertura mínima, testes de mutação com Infection e CI no GitHub Actions. A instalação das ferramentas e a ativação de cada verificação dependem da escolha do usuário e da compatibilidade com o projeto. |

Explique que o **PHPStan** encontra problemas de tipos e chamadas inválidas no PHP sem precisar
executar a aplicação. No perfil completo, pergunte se o usuário quer ativar essa
análise e quais verificações adicionais quer adotar. Explique que o **Mago** formata o código
PHP e pode conferir o estilo no CI. A formatação é uma escolha independente, disponível com
qualquer perfil. Preserve o formatador existente ou apresente o Mago como opção se o projeto
ainda não usa um.

Na mesma conversa, informe quais ferramentas e verificações serão incluídas e quais ficarão
de fora da opção proposta. Apresente as integrações de agentes quando forem relevantes. O
hook `Stop` de Claude Code ou Codex é opcional, separado do pre-commit do Git, e só é ativado
quando o usuário quer verificações que possam bloquear o fim do turno.

Um pedido genérico como “instale o kit” deixa as escolhas em aberto. Pergunte qual perfil o
usuário quer e confirme os opcionais que ainda estiverem indefinidos antes de instalar. Se ele
já escolheu ou delegou a decisão, apresente o que será incluído e siga sem pedir a mesma
confirmação. Na atualização, preserve o perfil, as ferramentas e as integrações já escolhidas.
Pergunte apenas sobre as mudanças que o usuário ainda não definiu.

## 3. Instalar o pacote e rodar o instalador

Enquanto o pacote não está no Packagist, o projeto declara o repositório antes:
`composer config repositories.kit-rigor vcs https://github.com/christopherpersaud/kit-rigor-symfony`.

```bash
composer require --dev "christopherpersaud/kit-rigor-symfony:^0.5" --dry-run
composer require --dev "christopherpersaud/kit-rigor-symfony:^0.5"
```

Instale as ferramentas das verificações escolhidas, preservando as versões compatíveis e o
executor do projeto. No essencial, mantenha as dependências do kit e o formatador que tiver sido
escolhido. No completo, consulte esta lista de dependências opcionais:

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

Troque os argumentos conforme a escolha autorizada. `--agentes=claude,codex,cursor,copilot,gemini,opencode` aceita uma lista de integrações; `nenhum` instala só AGENTS.md e `.agents/skills`. Cursor/OpenCode usam AGENTS.md, e Gemini/Copilot recebem uma instrução para lê-lo. Para ativar o hook Stop em sessões autônomas, acrescente `--stop` e selecione Claude Code ou Codex. Sem essa opção, o instalador remove só as entradas Stop gerenciadas pelo kit, inclusive em instalações antigas. Inspecione e relate hooks próprios do projeto separadamente.

Localize a biblioteca com `composer show christopherpersaud/kit-rigor-symfony --path`. Todos os caminhos `templates/...` deste guia pertencem à **pasta desse pacote instalado**, normalmente `vendor/christopherpersaud/kit-rigor-symfony/`, e não à pasta da skill nem ao diretório temporário do MCP. Não execute os arquivos do pacote como scripts temporários isolados.

Leia a saída:

- **`criado` e `atualizado`:** o instalador gravou o arquivo.
- **`removido`:** o instalador retirou um hook antigo do kit.
- **`igual`:** o arquivo já estava como o modelo.
- **`difere` e `falta`:** pedem o seu ajuste, nos passos seguintes.

## 4. Ajustar ao projeto

Nos dois perfis, configure `test` com o PHPUnit existente, `check:rapido` com os checks adotados
e `check` como a verificação completa independente do agente. Mantenha
`tests/Spec/RastreabilidadeTest.php` na suíte. Os testes dourados usam `KitRigor\Dourado`.
No completo, o instalador copia os modelos do perfil. Revise os arquivos novos e adapte os
scripts e o CI para executar somente as verificações escolhidas. Mantenha as configurações
que o projeto já usa.

- **`composer.json`, scripts:** use `templates/referencia/composer-scripts.json` do kit.
  - Os comandos do modelo não são copiados literalmente: use `@php vendor/bin/...` nos scripts Composer, preserve o bundler e o runner do projeto e substitua expressões de shell específicas do sistema. Não imponha ParaTest no Windows; use o PHPUnit sequencial se o runner paralelo não suportar o ambiente.
  - Monte o `check:rapido` só com o que o projeto tem: `lint:twig` só com Twig,
    `doctrine:schema:validate` só com Doctrine, ESLint, testes JS e build só com Node.
  - Formatação opcional (kit 0.5.0+): se o projeto já define `formatar`, preserve a ferramenta,
    os caminhos e o estilo; configure `formatar:verificar` para conferir sem escrever e ponha
    `@php vendor/bin/rigor-formatar` primeiro em `check:rapido`. Localmente ele formata; com
    `CI` definido (exceto vazio, `0` ou `false`), só verifica. Sem `formatar`, não faz nada.
    Se o usuário escolher o Mago e o projeto não tiver `mago.toml`, use
    `templates/referencia/mago.toml` como ponto de partida, ajustando `php-version` e `paths`.
    Mantenha a chamada ao formatador por meio de `rigor-formatar` no check compartilhado com o CI.
    O instalador só relata o ajuste; não sobrescreve scripts. Na adoção por escopo, inclua a
    chamada também em `check:ci`, sem ampliar o `check:leve` para formatar PHP.
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
    identifique se ele ainda chama o check amplo. Mostre a configuração ao usuário antes
    de alterar uma entrada que o kit não gerencia.
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
  - ajuste `containerXmlPath` ao nome do kernel. O XML só existe depois de um container de dev
    compilado: o script `phpstan` começa com `cache:warmup --env=dev`, porque o CI instala as
    dependências no `APP_ENV` do `.env` versionado, que pode ser `prod`;
  - com Doctrine, copie `templates/referencia/tests/phpstan-object-manager.php` e descomente
    `doctrine:`. Fixe nele a `DATABASE_URL` da plataforma de produção: o tipo que o
    phpstan-doctrine deduz para as consultas depende dela, e o `.env` versionado (que o CI usa)
    pode trazer outro banco que o `.env.local`.
- **PHPStan nível 10:** um projeto que nunca rodou nesse nível costuma ter muitos erros.
  **(usuário)** Escolha entre corrigir já ou começar num nível menor e subir. Baseline e
  comentários de ignorar não entram.
  - Meça antes de propor o nível: rode a análise em cada nível, do 10 para baixo, e conte os erros.
  - As regras do phpstan-doctrine e do phpat valem em qualquer nível: num projeto legado, o
    nível 0 pode reprovar. Separe o que é bug real (DQL com campo inexistente, classe removida,
    método sem `return`), que se corrige com teste, do que é convenção, como propriedade nullable
    em coluna NOT NULL (`allowNullablePropertyForRequiredField`, comentado no
    `phpstan.base.neon`). **(usuário)** Corrigir os bugs é decisão do usuário.
  - O phpstan-doctrine analisa a consulta inteira só quando o `join` está no mesmo método que a
    monta. Um alias criado num método auxiliar aparece como `'x' is not defined`: leve o `join`
    para quem monta a consulta, sem mudar o SQL gerado.
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
    deixe `preparo` vazio e use `preparo-cobertura: npm run dev` e, com a mutação ligada,
    `preparo-mutacao: npm run dev`, evitando build de dev extra no check. Sem o build, os testes
    que renderizam as tags do Encore falham e o Infection para antes de mutar. `preparo`
    continua disponível para consumidores que precisam do comando comum;
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
- **Pre-commit do Git:** o instalador copia `.githooks/pre-commit` nos dois perfis e retira
  o `pre-push` original do kit. Confira o `core.hooksPath` efetivo. Para um clone que adotou
  os hooks do kit, ative com `git config --local core.hooksPath .githooks`. Se o projeto já
  usa outros hooks, integre `php vendor/bin/rigor-verificar --pre-commit` ao hook existente.
  Preserve hooks personalizados e relate qualquer `pre-push` que ainda rode o mesmo check.
- **Stop autônomo (opcional):** se solicitado, instale com `--stop` para os agentes escolhidos.
  No Codex, oriente o usuário a confiar no hook do projeto com `/hooks`. No Claude Code,
  confira a configuração na sessão que usará o hook.
- **CI no PR:** confira se o workflow rodará em `pull_request` e se o comando completo
  independe da seleção local. Oriente a exigir os checks adotados antes do merge.
- **Push do CI:** enviar arquivos de `.github/workflows/` com um token OAuth (como o do `gh`)
  exige o escopo `workflow` (`gh auth refresh -h github.com -s workflow`). Commit e push só com
  pedido do usuário.

## 5. Specs

- **Sem `docs/modulos/modulo-*.md`:** a rastreabilidade passa vazia. Converter os módulos em
  spec é trabalho à parte, com a skill `rigor-spec`, um módulo por vez e com aprovação do usuário.
- **Docs que já existem:** não invente regras. Proponha a conversão.

## 6. Validar

```bash
php vendor/bin/rigor-verificar --tudo
```

Depois da instalação, use testes focados durante a tarefa. Quando o usuário pedir um commit,
deixe o pre-commit executar o check rápido. Se o hook estiver inativo, execute
`php vendor/bin/rigor-verificar --pre-commit` depois de preparar o índice. Mostre o
`git status` e o `git diff`. Commit, branch e push só quando o usuário pedir.

Em projetos que adotaram escopo, confira o comando de revisão com o manifesto e o CI
independente em ambiente limpo. Se o usuário ativou o Stop, confira também a seleção do
comando leve para layout e a reutilização de um resultado verde quando nada mudou.

## Atualizar depois

```bash
composer update christopherpersaud/kit-rigor-symfony
php vendor/bin/rigor instalar --perfil=<perfil-escolhido> --agentes=<agentes-escolhidos>
```

O bloco do `AGENTS.md` e as skills `rigor-*` são trocados. Modelos que mudaram no kit
aparecem como `difere`. Repita `--stop` se o usuário quiser manter o modo autônomo nessa cópia.
