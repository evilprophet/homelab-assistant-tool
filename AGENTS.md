# AGENTS.md

Bootstrap and routing only. Durable shared rules live in local `./ai-rules`, which is expected to be private/local and not tracked by this repository. This root file holds only routing plus project context. Keep it minimal.

## Shell Requirement

- On Windows, use WSL2 for all shell commands.
- On Linux/MacOS, use the native system shell.

## Rule Loading Order

Rules are layered from most to least specific. On conflict, the more specific file wins:

1. Subtree-local `AGENTS.md` for the files being edited, if present.
2. This root `AGENTS.md` - local routing and project context.
3. Rules shipped by the project's own dependencies, when it has any.
4. `./ai-rules/contexts/<context>/AGENTS.md` - the context this project is worked in.
5. Matching language/task profiles in `./ai-rules/profiles/*/AGENTS.md`.
6. `./ai-rules/AGENTS.md` - global engineering baseline.

If `./ai-rules` is missing, ask the user for instructions and do not assume another rules source.

## Project Context

- Use `./docs/tech-stack.md` as the source of truth for project tech stack and tooling assumptions.
