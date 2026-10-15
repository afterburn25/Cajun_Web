# Current development: dedicated hosting order execution

Codex owns complete20i P0. Published version remains [1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0), immutable source7190b582; main0b96f3d5 is unchanged. Unrelated roadmap work is paused. No new release or live deployment.

[PR138](https://github.com/afterburn25/Cajun_Web/pull/138), startingd3541693, now connects an explicitly approved order to the existing provider gateway/ledger and hosting attempt/result history through an internal application service. It rechecks current client consent, receipt/manual policy, owner/session and provider context before credentials and each send. Duplicate submissions cannot issue another request; an assigned ID remains pending. Native approval UI, automated catalog/configuration verification, result recovery and authenticated service confirmation remain unfinished. [Implemented boundary](docs/TWENTYI_ORDER_FULFILLMENT.md).

New native cases cover payment reversal, concurrent submissions, revocation, rollback, missing history and provider acknowledgement. Local syntax and retained pure hosting tests pass; new database/runtime matrices and packaged acceptance are pending. Canonical shipping inventory and private-path checks are updated. Manual configuration-review provenance does not claim provider-verified entitlement, and no route enables this service yet.

Parentf6c15855 passed all24 focused workflows, including21 pending cases within178 reconciliation checks in each of four combinations. Its full38790555173 has passed native/browser/recovery and is running Apache; downloaded report inspection remains outstanding. Parent162c has fully verified1652HTTP/289recovery/1763Apache and exact Windows/Linux packages. Those parents do not certify this descendant.

Continue native execution acceptance and the actual first-party fulfillment journey, then all remaining hosting/domain/VPS/support/interactive SSH workflows, independent review, normal integration and exact-main releases. Original full API reference/image remain requested once; no production request, charge, mail, SSH or deployment occurred.
