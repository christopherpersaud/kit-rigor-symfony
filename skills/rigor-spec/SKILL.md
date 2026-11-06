---
name: rigor-spec
description: Write or update traceable business specifications and tests in a Symfony/PHP project using kit-rigor-symfony. Use for new or changed business behavior, converting module documentation to specifications, or bugs revealing an undocumented rule.
---

# Spec de regra de negócio

## Ambiente e distribuição

Requer acesso aos arquivos e ao terminal do projeto, PHP 8.4+, Git e o pacote Composer `christopherpersaud/kit-rigor-symfony` compatível com PHPUnit 12/13. A entrega por Agensi/MCP fornece as instruções; não instala a biblioteca ou hooks. Localize o pacote com `composer show christopherpersaud/kit-rigor-symfony --path`. Na ausência dele, explique o requisito e instale somente quando isso fizer parte do pedido autorizado. Preserve as decisões do projeto e não atualize sua plataforma por consequência.

Sem hook Stop confiável e funcional no agente atual, rode `php vendor/bin/rigor-verificar` explicitamente antes de concluir mudanças em arquivos verificados. Se faltar acesso ao repositório ou execução, ofereça somente análise/spec e indique quais checks não foram executados.


A rastreabilidade confere referências a IDs, não a correção das asserções nem a prova de cada cenário. A revisão humana permanece necessária. A spec é o contrato. A aprovação da spec é o ponto de decisão sobre o comportamento de negócio. Depois de aprovado,
os valores esperados dos testes já estão fixados pelos cenários, e não pelo que o código faz.

## Quanto de spec a tarefa pede

| Tarefa | Spec |
|---|---|
| Regra de negócio nova ou alterada | regra e cenários no doc, depois **parar para aprovação** |
| Bug | nenhuma, ou um cenário novo se o bug mostrar uma regra que nunca foi escrita |
| Ajuste visual, texto, refactor | nenhuma |

## Regra nova ou alterada

1. **Leia** o doc do módulo (`docs/modulos/modulo-<x>.md`), o prefixo em
   `docs/modulos/README.md` e a memória do projeto, se houver.
2. **Escreva a mudança da spec**, na seção `## Regras`:
   - Um título por regra: `### ABC-NN Frase que afirma a regra`. O `NN` é o próximo número
     livre do doc, contando também as regras revogadas e as pendentes.
   - Um parágrafo curto com o porquê.
   - Cenários com valores concretos, um por linha:
     `- **Dado** <estado>, **quando** <ação>, **então** <resultado observável>`. O resultado
     observável é o status HTTP, a mensagem exata, o que foi gravado ou o que sai no
     documento.
   - O que fica de fora, em prosa.
   - Regra que deixa de valer: ` (revogada)` no fim do título, e ela fica no doc. Não
     implementada ainda: ` (pendente)`, sob "O que ainda não está pronto".
   - **Nunca** renumere nem reuse um ID.
3. **Pare.** Mostre o diff da spec ao usuário e espere a aprovação. Nenhum código antes disso.
4. **Implemente numa passada só** o código e os testes:
   - Cada cenário aprovado vira um teste que confere exatamente os valores do cenário.
   - O método de teste leva `#[Group('ABC-NN')]` (PHP). No JS, o ID vai no começo do nome do
     `describe`/`test`.
   - Não há rodada de "teste primeiro, ver falhar".
5. **Atualize as seções descritivas** do mesmo doc (mapa de arquivos, rotas, "Como testar") e
   registre a decisão na memória do projeto, apontando o ID.
6. **Valide:**
   - `composer test -- --group ABC-NN` roda o que prova a regra;
   - o `composer check:rapido` passa, e é ele que roda a `RastreabilidadeTest`.

## Bug

Primeiro o teste que reproduz o bug: veja-o falhar e só então corrija. Se o bug mostrar uma
regra que nunca foi escrita, acrescente o cenário (ou a regra) e cite o ID no teste. Nesse
caso, mostre a spec ao usuário junto com a correção.

## Converter um doc de módulo em spec

1. Leia o doc e o código do módulo, e liste o comportamento observável que os testes já provam.
2. Proponha a seção `## Regras`: o prefixo novo (e a linha dele em `docs/modulos/README.md`),
   as regras com cenários tirados dos testes existentes e os valores que eles já conferem.
   Comportamento sem teste vira regra com teste novo, ou `(pendente)` se não estiver pronto.
3. **Pare para aprovação.** Depois, marque os testes existentes com `#[Group]` e escreva os que
   faltam.
4. A regra fica citada num doc só, o do módulo que a implementa. Os outros docs falam dela em
   prosa, com link.

## Se o código e a spec discordarem

Vale a spec, a menos que o usuário diga outra coisa. Corrija o código, ou pergunte antes de
mudar a regra.

## Saída que muda por inteiro (XML, documento, payload)

Use um teste dourado (`KitRigor\Dourado::xml()`, `html()`, `json()`, `texto()`). Quando a
mudança da saída está na spec aprovada:

1. Regenere com `ATUALIZAR_DOURADOS=1 composer test -- --filter <teste>` no Bash.
   No PowerShell, defina `$env:ATUALIZAR_DOURADOS='1'`, rode o mesmo `composer test` e restaure
   o valor anterior da variável ao terminar.
2. Mostre o `git diff tests/Dourado` ao usuário.

Nunca regenere só para fazer o teste passar.
