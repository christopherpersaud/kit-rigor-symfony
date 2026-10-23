---
name: rigor-mutacao
description: Teste de mutação com Infection num projeto com o kit de rigor - quando recomendar, como rodar só nas linhas alteradas (composer mutacao:diff) ou em arquivos inteiros (composer mutacao), e como tratar mutantes que escaparam. Use quando o usuário pedir mutação, Infection ou "testar os testes", e ao fechar um turno que mudou PHP em src/ para decidir a recomendação. Nunca rode mutação por iniciativa própria.
---

# Teste de mutação

O Infection muda o código: inverte uma condição, apaga uma chamada, desloca um limite. Depois
roda os testes que cobrem a linha mutada. Se um mutante sobrevive, aquele comportamento não é
provado por nenhum teste. A cobertura diz que a linha rodou; a mutação diz se algum teste
conferiu o resultado.

## Quando roda

**Só quando o usuário manda.** Nunca no hook, nunca no CI e nunca por iniciativa própria.

Todo turno que mudou PHP em `src/` termina com **uma linha de recomendação**, mesmo quando a
resposta é não:

- **Recomende** quando a mudança mexe em regra densa: condições, limites e fronteiras,
  cálculos, arredondamento, tributos, numeração, transições de estado, dinheiro. Diga os
  arquivos e o comando.
- **Não recomende** para ligação entre peças, renomeação, classe movida, template, texto ou
  configuração, e diga o motivo em poucas palavras.

## Como rodar

- **Na mudança (o padrão, antes do push):**
  - `composer mutacao:diff` muta só as linhas de `src/Domain` e `src/Entity` adicionadas ou
    alteradas em relação à branch principal, com commit ou sem. Ele faz `git fetch` antes.
  - Leva cerca de 1,5 minuto, quase tudo na rodada inicial com cobertura. Sem mudança nessas
    pastas, termina em segundos.
  - Não muta linhas apagadas, nem código inalterado cujo comportamento a mudança afetou.
- **Em arquivos inteiros:**
  - `composer mutacao -- src/Domain/X/Service/Y.php src/Entity/Z.php`, com os caminhos
    separados por espaço (o `--filter` está obsoleto no Infection 0.34+).
  - Leva cerca de 2 minutos por arquivo. Use para uma mudança grande numa classe de regra
    densa, ou para uma varredura que o usuário pediu.
  - Nunca `src/Domain` e `src/Entity` inteiros de uma vez.

O relatório fica em `var/infection/` (`infection.log`, `infection.html`, `resumo.log`).

## Mutantes que escaparam

Para cada mutante que escapou:

1. Leia o diff do mutante no `var/infection/infection.log`.
2. Decida se é **buraco de teste** ou **equivalente**:
   - **Buraco de teste:** escreva um teste que confira o comportamento, não a implementação.
     Se a regra estiver na spec, marque o teste com o `#[Group('ID')]` dela. Rode de novo só
     aquele arquivo para ver o mutante morrer.
   - **Equivalente:** o mutante não muda o comportamento observável. Por exemplo, um `?? 1`
     num valor que a validação garante não nulo, ou um `finally` cuja trava se solta sozinha.
     Explique o porquê ao usuário em uma linha. Não escreva teste para ele.
3. Código que só os testes usam: proponha apagar em vez de testar.

## Se um mutante for pulado por tempo

Se o Infection disser "required more time than configured", o mutante foi pulado, não
testado. O `timeout` do `infection.json5` é 120 s porque uma classe coberta por muitos testes
funcionais passa de 30 s. Suba o `timeout` em vez de aceitar o pulo.
