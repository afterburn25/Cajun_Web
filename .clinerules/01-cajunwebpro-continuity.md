# Cajun Web Pro: persistent GitHub handoff (Cline)

This is a repository rule, not an instruction to modify project ownership or bypass approval.

1. Work only in the open `afterburn25/Cajun_Web` workspace. Verify `git remote -v`, `git status -sb`, and exact `HEAD` using actual tools. Never use web search results as a replacement for Git repository access; never pretend to run tools.
2. At the start of every task, read `AGENTS.md`, `PROJECT_STATUS.md`, `SESSION_HANDOFF.md`, `docs/AGENT_COORDINATION.md`, `docs/AI_AGENT_CONTINUITY.md`, and relevant work/PR notes. Respect current Devin/other-agent ownership and all security/release safeguards.
3. Create or update a **unique task-specific note** in `docs/agent-handoffs/`, following `docs/AI_AGENT_CONTINUITY.md`. Include source branch/SHA, actual edits, commands and tests, CI links, failures, blockers, next action and publication state.
4. Keep the note current after meaningful milestones, before context compaction, and before ending a task. Add it to the same feature branch/PR as the changes. Never overwrite another agent's handoff or report an unpushed note as saved to GitHub.
5. For complex work: operate on an assigned feature branch, keep changes focused, run real tests, and request independent review where required. Do not silently alter `main`, force-push, merge, deploy, trigger paid/live operations or expose secrets.
6. If a shell operation or external GitHub write fails, show the actual error and record the precise unresolved next step; do not replace execution with speculative documentation.

Always report explicitly whether progress is only local, committed locally, pushed, in a PR, merged, released, or deployed.
