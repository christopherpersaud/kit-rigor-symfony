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
and validation. Resolve all template paths from the installed Composer package, not from this
skill's directory. When delivered through Agensi/MCP, the skill is guidance; Composer installs
the executable library into the target repository. Never run its PHP entrypoints as isolated
MCP scripts or copy vendor dependencies into the skill distribution.

Preserve already authorized choices and existing project conventions. Set up only the selected
integrations. Agent Skills discovery does not imply support for a Stop hook: when the current
harness has no trusted, working hook, run `php vendor/bin/rigor-verificar` explicitly before
finishing a task that changed checked files. Keep the full CI check independent of the agent.

Report what changed, commands executed and their outcomes, any remaining blockers and the
selected profile/integrations. Never report an unexecuted check as passed. Commit, tag, push
and publication require an explicit user instruction.
