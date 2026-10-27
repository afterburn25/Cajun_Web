# Subdomain request activity

## Candidate scope — 2026-10-19

Branch `codex/twentyi-subdomain-notifications` starts at `d1493f77f4c32d49b62256df1645dc60b9768cb9`, the frozen [PR237 browser candidate](https://github.com/afterburn25/Cajun_Web/pull/237). It adds subdomain request notices to the existing workspace activity center and unread bell. This branch depends on the [client request workflow](TWENTYI_CLIENT_SUBDOMAIN_REQUESTS.md); it is not a released or deployed feature. Main was `dcd6966c4e0c209d15f9d88c8832ef601765c336` at the latest local read.

Owners see a client's request and withdrawal. The original primary client sees owner review or decline, a retained send outcome, and each independent hosting check. Each notice opens the exact request. Review and an uncertain send never imply success. A matching check confirms only that the requested hostname was observed at that time; DNS, certificates and website availability remain separate.

The existing **All updates**, **Unread**, individual read/unread and **Mark visible updates read** controls apply to both project and subdomain updates. Opening a notice does not mark it read. Stable keys prevent duplicate notices on replay; read state remains independent for each person. Thirty-item pages and the existing 99+ display cap remain. No new database table or notification migration is required.

## Authority and failure behavior

Every count, list, open and mark revalidates the current identity. Provider notices require an enabled owner or the exact original primary client with its current request authority. Nonowner staff retain their existing project activity without receiving owner provider notices. Contact markers are captured before identity checks, and contacts receive no provider notices. Support access cannot view or change another person's activity.

Client provider notices disappear when the original client authentication, hosting assignment or provider credential binding changes. A captured key cannot transfer access to a new client. Owner intake history remains available for review under the existing owner authority. Notices expose fixed copy and the hostname, without private paths, notes, credentials, provider responses or approval proofs.

Reads share one account mutex and database transaction; they never initialize storage, contact 20i or write read state. Mixed read/unread actions validate every selected project/provider key before writing any row. A broken established provider history makes the count unavailable even when project activity already reaches the cap. The activity center can still show project updates with an explicit partial-results notice. Provider navigation and marking refuse unavailable history; existing project-only navigation and marking remain usable. Clean, never-prepared provider storage contributes no notices.

History validation deliberately retains the existing full-history and reciprocal-origin checks. Their repeated graph validation under the shared mutex is a scaling concern; native timing and larger-history performance acceptance remain pending. No cache or history truncation weakens these checks.

## Verification and remaining work

The [local evidence receipt](evidence/subdomain-notifications-local.json) records exact sources, named checks, reviews and limitations. The new projection and merge tests exercise actual PHP functions with explicitly modeled persistence where needed. They are separate from SQL and installed-browser acceptance.

The existing native subdomain producer now covers mixed project/provider pages, the count cap, independent read state, atomic refusal, authority changes, corrupt history and missing terminal records. The installed browser callback now covers real activity links and forms, CSRF and foreign-account refusal, read-state replay and truthful result wording. All original fixture checks, provider request counts and six required captures remain. These added native/browser assertions have only syntax/source verification locally.

Four mandatory pure test commands and reports are added to full acceptance, preserving every previous command and report through exact predecessor checks. Candidate native/HTTP/Chromium results, capture review, combined integration, exact-main acceptance and release/download verification are still required. Preserve the first original runs for PR237, PR220 and the catalog candidate; queue status alone does not authorize rerunning them. Published 1.25.0 remains immutable. Devin retains the non-20i assignment. No live provider, payment, mail, SSH or deployment operation is part of this increment.
