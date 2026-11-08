# GitHub continuity protocol for local and cloud agents

This document extends, but does not supersede, [AGENTS.md](../AGENTS.md), [SESSION_HANDOFF.md](../SESSION_HANDOFF.md), [PROJECT_STATUS.md](../PROJECT_STATUS.md), [agent coordination](AGENT_COORDINATION.md), [session log](SESSION_LOG.md), [decisions](DECISIONS.md), and [release workflow](RELEASE_WORKFLOW.md).

**Goal:** Preserve actionable, source-linked project knowledge across Cline/Ollama, Codex and future agents without relying on any agent's chat memory. GitHub is the transport and audit history; it is not a substitute for verifying current source and workflow results.

## Authority and ownership

- Codex currently owns all 20i and non-20i implementation, integration and release coordination for now. [Issue #157](https://github.com/afterburn25/Cajun_Web/issues/157) and [PR #360](https://github.com/afterburn25/Cajun_Web/pull/360) provide the master queue and prepared handoff context. The active coordinator marker in [PR #340](https://github.com/afterburn25/Cajun_Web/pull/340) controls shared coordination; a newer active coordinator wins. A bounded worker must not take shared ownership or overlap work merely because it can access GitHub.
- Work only within an explicitly assigned, bounded scope on a separate, clearly named branch. Check for active PRs, concurrent writers, uncommitted work and frozen heads. Never reset, force-push, overwrite or merge another worker's branch.
- Branch work, passing local tests, passing PR tests, integrated `main`, published release and production deployment are **distinct states**. An agent must not promote one to another in documentation.
- Keep real charges, payment activation, customer messages, live provider changes, purchases, destructive operations and production deployment behind existing operation-specific authorization. Do not weaken payment, licensing, financing, authentication or CI controls.

## At the start of every task

1. Verify the workspace points to `afterburn25/Cajun_Web`; run real Git commands (`git status -sb`, `git remote -v`, `git rev-parse HEAD`). Do not substitute search results or simulated tool calls for filesystem access.
2. Read `AGENTS.md`, `PROJECT_STATUS.md`, `SESSION_HANDOFF.md`, `docs/AGENT_COORDINATION.md`, `docs/DEVIN_INTEGRATION_QUEUE.md`, the applicable roadmap and feature documentation, and this protocol. The integration queue keeps its historical filename; it is not a current ownership statement. Inspect relevant historical handoffs only as needed.
3. Fetch current GitHub state if authorized and connected: `main`, current PRs, exact branch heads, actual Actions runs, and release status. Prefer direct Git/CLI/API evidence. If offline, state the last-known commit and do not call it current.
4. Identify the task owner, target branch, source commit, boundaries, success criteria, and any other agent working in the same area. Avoid a parallel implementation if ownership is unclear.

## During execution

- Keep a **task-scoped handoff** at `docs/agent-handoffs/YYYY-MM-DD-<agent>-<short-topic>.md` on the **same branch as the work**. Use a unique name per task/agent; do not replace another agent's note. The handoff should follow the template below.
- Record meaningful checkpoints after source changes, verified tests, discoveries, decisions, CI failures, blockers, and before context compaction or task termination. Not every chat message or terminal command needs to be copied.
- Keep the task note close to its code changes and publish it in the same PR. If GitHub publication is not authorized or fails, say **local only**, give the exact unpushed branch/commit, and request approval rather than claiming that GitHub knows about the work.
- Append dated, evidence-backed highlights to `docs/SESSION_LOG.md` when authorized. Update shared `PROJECT_STATUS.md`, `SESSION_HANDOFF.md`, `docs/DECISIONS.md`, `docs/ROADMAP.md`, `CHANGELOG.md` or the integration queue when scope warrants and coordination permits; do not silently replace active maintainer handoffs or erase historical entries.
- Cross-link affected issues, PRs, commits, CI run/job URLs, and source paths. Capture failed attempts alongside successes. Never invent run IDs, screenshots, assertion counts, reviews, or "tests passed" from unexecuted commands.
- CI runs can be expensive and lengthy. Check whether the exact source already has required runs before dispatching. Preserve existing queue/cancellation rules in `AGENTS.md`.

## Before stopping, compacting, or handing off

1. Run `git status -sb` and `git diff --stat`; record the branch, working-tree state, commit SHA(s), and precise modified paths.
2. Record test commands, environment, counts/results, evidence URLs and unrun/skipped tests explicitly. A failure is not success merely because a later run passed a different source.
3. Explain what is implemented, what remains unverified, exact blockers, unresolved risks, the next safe action and any approval required.
4. Update the task-scoped note; include it in a commit alongside work when authorized. Push only to the approved feature branch, under current GitHub permissions; never assume automatic publishing or merge.
5. In the final user report, distinguish **saved locally**, **committed locally**, **pushed to GitHub**, **PR open**, **merged**, **main tested**, **released** and **deployed**. Include links when real.

## Starting a new agent or session

Read this protocol and the existing canonical documents, then inspect the latest relevant task note(s) and associated PR(s). Reconcile the note's recorded SHA with live Git and CI. Treat notes from a stale or unmerged branch as historical claims rather than current `main` status. Continue from the newest **verified** state; preserve other agents' branches, evidence and ownership.

## Task-scoped handoff template

```markdown
# <Task> - <agent> - YYYY-MM-DD
- Repository / branch / starting SHA / last verified SHA:
- Owner assignment and scope:
- Status: planning | implementing | tested-local | awaiting-CI | awaiting-review | PR-open | merged | verified-main | released
- Summary of verified changes:
- Modified files and why:
- Commands and results actually observed (include OS/runtime/test database):
- GitHub PR / CI run / artifact links and source SHA:
- Failures and rejected approaches retained:
- Important decisions and security/data constraints:
- Remaining risks / blocked gates / required approvals:
- Exact next action for another agent:
- Publication: local only | committed | pushed | PR | main (with URL/SHA):
```

## Privacy and security

GitHub repository content is visible to everyone when the repository is public. Never commit API tokens, passwords, private keys, production environment variables, payment information, customer records, private logs, personal identifiers, internal correspondence or confidential commercial information. Sanitize logs and screenshots before referencing them. Keep sensitive artifacts in approved private storage and link only safe, nonsecret evidence.

**This protocol is documentation, not a background service.** Cline or other agents must actually execute the updates and GitHub commit/push operations for cross-session persistence to occur.
