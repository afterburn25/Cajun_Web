# PHP version changes — implementation in progress

The owner requires native 20i controls for administrators and authorized clients. Work starts from main `e397d9a6e293850e8dff61a593e3ba39ee178400` on `codex/twentyi-php-version-controls`. The separately preserved catalog and checkout candidates retain their original tests and release gates. Published 1.25.0 is unchanged.

## Implemented request boundary

The retained [owner reference](reference/20i-owner-api-34e713-redacted.txt), lines 2491–2498, documents `POST /package/{packageId}/web/phpVersion` with one string field, `value`. `PhpVersionChange` validates an allocated positive integer package ID, bounded UTF-8 text and that exact canonical JSON body. It joins the request register, dedicated builder and fixed transport. Other methods, alternate endpoints, extra fields and ambiguous JSON are refused. The separate scalar reader continues to use a bodyless GET.

This validation does not establish that a version is available for a particular website. It does not authorize a user. Generic owner approval remains denied. No new route or form can call this operation in this increment. The existing operation gateway admits one attempt and retains uncertain outcomes; a repeated request cannot silently send again. A successful HTTP response does not establish that PHP changed because the setter's acknowledgement is unspecified. Completion needs a separate authorized scalar read.

## Remaining application work

The available-version endpoint is documented at lines 2455–2461, but its response container, field names and value mapping are absent. A separate, fixed read-only diagnostic is being prepared to inspect those details. It may read the inventory once to choose one genuine package and then read that package's options once; it must never change a provider setting. Its output is bounded metadata with resource identifiers and private values withheld. Preparation is not a live observation.

The complete journey remains: explicit owner delegation, authorized options check, selection and review, deliberate single send, separate current-version check and retained request history. Client change permission must be distinct from the existing saved-value sharing permission. A real primary-client session must retain its own actor identity; contact and support sessions cannot inherit owner authority. Assignment, credential, permission or authentication changes invalidate old reviews. Unresolved sends block another change; recovery may restore proven local records but cannot resend.

Native delegation, choices storage and projection, request/terminal/readback records, owner and client forms, supported-runtime database/browser/concurrency tests, upgrade/recovery acceptance and release verification are still required. There is no claim that PHP controls or full 20i integration are complete.

## Verification checkpoint

On PHP 8.4.25, the operation suite passes 360 named offline checks in both normal and minimal configuration. This includes the added PHP wire, fixed-transport admission, uncertain-result classification and gateway replay cases. Existing Force SSL (76), directory controls (78), and PHP scalar-reader (101) checks also pass in both configurations. Fixed transport policy checks pass. All use synthetic inputs; no provider mutation or native client workflow was executed.

The first scalar-reader regression exposed a missing request-class dependency in standalone wire validation and an obsolete test that rejected the now documented setter. Wire validation now compares validated canonical JSON directly without constructing an operation object. The test retains malformed-setter refusal, adds exact setter admission, and verifies the observation remains GET-only. The original failed local output remains outside the repository; corrected runs pass.

Canonical `tools/build.py --refresh` passed: shipping copies and installer dependencies agree, and no release was built from uncommitted files. Documentation checks pass for 272 Markdown files and 1,859 local links; touched PHP syntax and whitespace checks pass. First PHP 8.2/8.4 GitHub checks and independent source review are tracked with the implementation checkpoint. Those checks do not substitute for the remaining application workflow.
