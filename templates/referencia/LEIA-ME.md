# Modelos de referência

Arquivos que o `vendor/bin/rigor instalar` **não** copia sozinho, porque dependem do que o projeto
tem instalado. A skill `rigor-instalar` usa estes modelos para ajustar o projeto.

| Arquivo | Quando usar |
|---|---|
| `composer-scripts.json` | Scripts `test`, `phpstan`, `check:rapido`, `check`, `cobertura`, `mutacao`, `mutacao:diff`. O `check:rapido` lista tudo o que o nfdash usa: tire o que o projeto não tem (Twig, Doctrine, Node/ESLint, testes JS, Encore). Ajuste `master` no `mutacao:diff` se a branch principal for outra. |
| `tests/bootstrap-sqlite-por-processo.php` | Projeto com Doctrine ORM e `dama/doctrine-test-bundle`: um banco SQLite por processo do PHPUnit, schema criado uma vez das entidades. |
| `tests/phpstan-object-manager.php` | Projeto com Doctrine: a extensão do PHPStan valida DQL e tipos das colunas (descomente `doctrine:` no `phpstan.base.neon`). |
| `tests/phpstan-console-loader.php` | A extensão do PHPStan conhece os comandos de console (descomente `consoleApplicationLoader`). |
| `escopo/LEIA-ME.md` | Opção de adoção gradual: manifesto por tarefa, layout leve, PHP/testes direcionados e CI completo independente da seleção. Não é aplicado automaticamente. |

Além disso, no `phpunit.dist.xml` do projeto:

```xml
<phpunit ... failOnDeprecation="true" failOnNotice="true" failOnWarning="true">
    <php>
        <!-- Mesmo fuso do CI: um teste que depende do fuso da máquina falha aqui também. -->
        <ini name="date.timezone" value="UTC" />
    </php>
```

E uma `testsuite` por módulo, para `composer test -- --testsuite <módulo>` (o paratest aceita um
caminho só, então um módulo com vários arquivos e pastas vira uma suíte).
