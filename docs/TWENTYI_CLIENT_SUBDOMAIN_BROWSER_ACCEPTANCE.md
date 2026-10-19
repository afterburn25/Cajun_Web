# Installed client subdomain acceptance

## Candidate and purpose

Branch `codex/twentyi-client-subdomain-browser` starts from `af956844690b1ed3be93a9a83574f06127fa8763`, tree `60c2e60139dceb22c358566569216e00db491248`, in [PR236](https://github.com/afterburn25/Cajun_Web/pull/236). It adds installed HTTP/browser coverage for the [client request workflow](TWENTYI_CLIENT_SUBDOMAIN_REQUESTS.md). The earlier candidate's local renderer checks did not establish this coverage.

The original focused run `39196497298` and full application run `39196525941` were confirmed queued on their first attempts at the start of this continuation. Preserve both runs and the original PR234, PR235 and PR216 acceptance. This new branch does not replace their results or publish a release.

## Verification boundary

The prepared workflow uses the existing complete upload package, actual PHP request handlers, native test database and real client/owner sessions. Its provider traffic is routed by the existing test-only connector to the loopback TLS fixture. No shipping transport or provider endpoint changes are part of this test increment.

The provider fixture accepts only the specifically configured synthetic package and the documented subdomain add body. A POST response does not change the separately configured website-folder observation. This allows the browser workflow to distinguish approval, an uncertain provider response, an absent name and a later observed name.

The callback follows every existing browser module. A distinct owner is created through the actual invitation/activation flow, retaining the original owner's authority and inherited history. Inventory reads respect the real profile cooldown; request timestamps, quotas and history are not reset to make tests pass. The caller requires named passing outcomes and the complete six-image PNG set before accepting the callback result.

Prepared scenarios cover invalid forms and inline retries, client withdrawal, owner decline and escaped notes, cross-client and support denial, duplicate requests, stale approval, exact signed approval, one uncertain provider attempt, separate absent and observed readbacks, private headers and safe client progress. Six real reads use the new owner's existing allowance. The fixture never treats a POST response as its own GET observation. Contact-provenance guards retain their existing pure coverage; this callback does not fabricate a contact-login route.

Required captures are desktop and mobile views of the pending client request, owner review and observed client result. Synthetic PNG bytes used by the portable artifact tests only check evidence handling; they cannot establish browser or visual acceptance.

## Evidence status

The [local verification receipt](evidence/client-subdomain-browser-local.json) binds sources, named reports and review records. Ninety new offline Handler checks pass. The unchanged directory-control, renewal, WordPress and StackCP contracts pass 19, 25, 17 and 21 checks respectively, with complete outcome objects identical to the parent. The portable acceptance helper passes 521 outcomes, retaining all 429 parent outcomes in order. Fourteen callback source and early-failure checks verify compilation, fixed output names, sanitized failed reports and preservation of existing evidence. Five changed Python files parse successfully.

One added offline command and two required reports bring the inventories to 169 native commands, 182 browser-phase reports and 226 final reports. The acceptance helper verifies 41 current source pins before four exact whole-file inverses to the parent. Existing workflow triggers, concurrency, transfer rules, reports and captures remain intact. Independent callback source review found no actionable defect within that scope.

Retained failures include the first module-level fixture helper, which broke an unchanged Handler-only extraction. Moving the helper into Handler restored all previous outcomes. An external patch locale mismatch and two missing-dependency source-probe launches were also corrected, with their original outputs retained. No failure was relabeled as installed acceptance.

The installed workflow has not run on this Windows workspace. Linux SQL, authenticated HTTP, Chromium execution and visual review of the six actual captures remain required. Application and foundation files match the parent exactly; no canonical installer refresh or package rebuild was needed. Published 1.25.0, the running site and all existing release gates are unchanged.
