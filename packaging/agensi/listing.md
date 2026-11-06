# Texto sugerido para a publicação

**Title:** Symfony Rigor Kit: Traceable Specs and Golden Tests

**Short description:** Turn business rules into traceable specifications and golden-file tests
in Symfony/PHP repositories, with portable agent workflows and optional quality checks.

**Description:**

Keep business rules, test references and generated outputs connected as your Symfony project
evolves. This pack includes three skills: install and configure the Composer kit, write approved
business specifications with stable IDs, and investigate Infection mutation-test results when
mutation testing is enabled.

Start with the essential profile for specs and golden tests. Add architecture checks, coverage
gates and CI templates when they fit your project. The skills preserve your existing architecture,
bundler and test runner. Optional Stop hooks integrate with Claude Code and Codex; other coding
agents run the verifier explicitly. Agent Skills instructions are usable in Codex, Claude Code,
Cursor, Copilot CLI, Gemini CLI and OpenCode, subject to the host's tools and permissions.

Requires PHP 8.4+, Composer 2.2+, Git, PHPUnit 12/13 compatibility and an agent with repository
and terminal access. The executable library is installed separately through Composer. Infection
and a coverage driver are needed only for mutation testing. Runtime messages and specification
conventions include Brazilian Portuguese. Traceability supports human review and does not
guarantee that every business scenario is correctly tested.

## Checklist do mantenedor

- Publicar uma versão da biblioteca com `--perfil` e `--agentes` antes de disponibilizar esta
  distribuição; não anunciar suporte usando somente a versão antiga instalada pelo Composer.
- Rodar `composer check` e `composer package:agensi` na revisão que será distribuída.
- Usar `dist/kit-rigor-symfony-agensi.zip`, não o ZIP inteiro do repositório.
- Conferir o preview e declarar corretamente os requisitos de execução.
- A matriz de CI cobre PHP 8.4/8.5 no Ubuntu e Windows; isso não é uma certificação de sessões
  completas em todos os agentes. Validar o fluxo no agente do publicador antes do envio.
- Este arquivo é material de publicação para revisão; gerar o pacote não envia nem publica.
