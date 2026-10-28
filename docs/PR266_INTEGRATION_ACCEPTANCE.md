# PR266 licensing integration acceptance

## 2026-10-28 correction candidate

Owner assigned Codex a separate integration, QA and security pass for PR #266. Work is on `codex/licensing-integration-review`; Devin retains the feature branch and final integration/release coordination. Main observed at `fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827`; refreshed feature parent is `6045b801fc7653125da5d11c0a8cd956ce58a26c`. Latest published release remains [v1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0), source `b79c67110829126916e3a65e616191542448bccc`.

### Original failures and compatible repairs

- [Focused financing run 40647366526](https://github.com/afterburn25/Cajun_Web/actions/runs/40647366526), exact source `73d4c37be28144e499f4fb4b74a9381cf15194d1`, fails at active project-release download with HTTP404. Both unpaid-initial and zero-down agreements used project1; the project delivery guard checks every financing binding on that project. Devin's `6045b801` assigns the unpaid-initial fixture its own project; this branch preserves that repair without replacing application authorization or transaction behavior.
- Added installed assertions that both agreements use distinct projects, each schedule matches its offer project, and each license belongs to that client. An unpaid-initial project release must remain blocked while the independent active project's bytes are available; the initial license must retain its payment denial.
- [Document-roots original run 40644435011](https://github.com/afterburn25/Cajun_Web/actions/runs/40644435011) failed before checkout when host port33306 was occupied. Its workflow now requests a dynamically assigned service port and passes that actual mapping into the existing native-test container. The maintained PHP8.2/8.4 × MariaDB10.11/MySQL8.4 matrix and assertions remain intact.
- The focused financing workflow now also selects a companion PR targeting the licensing feature branch, filtered to the fixture/cases/workflow paths. Existing runs and protected acceptance workflows are preserved.

### Verification and limits

Local PHP8.4.27: fixture syntax, financing policy, financing source contract and production-payment gate contract pass. Python3.12 AST parsing of the changed browser cases passes. Actionlint1.7.12 validates both changed workflows with optional external shellcheck/pyflakes disabled. Repository documentation, generated inventory and whitespace checks are recorded with the correction commit. These are local checks, not installed database/browser acceptance or independent approval.

At parent `73d4c37b`, [published-v1.25 upgrade gate](https://github.com/afterburn25/Cajun_Web/actions/runs/40647375642), repository checks, software/finance source contracts and software payment policy contracts passed. Full application acceptance remained queued and focused financing failed. Those parent results do not certify this correction or the eventual merge result.

Remaining release gates: exact candidate native/HTTP/Chromium and Apache root/subfolder/rewrite matrices, all populated upgrade/recovery and package certification, actual non-secret Authorize.Net sandbox evidence for software and financing including replacement/reconciliation, payment-page/CSP/PCI and finance-agreement review, required independent security review, reviewed integration and exact-main acceptance. Real sandbox workflows require the existing approved sandbox secret configuration; no credentials or tokens belong in source or reports. Production payments and deployment require owner approval. No merge, release, deployment, live charge or independent approval is claimed by this record.
