# Symfony Rigor Kit

Three Agent Skills for traceable business specifications, golden-file tests and optional
quality checks in Symfony/PHP repositories: `rigor-instalar`, `rigor-spec`, `rigor-mutacao`.

## Requirements

The agent needs a real checkout, file editing, terminal execution and permission to run the
project's checks. PHP 8.4+, Composer 2.2+, Git and compatibility with PHPUnit 12/13 are required.
Installation needs network access. Mutation testing additionally needs Infection and a coverage
driver. Instructions work with Agent Skills-capable coding agents; automated hooks are optional
and harness-specific. A chat without repository/terminal access can only provide guidance.

## Installation and delivery

Agensi delivers the instructions through MCP. The library, autoload, PHP entrypoints and project
templates must be installed through Composer in the target repository, not executed from MCP
temporary files. Start with `rigor-instalar` and its included installation reference. Do not
bundle `vendor/`, install agent plugins automatically or assume a Stop hook is available.

This distribution requires kit version 0.4.x, which offers `--perfil` and `--agentes`.
Confirm those options with `php vendor/bin/rigor --help`. Update an older installation only
when compatible with the project; otherwise report the dependency constraint.

Use an essential profile for specs and golden tests, or deliberately adopt the full set of
templates. Preserve the application's architecture, bundler, test runner and analysis level.
Without a trusted working hook, run `php vendor/bin/rigor-verificar` explicitly before finishing
a task with changed checked files. The full CI check remains independent of the agent.

## Limits

Traceability checks IDs and references, not the correctness of assertions or complete scenario
coverage. Golden files and security-sensitive changes still require human review. No skill can
guarantee correct code, automatic hook support in every agent, or compatibility with every PHP
or Symfony version. Instructions and runtime messages may use Brazilian Portuguese, including
the specification syntax `Dado/quando/então`.

Source and MIT license: https://github.com/christopherpersaud/kit-rigor-symfony
