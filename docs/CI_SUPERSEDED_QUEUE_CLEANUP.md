# Superseded component queue cleanup

## Verified bounded pass — 2026-10-19, after API recovery

Read-only verification after API recovery confirms all 327 acknowledged cancellations, covering 1,297 jobs with no execution evidence. There are zero outstanding terminal verifications, 31 preserved candidates and 139 untouched candidates. The bounded pass is verified; the original 497-candidate plan is not complete. No cancellation request was repeated. The [new completion receipt](evidence/ci-superseded-queue-completion.json), SHA256 `222646256b082e916fc52b33ddb13c9456e86621f63a52190d9f6abb0b56172b`, sets `pass_review_complete: true` and `original_plan_complete: false`. It preserves the [first partial receipt](evidence/ci-superseded-queue-cleanup.json) byte-for-byte and retains all original operation history.

One capacity read after05:33UTC established sufficient API allowance;309 sequential terminal GETs then resolved the103 pending requests, with complete job/artifact pagination, no new API errors and no mutations or retries. Root locally compared all original IDs and request/error evidence, then verified the103 newly retained terminal snapshots covering409 jobs. Both singled-out runs39132937949 and39111082537 are now terminally cancelled with unexecuted jobs and no artifacts. The missing immediate post-request snapshot for39132937949 remains a permanent limitation; later evidence does not fill it.

All verifier processes exited. No further cleanup is in progress, and139 candidates remain untouched. Original application/capture/release tests still require their own actual results. The earlier checkpoint and continuation instructions below are retained as historical evidence; the read-only terminal follow-up they requested is now complete.

Recorded 2026-10-19. Main starts at `8f734de11031938eff81a02744d5195ecdcab17c`; documentation branch `codex/ci-superseded-queue-cleanup`. This operation reduces obsolete GitHub testing work while preserving current-source coverage and release acceptance. It changes no application or workflow source.

## Reason and scope

GitHub reported about 2,500 queued runs while active component jobs still targeted earlier Devin commits. A bounded inventory found 497 superseded automatic component runs across PR194, PR195, PR196, PR197, PR198 and PR203, each with a newer same-workflow run for that PR's current head. The independent source review confirmed identical old/replacement workflow bytes for all 497 pairs across 28 explicitly allowed component workflows. Old commits are ancestors of the current heads.

All 18 full-application pairs in that inventory were excluded. Current-head runs, replacements, manual dispatches, main pushes, application/capture/release/package/diagnostic work and any original run explicitly named in retained documentation or PR discussion are outside this cleanup. A separate reference scan found 980 recorded run IDs and no candidate intersections. Source supersession does not establish acceptance of the replacement.

The owner's standing authorization covers GitHub testing, integration and faster parallel progress. Treating obsolete, unexecuted component work as routine queue maintenance is the agent's operational judgment, not a new owner instruction to cancel arbitrary tests. Earlier documentation saying no runs were cancelled describes those earlier changes; this separately recorded cleanup does not rewrite that history.

## Actual checkpoint

The [partial receipt](evidence/ci-superseded-queue-cleanup.json) records 497 original candidates and 358 processed candidates:

| Observation | Count |
| --- | ---: |
| Acknowledged cancellation requests | 327 |
| Independently verified terminal cancellations | 224 |
| Unexecuted jobs in those verified cancellations | 888 |
| Requests awaiting terminal verification | 103 |
| Preserved candidates with existing work or results | 31 |
| Untouched candidates deferred from this pass | 139 |

The 224 verified and 103 unresolved requests are subdivisions of the327 acknowledgements. The receipt explicitly sets both original-plan completion and pass-review completion to false. Root rechecked all1,370 retained attempt-file hashes and224 terminal observations locally. These are evidence checks, not application tests or new GitHub reads.

A later independent GET for run39132937949 explicitly returned HTTP403 API rate limit exceeded at05:10:47UTC. Its exact failure is retained in the receipt's supplemental evidence. No jobs/artifact read or retry followed that response. All cleanup and API verifier processes stopped. The two earlier read failures remain unconfirmed individually; the later explicit limit does not retrospectively establish their cause.

## Per-run checks

The frozen plans bind exact candidate/replacement IDs, PR number, branch, repository, source heads and workflow identity. Before every cancellation, the operator freshly reads the PR, both runs, all-attempt paginated jobs and artifacts. It proceeds only for an original attempt-1 automatic PR run that remains queued with no conclusion, no artifacts, and an exclusively matching current PR. Its replacement must still target the exact current head and be pending or successful.

Every original job must be queued, have no conclusion or completion, no assigned runner and no steps. GitHub returned enqueue-time `started_at` values even for unassigned queued jobs; that timestamp alone is not treated as execution. A completed/active job, artifact, changed head, missing replacement, pagination ambiguity or preflight API error prevents cancellation. Thirty finite refusal probes passed on the unchanged refusal function; the final helper and its subsequent single-read plan binding passed independent review. Those are queue-policy checks, not application tests.

Only ordinary cancellation is used, sequentially. No force-cancellation, run deletion, repeated cancellation, dispatch, branch modification or replacement-test suppression is performed. API evidence before and after requests is retained where collection succeeded; the one missing immediate post-request snapshot is explicitly recorded below. A cancellation command acknowledges a request; the final result must be observed separately.

GitHub has no conditional cancellation operation that guarantees the run is still unstarted when the request arrives. The helper therefore also reads post-request jobs and artifacts and stops on runner assignment, steps or artifacts that reveal a scheduling race. This residual race is retained as a limitation rather than claiming an atomic guarantee.

## Evidence and continuation

The source review, four original frozen plans, a separately reviewed continuation, finite probes, original review findings and corrected helper are retained under workspace `work/queue-review-2026-10-18`. The receipt records actual requests, skips, untouched candidates, observed terminal outcomes and per-run evidence hashes. The initial helper review corrected removable assertions, incomplete post-request observation and a double-read plan-digest binding before any cancellation occurred.

The third batch stopped before requesting run39132240837 when its old-run GET failed. Durable evidence showed only a preflight error, with no request timestamp, before snapshot or cancellation result. A new 72-candidate plan contained exactly that original plan's previously unrequested suffix; it excluded all 56 prior requests. Independent review verified those facts before continuation. Absence of `request.json` alone would not prove that an arbitrary interrupted command made no request.

That continuation stopped after 46 acknowledged cancellations because the post-request PR read for run39132937949 failed. Its before snapshot and successful cancellation acknowledgement remain; no immediate post snapshot was obtained. A later terminal review is still required, without reconstructing the missing snapshot or repeating cancellation. Root ended the maintenance pass and retained the other 139 candidates unchanged. Both API failure causes remain unconfirmed; allowance observations varied and are not a diagnosis.

After API recovery, start read-only terminal verification with39132937949 and39111082537. The latter is already cancelled at run level, but its retained immediate snapshot still had three queued jobs. Then verify the other101 unresolved requests. Preserve the first partial receipts and append review corrections. Do not restart any cancellation helper or execute batch4 to obtain those results. This checkpoint contains no refreshed result for the original application/capture gates after the rate-limit response.

Cancelled obsolete runs provide no acceptance credit. Preserve all original application and capture runs, including the catalog f67 matrix and PR220's b7 run39137551561. Continue inspecting those exact runs, review corrected actual captures, then complete normal integration, exact-main acceptance and fresh release-download verification. Published1.25.0 and its assets remain immutable. No provider request, payment, customer mail, SSH or live deployment is part of this operation.
