## Agent skills

### Issue tracker

Issues live as todos in the second-brain store, area Mealboard. See `docs/agents/issue-tracker.md`.

### Triage labels

The five canonical labels, applied as store tags. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: root `GLOSSARY.md` + `docs/adr/`, with `DECISIONS.md` as the pre-ADR log. See `docs/agents/domain.md`.

### Worktrees

A fresh worktree runs `scripts/worktree-setup.sh` first: it copies vendor, the built frontend and .env from the main checkout and links the pre-commit hook (`scripts/githooks/pre-commit`) into the shared `.git/hooks`.
