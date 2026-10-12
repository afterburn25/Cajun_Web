# Session record template

Copy a concise entry into `SESSION_LOG.md`, update current status/handoff, and link supporting evidence. Do not overwrite previous log entries.

## YYYY-MM-DD — Meaningful task title

**Request / approved scope:**

**Repository / branch / starting commit:**

**Starting facts and unresolved issues:**

**Actions and files changed:** Include code, configuration/schema, assets, docs, external writes, and any no-change investigation findings.

**Decision changes:** Record decision IDs and affected roadmap items, or state none.

**Tests actually executed:** Commands/environment or procedure; named cases/results; exact artifact/commit; whether native, mocked/offline, or live. Do not reuse historical counts as current results.

**Results and limitations:** What now works and what remains unverified. Separate source, package, local tests, and target-host state.

**Data preservation / migration / rollback:** Note compatibility, protected files, backup/restore evidence, or not applicable.

**Commit / PR / artifact references:** For the containing commit, the Git history supplies its own SHA; avoid writing a guessed self-referential hash.

**Open blockers and exact next action:**

**Documentation synchronized:** Status, handoff, roadmap, changelog, decisions, architecture, branding, deployment/testing notes as relevant.

Never include secrets, customer documents, private server credentials, or speculative 'fixed/deployed' claims.
