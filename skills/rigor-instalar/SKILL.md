---
name: rigor-instalar
description: Install, update or configure the kit-rigor-symfony Composer package in a Symfony/PHP repository, including portable agent instructions and optional verification tools. Use for kit setup, end-of-task checks or its CI configuration; use rigor-spec for business specifications and rigor-mutacao for mutation testing.
---

# Set up the Symfony rigor kit

Use this workflow in a repository with filesystem access and a terminal. Require PHP 8.4+,
Composer 2.2+, Git, PHPUnit 12/13 compatibility, and network access for package installation.
Read the project's instructions and installed versions first. Report incompatible prerequisites
without upgrading the application's platform or changing its architecture automatically.

Follow the [installation guide](references/instalacao.md) for the requested setup or update.
It covers the essential/full profiles, optional harness integrations, project-specific checks
and validation. On a first installation, explain both profiles and the available optional checks
in plain language before changing the project. Name PHPStan (static analysis) and Mago (PHP
formatting) explicitly. Ask for any profile or tool choices the user has left open and wait
for their answer before installing. A generic request to install the kit leaves those choices
open. On updates, preserve the choices already made and ask about any new or changed checks
the user has left open.

Resolve all template paths from the installed Composer package, not from this
skill's directory. When delivered through Agensi/MCP, the skill is guidance; Composer installs
the executable library into the target repository. Never run its PHP entrypoints as isolated
MCP scripts or copy vendor dependencies into the skill distribution.

Optional formatting (0.5.0+) reuses project scripts: `rigor-formatar` applies `formatar`
locally and runs the non-writing `formatar:verificar` in CI. Do not impose a formatter.

Preserve already authorized choices and existing project conventions. Set up the selected
integrations. During implementation and review, use focused tests. When the user requests
a commit, let the Git pre-commit hook run `php vendor/bin/rigor-verificar --pre-commit`. If
inactive, run it after staging and before committing. Run full CI on pull requests. Install
the Stop hook only when the user requests blocking checks for autonomous sessions. The installer uses `--stop` for this and removes legacy kit hooks without it.

Report what changed, commands executed and their outcomes, any remaining blockers and the
selected profile/integrations. Never report an unexecuted check as passed. Commit, tag, push
and publication require an explicit user instruction.
