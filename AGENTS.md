# AGENTS.md

Bootstrap and routing only. Shared workspace rules live outside this public repository when it is checked out inside HomeLab Tools. This root file holds only routing plus project context. Keep it minimal.

## Shell Requirement

- On Windows, use WSL2 for all shell commands.
- On Linux/macOS, use the native system shell.

## Rule Loading Order

Rules are layered from most to least specific. On conflict, the more specific file wins:

1. Subtree-local `AGENTS.md` for the files being edited, if present.
2. This root `AGENTS.md` - local routing and project context.
3. Rules shipped by the project's own dependencies, when it has any.
4. `../AGENTS.md` - HomeLab Tools workspace routing and context, when present.
5. `../ai-rules/contexts/homelab-tools/AGENTS.md` - HomeLab Tools workspace map, when present.
6. Matching language/task profiles in `../ai-rules/profiles/*/AGENTS.md`, when present.
7. `../ai-rules/AGENTS.md` - global engineering baseline, when present.

When the parent `ai-rules/` is absent, follow this file and the project documentation. Do not assume another shared rules source.

## Project Context

- Use `./docs/tech-stack.md` as the source of truth for project tech stack and tooling assumptions.
