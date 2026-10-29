# Changelog

## 0.3.0 (2026-10-29)

- Verificação leve no fim do turno, opcional: `extra.rigor.verificar.leve` (`comando` e
  `arquivos`, padrões fnmatch com `!` para tirar). Quando tudo o que mudou desde o último verde
  completo casa com a lista, o hook `Stop` roda o comando leve no lugar do completo. O
  `rigor-verificar` (pre-push) continua sempre completo. O estado em `var/check/` passa de
  `verde` para `verde.json` (o sha1 de cada arquivo), então a primeira verificação depois de
  atualizar roda o comando completo uma vez.
- `Verificacao` ganha `comando` e `leve`; o motivo do bloqueio do hook cita o comando que rodou.

## 0.2.0 (2026-10-23)

- Mutação no CI, opcional: o workflow reutilizável ganha o job `mutacao` (inputs `mutacao`,
  `mutacao-msi` e `comando-mutacao`). Ele muta as linhas de `src/Domain` e `src/Entity` mudadas
  pelo push ou pelo pull request e reprova abaixo do limite (90% por padrão), contando os
  mutantes não cobertos. Desligado por padrão: quem não liga não vê diferença.
- Bloco do `AGENTS.md` e skill `rigor-mutacao`: com a mutação no CI, o agente roda o
  `composer mutacao:diff` antes de dizer que terminou; mutante equivalente se resolve tirando a
  redundância ou, se não der, com `// @infection-ignore-all: <motivo>` mostrado ao usuário. Sem
  a mutação no CI, a regra de antes continua.
- Skill `rigor-instalar`: confere se as migrations partem do banco vazio, as extensões `ext-*`
  do `composer.json` no CI e a plataforma do banco no loader do PHPStan.

## 0.1.1 (2026-10-23)

- O leitor de specs quebra as linhas do Markdown em modo Unicode. Antes, um caractere que termina
  no byte 0x85 (como o ✅) virava quebra de linha, a linha saía como UTF-8 inválido e a
  rastreabilidade inteira dava erro.

## 0.1.0 (2026-10-23)

- Primeira versão, extraída do nfdash: rastreabilidade de specs, testes dourados, verificação no
  fim do turno (Claude Code e Codex), trava de cobertura, regras phpat, workflow reutilizável,
  instalador, skills `rigor-instalar`, `rigor-spec` e `rigor-mutacao`.
