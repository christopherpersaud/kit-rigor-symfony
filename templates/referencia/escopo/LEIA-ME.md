# Adoção por escopo, com layout leve e CI completo

Opção para um projeto que quer validar a tarefa localmente sem rodar a base inteira a cada
turno. Não é instalada automaticamente nem substitui a configuração existente.

1. Copie `bin/verificar-escopo.php` para `bin/` do projeto e adapte os lints e o build ao que
   ele usa. O modelo executa PHPUnit sequencial; preserve o executor escolhido no projeto.
2. Mescle `configuracao.json` e `composer-scripts.json` no `composer.json`, preservando os
   scripts e opções existentes. Ajuste `phpstan:ci` às configurações e níveis já adotados;
   remova a segunda análise se não houver `phpstan.tests.neon`, e os passos de Node/Twig se
   o projeto não os usar. Não instale ferramentas só para completar o modelo.
3. Crie o manifesto a partir de `rigor-escopo.json`. Substitua-o em cada tarefa pelos arquivos
   individuais alterados e pelos testes afetados, incluindo rastreabilidade e regressões
   relevantes. A seleção de dependências é manual.
4. Escolha quais templates e assets são layout. Exclua e-mails, PDFs, exportações e templates
   com testes dourados. Ajuste os padrões aos caminhos do projeto; não os copie sem conferir.
5. Quando o usuário pedir CI, use `composer check:ci` no input `comando-check` do workflow
   reutilizável. Esse comando é completo e não lê o manifesto. Cobertura, mutação e migrations
   são opções separadas: preserve as decisões do projeto.

`extra.rigor.verificar.escopo` informa o nome do manifesto ao kit. Ele contém as listas
`arquivos` e `testes`: caminhos relativos de arquivos existentes, nunca pastas ou opções.
Os testes precisam estar nas pastas de `testes-php`. Caminhos externos, travessias e links
para fora do projeto são recusados por `KitRigor\Verificar\Escopo`.

O manifesto é metadado: atualizá-lo junto com uma tela não impede o caminho leve. O kit lê a
seleção antes de decidir. Se ela inclui PHP ou algo fora da lista de layout, ou se o JSON é
inválido, escolhe o comando direcionado. O comando leve também confere a seleção para impedir
uma chamada manual sobre código. Não marque todo `*.json` como layout para contornar isso.

| Mudança | Validação |
|---|---|
| Só tela/assets e manifesto | `composer check:leve`: lints selecionados; build se houver assets |
| PHP, testes, documentos ou tarefa mista | `composer check:rapido`: lints, PHPStan e testes selecionados |
| Nada mudou desde o último verde | O hook não repete comandos |
| CI | `composer check:ci`: lints, build, PHPStan e testes completos |

Uma worktree sem verde anterior começa com o direcionado. O verde leve vale só para o fim do
turno; o pre-push, quando ativado, continua no direcionado. Não rode o rápido de novo por cima
de um verde leve apenas para encerrar o turno. O modelo mantém `composer test -- tests/XTest.php`
e `composer phpstan -- src/X.php` para seleções explícitas, sempre de arquivos individuais.

Para guardar o log e o JUnit no CI reutilizável:

```yaml
with:
    comando-check: |
        mkdir -p var/check/ci
        composer check:ci 2>&1 | tee var/check/ci/check.log
    timeout-check: 30
    relatorios: var/check/ci/
    retencao-relatorios: 14
    cobertura: false # exemplo de adoção gradual; confirme a escolha do projeto
```

Os scripts completos desativam o limite interno de cinco minutos do Composer. O job mantém
seu limite próprio. Confira uma execução em ambiente limpo (assets, extensões e variáveis de
teste) antes de exigir o check nos merges; nunca forneça credenciais de produção aos testes.
