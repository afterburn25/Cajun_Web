# CajunWebPro session handoff

Updated 2026-10-16 15:15 UTC. **Published 1.24.0 is unchanged; 1.25.0 and complete 20i remain unfinished.**

Codex and its three agents own 20i, provider support/SSH, shared integration and releases. Devin owns remaining non-20i work, including licensing, under [assignment #157](https://github.com/afterburn25/Cajun_Web/issues/157). The assignment is on main at [DEVIN_START_HERE.md](DEVIN_START_HERE.md). Devin acknowledged it and opened licensing159, tickets158 and project progress160; proposals remain subject to independent acceptance.

## Current 20i source

Cloud assignment authority is now integrated on [2a86704f](https://github.com/afterburn25/Cajun_Web/commit/2a86704fa0c7775206cf9ac93e27d0457be307fa), branch `codex/twentyi-cloud-assignment-integration`, tree `32f1058c4f13006ba6977216fea20dce7c815db9`, parent819c3ea3. It adds protected owner approval and client sharing boundaries while preserving registration and StackCP histories. Legacy unknown-account facts are withheld; authenticated client identity and active status are checked again inside the shared lock. [Exact feature guide](https://github.com/afterburn25/Cajun_Web/blob/2a86704fa0c7775206cf9ac93e27d0457be307fa/docs/TWENTYI_CLOUD_ASSIGNMENT_AUTHORITY.md).

The candidate also fixes the real legacy-inventory readiness defect exposed by original full819c: optional StackCP history no longer queries an absent operation column on legitimate inventory versions1/2/3. Retained history and damaged current/future storage still require recovery; GET does not repair storage. All original upgrade assertions remain, with15 added checks.

Local syntax, portable/synthetic/helper checks pass. Canonical refresh and staged verification prove all2,884 shipping files and2,883 installer hashes match exact Git bytes (proof5c4c1286). First original [focused matrix38921917647](https://github.com/afterburn25/Cajun_Web/actions/runs/38921917647), attempt1 push, is running for PHP8.2/8.4 and MariaDB10.11/MySQL8.4, requiring12 report identities per environment. Windows package construction is running separately. Native, HTTP/browser, images, combined packages, current-main and release acceptance are not yet established for this source.

Original full819c run38917429508 retains its actual legacy-upgrade native failure and skipped browser execution (proof8e973156); build/recovery evidence remains scoped to its own source. Original9779 full run38909577278 passed native and is still executing HTTP/Chromium at the last observed checkpoint. Keep both original runs and all retained failed evidence. [Detailed checkpoint](docs/TWENTYI_CURRENT_CHECKPOINT.md).

## Devin review and next actions

Independent [licensing159 review](https://github.com/afterburn25/Cajun_Web/pull/159#issuecomment-6218434878) at exact e3d62770 found two remaining behavior gaps: a sole unrelated/admin-created/revoked NULL-invoice license can be claimed by a new purchase, and genuine populated unmarked pre-master databases still cannot migrate. Original CI also failed stale installer hashes before native/browser execution. Master-customer exclusions and unknown-version refusal are present in source. Devin received actionable fixes; this is not accepted licensing behavior. [Review history](docs/LICENSING_INTEGRATION_REVIEW.md).

Next: inspect the actual focused archives, complete exact-source Windows/Linux and full native/browser/Apache/recovery acceptance, inspect actual desktop/mobile images, and integrate histories sequentially before exact-main acceptance and a versioned release. Full hosting lifecycle, domain commerce, VPS/cloud controls, support sessions and SSH remain roadmap work. This main commit changes documentation only; candidate runtime remains on its own branch. No deployment, provider operation, charge, customer mail, SSH session or new release occurs.

[Previous immutable checkpoint](https://github.com/afterburn25/Cajun_Web/blob/595ac9c3551952552cfb103601f6e4dd5f818eb4/SESSION_HANDOFF.md).
