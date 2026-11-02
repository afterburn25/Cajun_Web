# Primary PR317 hosting UI/workspace supplemental source review

**Result:** All 51 paths previously marked `inventoried_not_reviewed` in the hosting receipt have now had their changed source clauses reviewed at the depths recorded below. One P3 navigation integration defect is confirmed. No additional authority or commerce blocker was established by this bounded supplement. This is not whole-PR, release, browser, or live-provider approval.

- Baseline: `1c44ca120e7768998fafa720b3e1c4c85990c330`.
- Frozen target: `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`.
- Exact source checkout: `/workspace/scratch/8cc2b4f257ab/Cajun_Web_primary_review`; HEAD exact and working tree clean before and after.
- Reviewer: `/root/github_status_review/primary_finance_review`.
- Original hosting and finance receipts remain intact. FIN-01 and FIN-02 are not cleared here.

## Confirmed finding: HOST-UI-01 — P3 navigation context is lost

Some hosting handoff and cloud action returns still open overview after the section split.

The new **Review client access** link renders `hosting_fulfillment_view()` assignment URLs built as `provider-settings#provider-inventory` or `provider-settings#provider-package-ID`. Neither URL contains `section=services`. The section selector defaults to overview, whose rendering branch excludes inventory and assignment forms. The new services view also lacks those fragment IDs. A current owner therefore reaches the overview instead of the intended client-access review location.

The same section split affects the cloud preparation, refresh and link/relink/unlink returns in `site/admin/controllers/providers.php` at target lines 84, 100 and 128: the focused cloud-assignment query is retained when present, but the normal path passes an empty query and opens overview. The focused route itself remains intact.

| Exact source | Target clause |
| --- | --- |
| `site/app/hosting/fulfillment_views.php` (78, 84) | Assignment URL has no section; fragment is provider-inventory or provider-package-ID. |
| `site/includes/layout/hosting_fulfillment.php` (26) | New Review client access link renders the saved assignment URL. |
| `site/app/providers/workspace.php` (8, 11) | Without section or cloud-assignment focus, selected section is overview. |
| `site/admin/views/providers.php` (37, 52) | Overview branch omits services controls; services child is included only for that selected section. |
| `site/admin/views/provider_workspace/services.php` (6, 32) | Service inventory and package cards do not define either fragment target. |
| `site/admin/controllers/providers.php` (84, 100, 128) | Cloud preparation, refresh and assignment return preserves focus when present, otherwise passes empty query and loses ordinary cloud section. |

**Impact:** Owner loses the intended review/action context and must choose the workspace again. No provider write, access grant, payment, or cross-client exposure is performed by these URLs; no authority bypass is claimed.

**Required correction invariants:**

- Client-access handoff must explicitly select services and use a fragment that exists on the inventory or exact package card; retain the current owner gate.
- Ordinary cloud action returns must select cloud while preserving the existing focused cloud-assignment route when explicitly present.
- Navigation must remain fixed local URLs with allowlisted section names; selecting a section must not become provider/assignment authority.

No source or browser probe was needed to establish the emitted URL, default section and missing target relationship. No application request or provider action was executed.

## Authority and commerce traces checked

| Surface | Source trace and bounded conclusion |
| --- | --- |
| Shared POST entry | `admin/controllers/actions.php` performs `check_csrf()`, `require_admin()` and action permission checks before provider/catalog dispatch. Existing forms use `admin_action()` or `csrf_field()`. Parent owns the shared identity implementation. |
| Removed repeat-password fields | Cloud requests, registration drafts, renewal dispatch/review and forwarding forms keep CSRF, exact retained IDs/revisions/hashes and explicit action confirmations. Their parsers return an empty string for omitted legacy password fields, which remains a non-null owner argument. Destination owner functions check the current session rather than treating an empty value as client mode. |
| Current owner and settings | `provider_settings_owner()` calls the current manager and `admin_session_matches()`. Settings saves/state changes occur under `staff_locked` and `staff_transaction` with profile/revision checks. Support context is refused. Repeated-password omission does not remove the separate General API key field or refill a secret. |
| Provider reads and local assignments | Existing-services forms preserve profile/package/domain/client identities. Link composition asserts owning staff transaction, current provider authority, current-revision observation and active client; relink requires the original package/domain to remain in the current observation. Read routes retain their fixed operation and exact scope binding through `provider_inventory_refresh_attempt()`. The original core receipt remains the transport/source-model review. |
| Provider change destinations | Selected Force SSL/directory change, cloud request, renewal dispatch and StackCP access admissions retain owner, binding/review and staff-lock checks. Selected change paths create durable approval before I/O and retain unresolved-attempt fences. This selected seam review does not certify each entire provider backend. |
| Import and variants | Fixed saved-source selector; exact preview/source on retry; explicit draft confirmation; current owner plus owning transaction; newest complete profile/key-bound source. Variant retries retain original request/product/revision/review, disable stale creation and produce a separate draft through the original reviewed service. |
| Hosting publication/request views | Exact product/plan/publication/review and original withdrawal identity remain in failed-POST forms. Project options are client-scoped by the reviewed service. Checkout policy, price/cadence and separate unpaid-invoice/payment/setup steps remain explicit. Buttons do not supply the final commercial authority. |
| Hosting order handoff | Owner-only mapping/preparation uses original catalog mapping ID and hash for catalog-origin orders; the manual remapping form is suppressed. Format-2 verified-payment messaging is propagated, while original service assessment enforces it. Unknown and unresolved setup cards expose investigation/recovery rather than a resend button. |
| Public comparison tail | Complete tail and validator source inspected: safe bounded page, 1–4-offer native tables, escaped text, fixed media and portal destinations, exact publication/hash on checkout links and retained billing-period navigation. CSS source inspected only. |
| Software catalog link | Fixed software=1 portal navigation by family/SKU prefix is GET navigation through the existing authenticated portal. This does not clear the separate parent SW-AUTH finding or any software payment/entitlement mutation. |

## Scope and evidence handling

The intent document `docs/ADMIN_20I_SESSION_INTEGRATION_2026-10-30.md` explicitly calls for current-session authority, retained CSRF/permissions/locks/revisions, seven allowlisted workspaces, literal include mapping and section/focus preservation. Its runtime statements are historical self-report, not independent approval of these exact changed clauses. The earlier core hosting receipt is reused only at its stated source-bound scope. This supplement does not replace or alter it.

The JSON companion records baseline and target Git blobs, complete target SHA256, byte counts, exact changed hunk headers, review depth, notes and selected dependency hashes. “Changed source review complete” means every selected changed clause was read; it does not mean every unchanged backend dependency was audited end to end.

### Original receipts preserved

| Receipt | SHA256 |
| --- | --- |
| `primary-hosting-review.md` | `43d2dcc98c93fac48388c977ddae55c42c93a9bb76db48d1e38b37ca6f48a1fc` |
| `primary-hosting-review.json` | `8a908a454d9a99b3149ce5cf5b3285f8cbc3303d7d96efaa014363976805967b` |

### All 51 supplemental paths

| Path | Review depth | Target SHA256 |
| --- | --- | --- |
| `site/admin/controllers/provider_cloud_confirmation.php` | changed clauses and controller | `1474a8312ff2c9bad525754050c1359cd06e28438c845a65f01475ed060b9491` |
| `site/admin/controllers/provider_cloud_providers.php` | changed clauses and controller | `5ac0ea67eb8e7e848e53281858889f3288e9d1fe520af81b5c2b82edf55e4db9` |
| `site/admin/controllers/provider_cloud_site_request_confirmation.php` | changed clauses and controller | `23bda548e8de260eda4370807de6778d88c7eeb09e2fe153120d6c6187b7b302` |
| `site/admin/controllers/provider_cloud_site_requests.php` | changed clauses and controller | `c2a9bd8e60cf2a08be58abc09cfb6751c4c2901b6db2186736058c6ec255c959` |
| `site/admin/controllers/provider_directory_listing.php` | changed clauses and controller | `02979825d327a442c0f5cf5bd09a64216d4a08bd99b8736b7e3e436f7b170d30` |
| `site/admin/controllers/provider_directory_listing_controls.php` | changed clauses and controller | `4220289a26b833d057b23e874d180fa82039978e56188a103958885053fd17b4` |
| `site/admin/controllers/provider_document_roots.php` | changed clauses and controller | `d48dfa7d946232b9c63a84b6401fa03e213fab876565bb36b0bc0a56f669dc01` |
| `site/admin/controllers/provider_force_ssl_controls.php` | changed clauses and controller | `1ff0892f9878b2b94db6a861126173b19bfdea11181d720561ec6e9896eabad3` |
| `site/admin/controllers/provider_forwarding.php` | changed clauses and controller | `999baa895389fce2f507778d1bb146992d475461278d383f45f2d106a80f5c09` |
| `site/admin/controllers/provider_mailboxes.php` | changed clauses and controller | `79dc10b379c882a334176e171c1ee02211742a674ae719d8ca41be23cc9936bb` |
| `site/admin/controllers/provider_package_force_ssl.php` | changed clauses and controller | `48ef938bdfe7fb110b234483bb3f2cbc9d6d4ecdbea1804e2850c50c5f32ecdb` |
| `site/admin/controllers/provider_package_php.php` | changed clauses and controller | `262ee214a8bbe99fb72a169f0d573409f4c0f6d3cb7889a8452ccbd9dbd5f520` |
| `site/admin/controllers/provider_package_wordpress_installed.php` | changed clauses and controller | `5a6d60de864fdd55ecebfb5a4e23a36dcb25c55acc583210f339b3228e406e47` |
| `site/admin/controllers/provider_password_reset_template.php` | changed clauses and controller | `df95d7cf599ec55c9e0c0b98a39a55bc89c50b43e4e27ea4e27889e0939f401f` |
| `site/admin/controllers/provider_registrar.php` | changed clauses and controller | `dc0629ba31e1959a45064aeafa37b12daceb6d286010a6bb2981aa081df1d953` |
| `site/admin/controllers/provider_registration_periods.php` | changed clauses and controller | `7886ec6dd301cb9b33fb9b2f262cbe06bd4d3546f90e1c89c2469cc70fb0db3d` |
| `site/admin/controllers/provider_schedule.php` | changed clauses and controller | `6d7b8b2649ca271db8be392082b68cccdf77c6932c9a43611f256c76172ea601` |
| `site/admin/controllers/provider_stackcp_access.php` | changed clauses and controller | `e7e2419407806c8e587fae10fb64639c5b3650ce241c34ed57e0c21d0a03f303` |
| `site/admin/controllers/provider_stackcp_prerequisites.php` | changed clauses and controller | `41911e0208f76e0b74edd8d460e15df31087237feb711c7f2ea4949f1f201da5` |
| `site/admin/controllers/providers.php` | changed clauses and dispatch context | `50c38f2ef5152aa3a4d36a6a541ccf90f47f7efc4ea90686118a75edef306dca` |
| `site/admin/views/catalog_import.php` | full source view and destinations | `eda88d8942557502cdfc9ec60f221bc1bd188aa56b86a0e98c7eb3878f524d84` |
| `site/admin/views/catalog_variants.php` | full source view and destinations | `352e36772c00fa324902aa67a19ddc8a06ef48705ac5731072535da075c40596` |
| `site/admin/views/hosting_catalog.php` | full source view and destinations | `a5257c68970f7711c447ef2dfedfbb0e82714834913ef2870101c14419ca6ce1` |
| `site/admin/views/provider_cloud.php` | changed clause and context | `b10ef4863826f6ca0f017b31f6aa1175dfa0abad5596dd7444853334bfd1cb70` |
| `site/admin/views/provider_domain_search.php` | changed clause and context | `0a194bb462ecad01c33c0f6696ae7d64d2e9857176f8e42f99d357fc485c7a17` |
| `site/admin/views/provider_forwarding.php` | full source view and destinations | `db42a7d3b16fdd2a9679ca37c73a4c9035385a6daa779977abf764e60d68b622` |
| `site/admin/views/provider_registrar.php` | changed clause and context | `1df71257478eeddc417f3d4e3a15af7ef328b715661ee8d06ec9290871ab812a` |
| `site/admin/views/provider_registration_periods.php` | changed clauses and context | `66c53f85f50905a90caaad5da3c243c1243ee0bd4e98e661d9314cf8a70851b5` |
| `site/admin/views/provider_schedule.php` | changed clause and context | `5ce251849c5865bd3489852a445089c7fc82004b4705a79b46a45f1be9d3ef9a` |
| `site/admin/views/provider_workspace/advanced.php` | full source new view | `b662d1d7decc1ac7eb3adce1f6a417eca9b59f2a201fc3d72f51f64ce67de3e6` |
| `site/admin/views/provider_workspace/cloud.php` | full source new view | `0cb0a347d95b464d5fc418f91ec7a919066f5e6fac9584a31a6ed37c5c96aeba` |
| `site/admin/views/provider_workspace/connection.php` | full source new view and destinations | `887a12366eeb9fc9b3b87ce30425daa38d3a61f45f294ddd50bafcb4499f411f` |
| `site/admin/views/provider_workspace/domains.php` | full source new view | `bc062c9f8e5a6cf77444ffb6ef6f26cb954a97202939a0f955f6de2c74482eac` |
| `site/admin/views/provider_workspace/services.php` | full source new view and destinations | `0020e28e663890abfb47989d20fd4ca13eb4dfbf9b39f593c8e3302812ccc38e` |
| `site/admin/views/provider_workspace/website.php` | full source new view | `a467c81c0e4b6f04b8d64fd53ba79c2d3d626b7a19bef33f061da97fe931bbbb` |
| `site/app/providers/cloud_site_requests_views.php` | changed clauses and form admission | `59c3d89f76615b182bba3d49d65937b85f88d8033e5ab8ee189409a33070fb64` |
| `site/app/providers/directory_listing_controls_views.php` | changed navigation clauses and destination | `a372f829149d939ba685f365c27e5641f0c1d86fa668ba89a523008b1d317827` |
| `site/app/providers/domain_registration_drafts_views.php` | changed clause and form admission | `9030fd373de0a57bd292dbca0ff114b8ffd5a56437338fc9d37b9750850bc169` |
| `site/app/providers/domain_renewal_dispatch_views.php` | changed clauses and form admission | `3555cbe414ac48a79a258641473d3711d49c77eab3aa1aa68f5f10a6b377bd9b` |
| `site/app/providers/domain_renewals_views.php` | changed clause and form admission | `15a03c49e2cfb4e96755dec5c16d1a19442066021089b85683fb19f38bbbe26d` |
| `site/app/providers/force_ssl_controls_views.php` | changed navigation clauses and destination | `f80735eacd349114bf2ebe40c1db8590d114b357a368f2f5944c225322e0d0e4` |
| `site/app/providers/forwarding_views.php` | changed clause and form admission | `0bf1a4e668c77bc6224588e6796cb5a56135152642a1719a3bb098f3e650f707` |
| `site/app/providers/password_reset_template_views.php` | changed navigation clauses and destination | `14b9c739f20fdcd3eb45621f4dec828c1684463859090ebed83b893d513badf3` |
| `site/app/providers/service_observations_views.php` | changed navigation clauses and destination | `69e0ae937c6fbd1468bc9b629e26072fccd623e811bb90085e98f45e1154c2d4` |
| `site/app/providers/stackcp_access_views.php` | changed navigation clauses and destination | `a45304a8ad476325ca3258f553cd6a3c8714db2462fe46c60e1aa46e20a71ba8` |
| `site/app/providers/stackcp_prerequisite_views.php` | changed navigation clauses and destination | `f94ea36cb5eb5f85608ef71a044a754f938e2f704b759d4d574bd93ef052e62a` |
| `site/assets/css/hosting-comparison.css` | full source styles only | `c22207ed65f0cb3a34ecace16ed63cc733a3e6fc87e645a0d982d036a89b3403` |
| `site/includes/layout/catalog.php` | changed clause and destination | `6b53239075e428d93c3f1e95dec48289ed6da4eae8bdd7b8946472778a47f437` |
| `site/includes/layout/hosting_fulfillment.php` | full source layout and destination | `68500a68ad3a541261416b36fd94f6630cf0085f86ad90859ed6f1529c14935d` |
| `site/includes/layout/hosting_order_links.php` | full source layout and destination | `81633d29f4580f11fc78aa1422fdb8909efc4600667c1ec83be977fa1e8be155` |
| `site/includes/layout/provider_plan_catalog.php` | changed function and destination | `81760284d4d115e97c44c5563d7b103bc7233cbf68fd78cbc960aab5486ffa26` |

### Per-path observations

**`site/admin/controllers/provider_cloud_confirmation.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_cloud_providers.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_cloud_site_request_confirmation.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_cloud_site_requests.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_directory_listing.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_directory_listing_controls.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_document_roots.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_force_ssl_controls.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_forwarding.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_mailboxes.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_package_force_ssl.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_package_php.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_package_wordpress_installed.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_password_reset_template.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_registrar.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_registration_periods.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_schedule.php`** — Full small controller read: section-preserving redirect/back link is the delta. Existing action allowlist, exact identity/confirmation fields and owner/service destination remain; shared POST dispatcher performs CSRF and current administrator/permission checks before dispatch. Service scope is admission/navigation, not complete provider backend certification.

**`site/admin/controllers/provider_stackcp_access.php`** — Full controller read: longer dispatch prefix precedes StackCP setup; confirmed read, package and bounded page inputs reach current-owner access refresh. Return includes advanced and both page parameters.

**`site/admin/controllers/provider_stackcp_prerequisites.php`** — Full controller read: key/assignment actions retain exact review hashes, bounded package IDs and acknowledgement; return page is string/regex/range bounded and both StackCP page fields are preserved.

**`site/admin/controllers/providers.php`** — Workspace helper include and every changed redirect read, with settings/inventory/domain-search action dispatch and selected service guards. Cloud focus is preserved, but ordinary cloud redirects lose section=cloud (HOST-UI-01). Unrelated operations controller bodies are outside this supplement.

**`site/admin/views/catalog_import.php`** — Full source read: fixed package.types/packages.list sources, exact preview hash and source retained on failed create/batch, bounded page/offset, explicit confirmations, local draft-only language, escaped values. Catalog controller and core source-bound import admission reused/traced.

**`site/admin/views/catalog_variants.php`** — Full new view read: direct owner/support boundary, exact product/revision/review identity preserved on failed POST, stale retry disables creation, new request ID generated only for a fresh form, fixed catalog action. Destination owner and create guards traced.

**`site/admin/views/hosting_catalog.php`** — Full new view read: request/project selectors, explicit convert/decline confirmations, setup and checkout preparation, product/plan review, checkout policy and publication retry identities, exact withdrawal identity, escaped content and fixed destinations. Service invariants remain in original core receipt.

**`site/admin/views/provider_cloud.php`** — Focused cloud-assignment view suppresses provider_cloud_sites.php while retaining cloud inventory, client links and history. Current profile/readiness/enabled-key display gates and fixed includes read.

**`site/admin/views/provider_domain_search.php`** — Domain-search history pagination now retains domains section; current profile/readiness gate and retained original configuration/profile revisions on failed POST inspected. No domain purchase is caused by the link.

**`site/admin/views/provider_forwarding.php`** — Full view read: direct repeated password field removed; CSRF/action, permission/event/context identities, explicit confirmation, bounded limits, current-versus-historical distinction remain. Destination configure/change uses current owner under staff lock/transaction. Pagination retains website section.

**`site/admin/views/provider_registrar.php`** — Registered-domain pagination retains domains section. Readiness, current complete-observation display gate, refresh ID, profile/link revisions, exact assignment POST actions and CSRF inspected.

**`site/admin/views/provider_registration_periods.php`** — Both catalog and observation-history links retain domains section. Page parameters are string/digit/range bounded; current profile, readiness, exact retry profile/revision and enabled credential display gates inspected.

**`site/admin/views/provider_schedule.php`** — History links retain services section. Owner, profile and inventory readiness, bounded page, pending/recovery rendering and exact schedule sequence are retained. Controller keeps owner admission and worker identity separate.

**`site/admin/views/provider_workspace/advanced.php`** — Literal calls only to StackCP setup, password-reset-template and StackCP access renderers under the owner workspace. Fixed operations/documentation links. No provider work during this review.

**`site/admin/views/provider_workspace/cloud.php`** — Literal cloud inventory and provider-list includes; displayed observations are not represented as price, ownership or permission to buy. Ordinary cloud return URLs are affected by HOST-UI-01.

**`site/admin/views/provider_workspace/connection.php`** — Full connection form/history view read. General API key remains blank and is not confused with omitted current_password. Profile/revision, enabled/key, CSRF, explicit check/state buttons, escaped history and connection pagination retained. Settings owner/revision/transaction and check admission traced.

**`site/admin/views/provider_workspace/domains.php`** — Literal domain search, registration periods, mailboxes, registrar, contracts, expiry and renewal includes. Reads and purchases remain separate controller/service actions; selected section is not an authorization grant.

**`site/admin/views/provider_workspace/services.php`** — Full existing-services view read: current profile/readiness, explicit refresh, stale profile/key-context and moved-domain warnings, package/domain exact identities, active-client options and CSRF forms retained. Link/relink/unlink service admission inspected. No provider-inventory or provider-package-ID fragment targets exist (HOST-UI-01).

**`site/admin/views/provider_workspace/website.php`** — Literal observation, force-SSL/directory-change, forwarding and cloud-request includes/renderers only. No user-derived filename. Observation and mutation controls keep their dedicated service destinations.

**`site/app/providers/cloud_site_requests_views.php`** — Password helper emits nothing; POST remains exact-text parsed, CSRF-bearing and explicit-confirmation gated. Missing password becomes empty string and still selects owner authority in mutation calls. Confirmation URL carries fixed website section and validated grant ID; history links preserve website section and page.

**`site/app/providers/directory_listing_controls_views.php`** — Every changed pagination/return URL inspected; fixed website section is retained with the existing bounded page value and matching fragment. Owner renderer and controller/service admission inspected. Unchanged complete view/backend behavior is not certified.

**`site/app/providers/domain_registration_drafts_views.php`** — Repeated owner password input removed. Existing CSRF, command/draft/revision/head/client-version fields retained; owner save passes empty string as non-null owner argument and current owner/session is checked under staff lock/transaction before retained command replay.

**`site/app/providers/domain_renewal_dispatch_views.php`** — Three repeated password fields removed. CSRF, exact allocation/dispatch/review identities, unpaid invoice reservation confirmation and four explicit balance-charge checks retained. Dispatch current-owner/payments/ledger gate and commit-before-I/O/fence clauses traced; no complete re-review of renewal backend claimed.

**`site/app/providers/domain_renewals_views.php`** — Owner review/decline password field removed; CSRF and exact request revision/event remain. Current owner admission occurs inside staff lock/transaction before event replay or request update; review stays pending and sends no renewal.

**`site/app/providers/force_ssl_controls_views.php`** — Every changed pagination/return URL inspected; fixed website section is retained with the existing bounded page value and matching fragment. Owner renderer and controller/service admission inspected. Unchanged complete view/backend behavior is not certified.

**`site/app/providers/forwarding_views.php`** — Owner review/decline password field removed; CSRF, expected event and fresh/current permission/resource checks remain in destination under staff lock/transaction. This path only changes local request review.

**`site/app/providers/password_reset_template_views.php`** — Every changed pagination/return URL inspected; fixed advanced section is retained with the existing bounded page value and matching fragment. Owner renderer and controller/service admission inspected. Unchanged complete view/backend behavior is not certified.

**`site/app/providers/service_observations_views.php`** — Every changed pagination/return URL inspected; fixed website section is retained with the existing bounded page value and matching fragment. Owner renderer and controller/service admission inspected. Unchanged complete view/backend behavior is not certified.

**`site/app/providers/stackcp_access_views.php`** — Every changed pagination/return URL inspected; fixed advanced section is retained with the existing bounded page value and matching fragment. Owner renderer and controller/service admission inspected. Unchanged complete view/backend behavior is not certified.

**`site/app/providers/stackcp_prerequisite_views.php`** — Every changed pagination/return URL inspected; fixed advanced section is retained with the existing bounded page value and matching fragment. Owner renderer and controller/service admission inspected. Unchanged complete view/backend behavior is not certified.

**`site/assets/css/hosting-comparison.css`** — Full stylesheet read: scoped comparison selectors, horizontal scrolling and focus outlines, 1–4-column widths, responsive sticky first column and print rules. Source-only: no visual, contrast, keyboard or responsive browser approval.

**`site/includes/layout/catalog.php`** — Software family or explicit SOFTWARE/WEBSITE/APP SKU prefix selects fixed portal software=1 link. Existing escaped product terms remain. Portal auth/render destination traced; no GET purchase and no software-service authorization clearance. Parent software lane owns SW-AUTH.

**`site/includes/layout/hosting_fulfillment.php`** — Full layout read: owner-only escaped saved assignment status, explicit current-profile approval/recovery/confirmation forms, unknown/unresolved outcomes without retry button, verified-checkout explanatory message. Assignment link has HOST-UI-01. Provider-service correctness remains bounded by original core review.

**`site/includes/layout/hosting_order_links.php`** — Full layout read: client-safe progress, owner-only setup forms, original catalog mapping carried forward and manual remapping form suppressed for catalog-origin order. requiresVerifiedPayment is forwarded for messaging; enforcement is server-side in original core receipt.

**`site/includes/layout/provider_plan_catalog.php`** — Entire changed provider_plan_catalog_order_ui function read: owner/recurring lock, exact original mapping lookup and SHA256 comparison, fresh validity check, no silent substitution with latest mapping, exact form identity. Other plan editor functions outside this supplement.

## Remaining limits

- All 51 previously unreviewed changed clauses are now source-reviewed, but review depths differ and are recorded per path. This is not complete backend certification for every destination.
- No browser, screenshots, visual comparison, keyboard/mobile/print accessibility execution, HTTP render, PHP runtime, database, migration, service, provider, real account/payment/mail, remote write or Actions polling was performed.
- The shared current-session helper implementation and broader shared administrator/portal routing remain parent-owned; this supplement verifies callers and selected entry gates, not all shared identity internals.
- Historical runtime receipts remain historical and bounded; no old test pass is promoted to independent present-day approval. Original core coverage limits and FIN-01/FIN-02 remain unchanged.
- Software catalog link routing was reviewed only as a fixed local GET destination. Parent software lane owns SW-AUTH and complete software mutation/entitlement authority review.
- Provider operation backends for all nested workspace tools were not re-audited end to end; selected current-owner, exact-binding, lock and unresolved-outcome admission clauses were traced as dependencies.
- HOST-UI-01 was established by source composition only. Its browser appearance and any additional unlisted legacy deep links were not executed or exhaustively audited.

## Activity attestation

Only local source/diff/document reads and hash/inventory checks were performed. No source, index, branch, database, service, provider, payment, mail or remote state changed. The only writes were these separate scratch receipts and their generator. No tests or runtime probes were run for this UI supplement. The original source and reports remain frozen.
