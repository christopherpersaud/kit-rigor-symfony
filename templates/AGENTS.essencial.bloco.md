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

Run `php vendor/bin/rigor-verificar` before completing a task that changed checked files.
If no trusted working Stop hook is installed in the current harness, run it explicitly.
Fix failures or report a concrete blocker; do not describe a skipped check as passed.
Preserve the project's full CI check independently of the agent and its hooks.

The portable skills live in `.agents/skills/`. Optional Claude, Codex, Gemini and Copilot
integration files point to these project instructions. Selecting another agent on a later
installation does not remove existing integration files; inspect them before disabling any.
