## Specs: the module docs are the contract

`docs/modulos/modulo-*.md` are the specification of each module, not documentation written
after the fact. A module doc declares its business rules under `## Regras`, one heading per
rule, with a stable ID (module prefix + two digits) and scenarios with concrete values:

```markdown
### CLI-04 O documento é único por Conta

A mesma Conta não cadastra o mesmo CPF/CNPJ duas vezes; outra Conta pode.

- **Dado** um cliente com o CPF X na Conta, **quando** cadastra outro com X, **então** 409.
- **Dado** o CPF X na Conta B, **quando** a Conta A cadastra X, **então** grava.
```

- **IDs are permanent.** Never renumber or reuse one. A rule that stops applying gets
  ` (revogada)` at the end of its heading and stays in the doc; a rule not implemented yet
  gets ` (pendente)` and lives under "O que ainda não está pronto".
- **Prefixes:** one per doc, listed in `docs/modulos/README.md`. A new module doc gets its
  prefix there and starts with its `## Regras` section.
- **Rules are business behaviour,** what a caller observes (HTTP response, what is saved,
  what goes out in a document or API). Screen details, file maps and routes stay in the
  descriptive sections of the same doc.
- **Tests point to the rule** with `#[Group('CLI-04')]` on the test method (PHP) or the ID in
  the `describe`/`test` name (JS, `*.test.js`). `composer test -- --group CLI-04` runs
  everything that proves a rule.
- `tests/Spec/RastreabilidadeTest.php` (kit `christopherpersaud/kit-rigor-symfony`, part of the
  suite) enforces the link: every active rule has a scenario and at least one test, no test
  cites an unknown or revoked ID, IDs are unique and follow the doc's prefix. It checks
  structure, not meaning: the guarantee comes from each scenario becoming a test that asserts
  the scenario's values.
- A rule is cited in one doc only, the one of the module that implements it; other docs
  mention it in prose and link to it.

**How much spec a task needs:**

| Task | Spec |
|---|---|
| New or changed business rule | rules and scenarios in the doc, then **stop for approval** |
| Bug | none, or one new scenario if the bug shows a rule that was never written |
| Visual tweak, copy, refactor | none |

**Workflow for a new or changed rule** (skill `rigor-spec`):

1. Read the module doc (and the project memory, if it has one).
2. Write the spec change: new or changed rules and scenarios with concrete values, and what
   is out of scope. **Stop and show the spec diff to the user.** This is the user's only
   review point; no code before it is approved.
3. In one pass, implement the code and the tests: each approved scenario becomes a test that
   asserts exactly the scenario's values, tagged with the rule ID. No test-first round: the
   expected values were fixed by the approved scenario, not read from the code.
4. Update the descriptive sections of the same doc (file map, routes, "Como testar").

**Bugs are the exception where the test comes first:** write a test that reproduces the
bug, see it fail, then fix. It costs a minute and proves the fix hit the actual cause.

If the code and the spec disagree, the spec wins unless the user says otherwise: fix the
code, or ask before changing the rule.

## Testing

Always run tests with `composer test` (paratest, one process per core, coverage driver off),
never `vendor/bin/phpunit` directly. Arguments go after `--`: one file or folder
(`composer test -- tests/Controller/XControllerTest.php`), one module
(`composer test -- --testsuite <module>`; paratest takes a single path, so a set of files is a
testsuite in `phpunit.dist.xml`, and a new module gets its own), one rule or filter
(`composer test -- --group CLI-09`, `composer test -- --filter Nota`).

A feature isn't done until it has a test that exercises it the way a caller would: an HTTP
request for a controller, a service call for a service, not just "it didn't throw".
Functional tests extend `WebTestCase`; service-level tests extend `KernelTestCase`.

PHPUnit runs in UTC, the CI's timezone: a date built in a test carries an explicit timezone.
Tests must pass in any order (`composer test -- --order-by=random`): a shared counter or a
number that collides with real numbering is an order dependency.

**Golden files** (`tests/Dourado/`, helper `KitRigor\Dourado`: `xml()`, `html()`, `json()`,
`texto()`): a whole output (an XML, a rendered document, an API payload) is compared with an
approved file, so any change in the output, intended or not, fails with a diff. What changes on
every run (signatures, timestamps, embedded image bytes) is stripped first; the rest of the
scenario is deterministic (fixed dates, numbers and codes). When the change is intended and in
the spec, regenerate with `ATUALIZAR_DOURADOS=1 composer test -- --filter <test>` and show the
`git diff tests/Dourado` to the user as part of the change. Never regenerate just to make a
failing test pass.

## Checks

**Optional scoped adoption:** if the project chose `extra.rigor.verificar.escopo`, update its
manifest for each task with individual changed files, affected regression tests and the
traceability test. The project chooses the scope manually. Use the light command for layout
and the selected check for PHP or mixed changes; keep e-mails, PDFs, exports and golden-tested
templates out of the light list. The configured manifest is metadata, but the kit permits a
light run only when its selection is valid and contains layout. The CI must use a separate
complete command (`composer check:ci` in the reference), independent of the manifest. Preserve
the project's runner, analysis levels and rollout choices; do not enable global coverage,
mutation or migrations merely by updating the kit. These project choices take precedence over
the default commands below.

Two levels, so the end of a turn stays fast and the slow parts still run on every push:

- **`composer check:rapido`, at the end of every turn.** Lints, PHPStan, php-cs-fixer
  `--dry-run`, JS lint and tests if the project has them, and the whole PHPUnit suite in
  parallel without coverage. The agent's Stop hook (Claude Code: `.claude/settings.json`;
  Codex: `.codex/hooks.json`) runs it through `vendor/bin/rigor-verificar`, which skips it when
  no checked file changed since the last green run (fingerprint in `var/check/`). When the
  project sets `extra.rigor.verificar.leve` and every file changed since the last full green
  run matches its `arquivos` (layout: templates, styles, front-end), the hook runs the light
  command instead; that is enough for a layout-only turn, so don't run `check:rapido` by hand on
  top of it. The full command still runs on the next turn that touches anything else, on the
  `pre-push` and in the CI. If it fails,
  the turn goes on until it is green: fix the cause; never disable the hook
  (`VERIFICAR_DESLIGADO=1`) or work around it, that is the user's call only. Full log in
  `var/check/ultimo.log`. The git `pre-push` hook (`.githooks/pre-push`) runs the same check.
- **`composer check`, in the CI** (`.github/workflows/ci.yml`, calling the kit's reusable
  workflow on every push to the main branch and every PR): `check:rapido` plus the slow parts
  (production build). When the project has Doctrine Migrations, a second job runs every
  migration on an empty database and `doctrine:schema:validate` without `--skip-sync`, so an
  entity changed without a migration fails there. When the project turns it on, a mutation job
  runs on the changed lines (see "Mutation testing" below). Another job, `composer cobertura`
  (`vendor/bin/rigor-cobertura`), runs the suite with pcov and enforces `tests/cobertura.php`:
  a floor for all of `src/`, a floor per folder (measured coverage rounded down minus a 2-point
  margin) and no file with code at 0%. New code ships with its tests, so this never needs a
  floor lowered; when the script says a floor can rise, raise it in the same change. Lowering a
  floor or adding a file to `sem_teste` is the user's call. Coverage only says a line ran, not
  that a test checked its result.

Run `composer check:rapido` yourself before saying a task is done; after a push, check that the
CI went green.

**Mutation testing** (Infection, `infection.json5`, `src/Domain` and `src/Entity`; skill
`rigor-mutacao`) changes the code (flips a condition, drops a call, shifts a boundary) and runs
the test cases that cover the mutated line; a mutant that survives is behaviour no test proves.
It never runs in the Stop hook. Where it runs depends on the project's CI
(`.github/workflows/ci.yml`):

- **CI mutation on (`mutacao: true`):** a CI job mutates the lines of `src/Domain` and
  `src/Entity` added or changed by the push or PR and fails below `mutacao-msi` (default 90%).
  Uncovered mutants count against it: editing an old line that no test runs means writing its
  test. So, before saying a task that changed `src/Domain` or `src/Entity` is done, run
  `composer mutacao:diff` yourself and get it to the same threshold. Kill each escaped mutant
  with a test that asserts the behaviour. For an equivalent mutant, first remove the redundancy
  that makes it equivalent; if that is not possible, put `// @infection-ignore-all: <reason>` on
  the statement and tell the user (an ignore is the user's call to keep).
- **CI mutation off:** it runs only when the user says so, never on the agent's initiative:
  `composer mutacao:diff` (the change against the main branch) or
  `composer mutacao -- src/Domain/X/Service/Y.php src/Entity/Z.php` (whole files, about 2
  minutes a file; never all of `src/Domain` and `src/Entity` at once). End every turn that
  changed PHP code in `src/` with a one-line mutation recommendation, even when the answer is
  no: recommend it for dense rules (conditions, limits and boundaries, calculations, rounding,
  tax rules, numbering, state transitions, money), naming the files and the command; don't
  recommend it for wiring, renames, moved classes, templates, copy or config, and say why in a
  few words. When it runs, kill each escaped mutant with a test or explain why it is
  equivalent.

## Static analysis

PHPStan runs in two passes sharing `phpstan.base.neon` (Symfony, Doctrine and PHPUnit
extensions): `src/` at level 10 (`phpstan.dist.neon`) and `tests/` at level 6
(`phpstan.tests.neon`). `composer phpstan` runs both and must report no errors. Fix the cause
instead of adding baselines or ignore comments.

At level 10 untyped external data (API responses, payloads, request attributes) is `mixed`:
narrow it with an explicit `is_*()` check or a typed reader before use, never a blind cast.

**Architecture rules** (phpat, `tests/Arquitetura/RegrasDeArquitetura.php`, extending the kit's
`KitRigor\Arquitetura\RegrasSymfony`, registered in `phpstan.dist.neon`): only controllers depend
on controllers; entities and repositories do not depend on services or UseCases; UseCases are
`final`; controllers extend `AbstractController` and do not use repositories (except the ones in
`controllersComRepository()`); and each module of `src/Domain` depends only on the modules listed
for it in `modulos()`, plus `Comum`, which every module may use and which depends on none. A
violation is a PHPStan error on the line of the dependency. Fix the dependency (move a shared
class to `Domain/Comum`, go through a service); adding an exception or changing the module map is
the user's call. A new module goes into `modulos()` in the same change that creates it.

## Kit maintenance

This block, the `rigor-*` skills in `.agents/skills/` and `.claude/skills/` and the Stop hooks
come from `christopherpersaud/kit-rigor-symfony`. Don't edit them here: change the kit, then
`composer update christopherpersaud/kit-rigor-symfony && vendor/bin/rigor instalar` (skill
`rigor-instalar`).
