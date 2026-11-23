## Kit de rigor: perfil essencial

Use the installed Composer package `christopherpersaud/kit-rigor-symfony` for traceable
specifications and golden-file tests. PHP 8.4+, PHPUnit 12/13 and Git are required.
Respect the project's existing architecture, test runner, analysis level and asset bundler.
Coverage gates, mutation testing and architecture rules are optional and are not enabled by
this profile. Do not install their tools unless the project has chosen those checks.

For new or changed business rules, use `rigor-spec`: assign permanent IDs such as `CLI-04`,
write concrete Dado/quando/então scenarios, obtain approval for the specification, then
implement and test the approved behavior. Traceability checks references to IDs; they do not
prove that the assertions are correct or that every scenario is tested. Human review remains
necessary. Never regenerate golden files merely to make a failing test pass.

Run focused tests while implementing, using the project's test runner and the smallest
relevant file, suite or group. Run the quick check only when the user asks to commit.
Stage the authorized changes and let the Git `pre-commit` hook run
`php vendor/bin/rigor-verificar --pre-commit`. If the hook is inactive, run the same command
after staging and before committing. Report failures or monitored files that differ from
the Git index. Keep unrelated changes out of the commit.
Keep the project's full CI check on pull requests. Require it to pass before merging.

The portable skills live in `.agents/skills/`. Use `rigor-instalar` when updating the kit or
changing the profile or optional checks. Explain the choices to the user before changing them.
Enable the Claude Code or Codex Stop hook with `vendor/bin/rigor instalar --stop` for autonomous
sessions that need blocking verification at the end of the turn. Pass the chosen profile and
agents explicitly.

Optional formatting uses `@php vendor/bin/rigor-formatar` first in `check:rapido`: it runs
`formatar` locally and the non-writing `formatar:verificar` in CI. Without a `formatar`
script it does nothing. Preserve the project's formatter and existing style configuration.
