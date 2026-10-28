# Financing email sender correction

28 October 2026. Focused correction based on integration `03c4f606b5e536c7173ebf35200a40715228e527`; the designated integration writer retains that branch. This change is pending integration, hosted acceptance and review, not merged into main or released.

## Original failure

Application run `40684017215`, Apache job `122816732929`, passes 2,747 protection/root/subfolder/installer checks and the 28/19 published-baseline guards. The actual populated published-1.24 root/rewrite case completes 158 inner checks, then fails the existing no-PHP-diagnostic guard (160 of 161 outer checks pass). The other three populated upgrade modes are not reached.

The sealed log contains five warnings for undefined variable `$mail_drafts_account` in `site/admin/views/studio/licenses.php:59`. When an assigned Send account exists, the view invokes that undefined variable as a callable. Its catch retains the page but leaves the financing sender choices empty. The correction calls the existing `mail_drafts_account()` function. Current mailbox permissions and revision checks remain in that service; the diagnostic guard remains unchanged.

Artifact `12818068831` matches GitHub SHA256 `a91988d261199f74b433449de55a166c10124b1965452fa0f6f77379e2124ad7`. All 120 partition members match their sealed hashes/CRCs, identity and input receipt. Checkout `d8e56c2c863cb723267da4bbbc6f7321f4580dd5` has the exact complete tree of integration03c. The two upgraded-client screenshots were inspected; this does not make the failed job accepted.

## Regression and local evidence

Installed financing acceptance now prepares a sending account and a read-only account through the existing account/grant services. It verifies the real finance-email form, current sender reference, exclusion and rejection of the read-only account, invalid CSRF, stale revision, exactly one authorized local draft, recipient/authenticated portal link, and unchanged agreement terms/signature state. No mail submission or provider call is performed.

Independent execution of the exact new regression function uses real owner sessions, HTTP and native PDO on PHP 8.2.35/8.4.25 × MariaDB 10.11.19/MySQL 8.4.13. Each fresh 97-table clone retains original column semantics/rows and enabled foreign keys; only independent new rate counters start empty. The original warning/missing-form failure reproduces on all four runtimes. The corrected view passes 32 mail assertions plus four login checks, 36 total, with no PHP diagnostics. Nine financing/payment/license/mail-authority snapshots remain unchanged; exactly one new local draft is retained per fixture.

Local mailbox records use opaque, unopened synthetic credential sentinels. The committed CI fixture instead uses real credential encryption via the account service; its syntax is checked, but that credential-creation fixture and a complete hosted installed run are not claimed locally. No filesystem security check was bypassed. Disposable SQL servers were verified and gracefully shut down after testing.

Source hashes:

- Original view: `a17c69d3906ac8e613e9a85f514356f5a85329590d67a3817235ad4277c7e48a`.
- Corrected view: `f2e3d206b807b410e24ad7f2394ef4fd537192d97a445c6ec975a29ab1d786cc`.
- Executed regression file: `910e3b40f902ccce735c05fb7862fa0725183a4033ca1a7619064b6fe9d5e295`.
- Installed fixture: `3793ae1299f7ab92aae57060517a65df1c290adfba264a0b23a4e8727bf4264a`.

Both touched PHP files pass lint on PHP8.2/8.4; Python syntax and the existing financing source contract pass. `python tools/build.py --refresh` completes successfully, including canonical inventory, shipping syntax and pinned dependency/mirror checks. No release is built from these uncommitted bytes.

## Integration boundary

Preserve the original failed run and require fresh combined Apache acceptance, including all four populated upgrade URL/rewrite modes. PR277's historical payment prerequisite correction and PR278's mailbox port correction are separate open work. Feature2c fixes the previously diagnosed readiness import but retains the trial-control regression. Full installed runtime matrix, actual sandbox, populated published1.25-to-next-version upgrade, independent approval, exact-main acceptance and correctly versioned release remain open. No actual gateway action, customer email or deployment is authorized by this evidence.
