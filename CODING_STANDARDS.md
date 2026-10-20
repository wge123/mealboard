# Coding standards

Read at review time. Formatting is Pint's job (pre-commit); these are the judgement calls no tool checks.

## Name things in the glossary's words

Identifiers, docblocks, log lines and user-facing copy use the terms in `GLOSSARY.md`. A word the glossary lists under _Avoid_ for a concept is a finding wherever new code uses it for that concept: `refusalsBeforeRestructuring`, not `refusalsBeforeShaping`. Older names stay until something touches them.

## Fail loud; a fallback lives only at a boundary

Code we own throws or refuses with its cause. Where an outside system (a recipe site, the Claude CLI, YouTube, Walmart) fails in an expected way and the code turns that into a normal outcome (a refusal, a skipped site), the turn keeps the cause and logs it at that spot, and the outcome never feeds a write as if it had succeeded.

## One honest door to the outside

Each outside system is reached through one class that owns its client settings: recipe pages and feeds through the page fetcher, the CLI through its wrapper. A new caller reuses that class rather than opening its own HTTP client, so the User-Agent, timeouts and failure rules stay single-sourced and true for every caller (ADR 0004).
