# CajunWebPro 1.8.0 release scope

Candidate: external-payment history and client statements/receipts. Source starts at main `2eee5719ccd85d6661b2677dbc8c1415853d9ec3`; implementation branch `codex/invoice-ledger`, contract `61b0b47`. Exact accepted source/CI/download hashes will be recorded after release gates pass.

Delivered scope: partial/full external USD receipt recording, owner-audited retained corrections, legacy paid balance import, remaining amounts for prepared requests/trials, client-owned invoice statements and printable receipts. No actual card charge, refund, recurring billing, tax calculation or customer mail. Sandbox observations never settle invoices.

Acceptance: local syntax/inventory/package checks and GitHub native MariaDB/concurrency/HTTP/Chromium/fresh and retained upgrade paths required. Existing v1.7.0 retained package is pinned by SHA-256 for update acceptance. All eight versioned assets require draft/public download verification against exact-main CI. No shared-host/live-merchant deployment claim.

See [setup and limitations](INVOICE_LEDGER.md). Devin PR #7 remains independent; portfolio integration is pending its readiness.
