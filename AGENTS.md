# AGENTS.md

## Rules Source

Rules for this repository are managed in local `./ai-rules`.
This directory is expected to be private/local and not tracked by this repository.

If `./ai-rules` is missing, ask the user for instructions and do not assume external rule sources.

## Shell Requirement

- Never use PowerShell.
- On Windows, use WSL2 for all shell commands.
- On Linux/MacOS, use the native system shell.

## Rule Priority

Apply rules in this order (highest to lowest):

1. `./ai-rules/AGENTS.md`
2. Matching profile files in `./ai-rules/profiles/*/AGENTS.md`
3. Subrepo-local `AGENTS.md` files for the current subtree (if present)
4. This file (bootstrap/routing only)

## Project Context

- Use `./docs/tech-stack.md` as the source of truth for project tech stack and tooling assumptions.

## Conflict Resolution

- `./ai-rules/AGENTS.md` is authoritative.
- Profile files in `./ai-rules/profiles/` extend global rules and are authoritative in their domain.
- Subrepo-local `AGENTS.md` may narrow behavior for files in that subtree, but must not override higher-priority rules.
