# AGENTS.md

Este repositório é o **kit de rigor** (`christopherpersaud/kit-rigor-symfony`): um pacote Composer
de desenvolvimento para projetos Symfony, mais os modelos, as skills e o workflow reutilizável que
ele instala. Ele é público: nada de segredo, dado pessoal ou detalhe de um projeto específico.

- `src/` é o código que os projetos usam (namespace `KitRigor\`); `bin/` são os comandos
  (`rigor`, `rigor-verificar`, `rigor-cobertura`, `rigor-hook-stop`).
- `templates/projeto/` é copiado para o projeto pelo `vendor/bin/rigor instalar` (só quando o
  arquivo falta); `templates/referencia/` é material que a skill `rigor-instalar` usa para
  ajustar; `templates/AGENTS.bloco.md` é o bloco que vai no AGENTS.md dos projetos.
- `skills/rigor-*/SKILL.md` valem para o Claude Code e o Codex: só `name` e `description` no
  frontmatter.
- Mudou um comportamento: atualize o README, o bloco do AGENTS.md e a skill que falam dele.
- Mudança que quebra projetos já instalados (classe, método, chave do `extra.rigor`, input do
  workflow) exige versão maior e nota no CHANGELOG.md.

Antes de terminar: `composer check` (php-cs-fixer, PHPStan nível 10, PHPUnit) tem de passar.
Commit, branch, tag e push só quando o usuário pedir.
