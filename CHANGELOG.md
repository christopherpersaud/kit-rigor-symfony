# Changelog

## 0.1.1 (2026-10-23)

- O leitor de specs quebra as linhas do Markdown em modo Unicode. Antes, um caractere que termina
  no byte 0x85 (como o ✅) virava quebra de linha, a linha saía como UTF-8 inválido e a
  rastreabilidade inteira dava erro.

## 0.1.0 (2026-10-23)

- Primeira versão, extraída do nfdash: rastreabilidade de specs, testes dourados, verificação no
  fim do turno (Claude Code e Codex), trava de cobertura, regras phpat, workflow reutilizável,
  instalador, skills `rigor-instalar`, `rigor-spec` e `rigor-mutacao`.
