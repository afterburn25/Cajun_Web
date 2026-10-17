## 2026-10-17 — installer layout, administrator passwords and explicit fresh start

Pending import: `codex/installer-layout-1.25.0`, starting head and merge base `bc5ec277580aa681f43aaf2c9b97df730f6954f1`. It is a direct descendant of the current release candidate and adds only the owner-requested installer/password/recovery correction and its tests/docs. No feature branch is reimported. The enclosing correction commit supplies the reviewed head; record the actual subsequent normal merge separately. Local evidence is in [the correction report](INSTALLER_FRESH_START_1.25.0.md); complete candidate/main gates remain required.

# CajunWebPro 1.25.0 integration map

Original integration inputs were main35d8 and mature3fa. Root already created `codex/release-1.25.0` from35d8 and normally merged3fa once, followed by the main documentation checkpoint. The feature ancestor table below is a historical source map, not a request to integrate those heads again. Both histories and the deliberate shared-file behavior union are retained.

- Main: `35d8fb7efc94f6f851399d0755fa93097f1fce66`; tree`196670c9b305a9aa45d7a9ae9e0661a703d9a4dc`.
- Candidate: `3fa72d7b3b447fa3d281cc981ed861c508f850f2`; tree`5ae5f5f025c79d217f769545290e80e400abc397`.
- Merge base: `b8f8528b72520e60ee052119dcff1e8b2c829737`.
- Published1.24 ancestor of both: main=True, candidate=True.

All following remote Codex20i heads were inspected. Ancestor means the exact head is already in3fa. An outside docs-only head does not require another runtime merge. DNS/cloud pure-only work remains held. Progress-handoff is main documentation ancestry, not an extra capability.

| Branch | Exact head | Merge base with3fa | Relationship / recommended action |
|---|---|---|
| codex/twentyi-catalog-controls | `8f7028dbbc23d9926d7d0ebde8d2f6b5e6861e98` | `8f7028dbbc23d9926d7d0ebde8d2f6b5e6861e98` | Exact ancestor; no separate merge |
| codex/twentyi-catalog-discovery-contracts | `fcf865891165510aae25f064346809c45eeb3cd0` | `8f7028dbbc23d9926d7d0ebde8d2f6b5e6861e98` | Outside; Markdown-only since included base, retain evidence without runtime merge |
| codex/twentyi-catalog-readiness | `872aec340c3b3e2c7e220b44df9b98b7479e2d24` | `872aec340c3b3e2c7e220b44df9b98b7479e2d24` | Exact ancestor; no separate merge |
| codex/twentyi-client-domain-search | `c00874b1a42433ed35dcc5d42931d5c98c9ac868` | `c00874b1a42433ed35dcc5d42931d5c98c9ac868` | Exact ancestor; no separate merge |
| codex/twentyi-cloud-assignment-integration | `2a86704fa0c7775206cf9ac93e27d0457be307fa` | `2a86704fa0c7775206cf9ac93e27d0457be307fa` | Exact ancestor; no separate merge |
| codex/twentyi-cloud-catalog-contract | `9f46fa0afc1109a5860cd056377b5eef524c6b35` | `427e3a233706f22cd39918d8b97256a7fb2b1e90` | Outside; HOLD separate incomplete pure capability |
| codex/twentyi-cloud-confirmation | `1a74952cb832ef851e5cad3a128c4bf8ad28f793` | `1a74952cb832ef851e5cad3a128c4bf8ad28f793` | Exact ancestor; no separate merge |
| codex/twentyi-cloud-inventory | `1d9e372cac7ff9739d339b2544c7c9cfbcee3e33` | `1d9e372cac7ff9739d339b2544c7c9cfbcee3e33` | Exact ancestor; no separate merge |
| codex/twentyi-cloud-links | `c377174cc3072c2ae2aee815c1ccbfd542b8780a` | `c377174cc3072c2ae2aee815c1ccbfd542b8780a` | Exact ancestor; no separate merge |
| codex/twentyi-cloud-minimal-runtime | `cccf85bf6c3523bca8a0659b5c660915fd67e248` | `cccf85bf6c3523bca8a0659b5c660915fd67e248` | Exact ancestor; no separate merge |
| codex/twentyi-cloud-providers | `3bdf17f2358402186cdbaf5631ebba9bb26ba8c7` | `e006a0839bce4680a2d490abb0f8591531f801cc` | Outside; Markdown-only since included base, retain evidence without runtime merge |
| codex/twentyi-cloud-runtime-preflight | `5bfd9c695d35103c3bf27e8eaf66b054c59741cf` | `5bfd9c695d35103c3bf27e8eaf66b054c59741cf` | Exact ancestor; no separate merge |
| codex/twentyi-complete | `906df7278ba9c3ae9d8f51ae49e480f686f91cee` | `906df7278ba9c3ae9d8f51ae49e480f686f91cee` | Exact ancestor; no separate merge |
| codex/twentyi-delivery-evidence-fix | `1d026026d711a602df5705aa10cf9492574b1108` | `1d026026d711a602df5705aa10cf9492574b1108` | Exact ancestor; no separate merge |
| codex/twentyi-directory-listing-controls | `d98aeef3237a4a864bc04b003c55a208698042b2` | `d98aeef3237a4a864bc04b003c55a208698042b2` | Exact ancestor; no separate merge |
| codex/twentyi-directory-listing-observations | `acbf00b9149f9f26b2a9b86b696c175dd74ba117` | `acbf00b9149f9f26b2a9b86b696c175dd74ba117` | Exact ancestor; no separate merge |
| codex/twentyi-directory-renewal-integration | `4791e6718ca41c97d9e9f60442609fbc9bfaacac` | `4791e6718ca41c97d9e9f60442609fbc9bfaacac` | Exact ancestor; no separate merge |
| codex/twentyi-document-roots-contract | `0ddd5101ad3537de1015420c1d23807010d2165c` | `0ddd5101ad3537de1015420c1d23807010d2165c` | Exact ancestor; no separate merge |
| codex/twentyi-document-roots-observations | `57e72f9f8c4a519f43fa896ffc4677d6da477343` | `57e72f9f8c4a519f43fa896ffc4677d6da477343` | Exact ancestor; no separate merge |
| codex/twentyi-domain-contracts | `5b229ca2655f50458a637494e76eac15460a38c6` | `5b229ca2655f50458a637494e76eac15460a38c6` | Exact ancestor; no separate merge |
| codex/twentyi-domain-controls | `725573f0a74192b420677c3556b0c7a983825bdf` | `725573f0a74192b420677c3556b0c7a983825bdf` | Exact ancestor; no separate merge |
| codex/twentyi-domain-date-recovery | `f725b1c386164c533a8edb1556f7f5545309bdc6` | `f725b1c386164c533a8edb1556f7f5545309bdc6` | Exact ancestor; no separate merge |
| codex/twentyi-domain-date-redaction | `a132b5b3a1cd821e2b0391cee2d827d144f5df70` | `a132b5b3a1cd821e2b0391cee2d827d144f5df70` | Exact ancestor; no separate merge |
| codex/twentyi-domain-expiry-contract | `b3c1cc311b9b3c55e851e5ec8da140424209d383` | `b3c1cc311b9b3c55e851e5ec8da140424209d383` | Exact ancestor; no separate merge |
| codex/twentyi-domain-expiry-observations | `18d296b714adc24fa967926ffeadc28616ac6915` | `18d296b714adc24fa967926ffeadc28616ac6915` | Exact ancestor; no separate merge |
| codex/twentyi-domain-registration-contract | `2e86e4e021980364b8f6d340e100e66779cf6fb0` | `2e86e4e021980364b8f6d340e100e66779cf6fb0` | Exact ancestor; no separate merge |
| codex/twentyi-domain-renewal-agreements | `38665bf324bb0ed6b1275f315f33de262cd5c432` | `38665bf324bb0ed6b1275f315f33de262cd5c432` | Exact ancestor; no separate merge |
| codex/twentyi-domain-renewal-dispatch | `b221eb4be95ec1509458d916f1ffd10d65e71e64` | `b221eb4be95ec1509458d916f1ffd10d65e71e64` | Exact ancestor; no separate merge |
| codex/twentyi-domain-renewal-requests | `f62e6913ff428936d649fc37045d8bfd97f92b3e` | `ed4d1cad66cd259e3003d75737c33658e514c748` | Outside; Markdown-only since included base, retain evidence without runtime merge |
| codex/twentyi-domain-search | `9ab6dc58eb2a2e9648968fbe94091c5bbe1e45a8` | `9ab6dc58eb2a2e9648968fbe94091c5bbe1e45a8` | Exact ancestor; no separate merge |
| codex/twentyi-force-ssl-controls | `89325cde535dfedf5456acf576d33b3f44373602` | `89325cde535dfedf5456acf576d33b3f44373602` | Exact ancestor; no separate merge |
| codex/twentyi-force-ssl-finished-recovery | `d72cef991bd3406facfab3be63d819b4d569a5a8` | `d72cef991bd3406facfab3be63d819b4d569a5a8` | Exact ancestor; no separate merge |
| codex/twentyi-forwarding-requests | `1992e3769c5d017a16a68718451524e5ed74b734` | `1992e3769c5d017a16a68718451524e5ed74b734` | Exact ancestor; no separate merge |
| codex/twentyi-fresh-profile | `5fa5998dab9c727b9b2f29c1ff3e162187364280` | `5fa5998dab9c727b9b2f29c1ff3e162187364280` | Exact ancestor; no separate merge |
| codex/twentyi-fulfillment | `7038cb50bafecfd6f96b0fcc3df944622fa0900b` | `7038cb50bafecfd6f96b0fcc3df944622fa0900b` | Exact ancestor; no separate merge |
| codex/twentyi-hosting-dns-contract | `fb2f19d27c24c4a197aec410534ee5ca06c6d94d` | `427e3a233706f22cd39918d8b97256a7fb2b1e90` | Outside; HOLD separate incomplete pure capability |
| codex/twentyi-mailbox-controls | `a9637f2bb2000196312f72e10f39f614980c98c7` | `a9637f2bb2000196312f72e10f39f614980c98c7` | Exact ancestor; no separate merge |
| codex/twentyi-nameserver-controls | `f8654252e1d029c0474c4fbf54839142b621ff65` | `f8654252e1d029c0474c4fbf54839142b621ff65` | Exact ancestor; no separate merge |
| codex/twentyi-nameserver-observations | `9ea20f8787085e308bd6a9acd2d3f6be8cfaa2f4` | `725573f0a74192b420677c3556b0c7a983825bdf` | Outside; Markdown-only since included base, retain evidence without runtime merge |
| codex/twentyi-next-integration | `faef8a6a0fb0a3969a67fe8bc7aabd3b107c1c10` | `faef8a6a0fb0a3969a67fe8bc7aabd3b107c1c10` | Exact ancestor; no separate merge |
| codex/twentyi-order-eligibility | `5d2785cfb8b9c81f25e79ef079c999e8646f3253` | `5d2785cfb8b9c81f25e79ef079c999e8646f3253` | Exact ancestor; no separate merge |
| codex/twentyi-package-force-ssl | `6988eb33315d4c140d3edff4ede5349f531c6c58` | `6988eb33315d4c140d3edff4ede5349f531c6c58` | Exact ancestor; no separate merge |
| codex/twentyi-package-force-ssl-app | `c06ea25d22280429f9116bb9de3ae4c86061365e` | `c06ea25d22280429f9116bb9de3ae4c86061365e` | Exact ancestor; no separate merge |
| codex/twentyi-package-php-version | `8b257538af84aef6cec9c62d83619c53fe0ecde7` | `cfc6ecd034ad93cb317fe156ed0621fa50436c10` | Outside; Markdown-only since included base, retain evidence without runtime merge |
| codex/twentyi-period-fixture-owner | `dd116b8910891fe9e55de8f858a8f7b0ac61fbfd` | `dd116b8910891fe9e55de8f858a8f7b0ac61fbfd` | Exact ancestor; no separate merge |
| codex/twentyi-progress-handoff | `155db254e8e3d0c018200abf6a9325d04c3578ca` | `b8f8528b72520e60ee052119dcff1e8b2c829737` | Main documentation ancestry; already preserved by starting from main |
| codex/twentyi-provider-support-handoff | `3189e5f9fa2a94e50a25961bf5d46056b2c1bada` | `3189e5f9fa2a94e50a25961bf5d46056b2c1bada` | Exact ancestor; no separate merge |
| codex/twentyi-recovery-integration | `e1cd8e44e4b8f748140618cf152521bb4215bc27` | `e1cd8e44e4b8f748140618cf152521bb4215bc27` | Exact ancestor; no separate merge |
| codex/twentyi-recurring-eligibility | `26be6d2bcc874d83ba543f8b9979a5c829e4bb3d` | `26be6d2bcc874d83ba543f8b9979a5c829e4bb3d` | Exact ancestor; no separate merge |
| codex/twentyi-recurring-recovery | `35a564a1a2f6660258ff0e0f2fcf057cb24a07f3` | `35a564a1a2f6660258ff0e0f2fcf057cb24a07f3` | Exact ancestor; no separate merge |
| codex/twentyi-recurring-ui | `f52525f37cd1bb33705f53d2957a09360856dedd` | `f52525f37cd1bb33705f53d2957a09360856dedd` | Exact ancestor; no separate merge |
| codex/twentyi-registration-drafts-integration | `9779879bc93e030bab70be383186700aabea3982` | `9779879bc93e030bab70be383186700aabea3982` | Exact ancestor; no separate merge |
| codex/twentyi-registration-periods | `ee3d76a169bbe71a998346ceb9018e75c34133dd` | `ee3d76a169bbe71a998346ceb9018e75c34133dd` | Exact ancestor; no separate merge |
| codex/twentyi-renewal-integration | `a63cc18ecfdb6d22ba1c138809c6e7bd01659d1a` | `a63cc18ecfdb6d22ba1c138809c6e7bd01659d1a` | Exact ancestor; no separate merge |
| codex/twentyi-scheduled-refresh | `2b9b0dc043802587803017e691788e1035ef7f5d` | `2b9b0dc043802587803017e691788e1035ef7f5d` | Exact ancestor; no separate merge |
| codex/twentyi-service-observations | `bff198cca9ea06973088e33d5a164b17d0c16d13` | `bff198cca9ea06973088e33d5a164b17d0c16d13` | Exact ancestor; no separate merge |
| codex/twentyi-service-orders | `5bec4fb2cd0826386617098972ae3eb3a6fff0e1` | `5bec4fb2cd0826386617098972ae3eb3a6fff0e1` | Exact ancestor; no separate merge |
| codex/twentyi-stackcp-access-observations | `819c3ea39b4ebbef8f37280dc5ede59cf06fd01c` | `819c3ea39b4ebbef8f37280dc5ede59cf06fd01c` | Exact ancestor; no separate merge |
| codex/twentyi-stackcp-integration | `9538ef1c87884167de8e0c1d84d1f9c28203423c` | `9538ef1c87884167de8e0c1d84d1f9c28203423c` | Exact ancestor; no separate merge |
| codex/twentyi-stackcp-prerequisites | `fbaae6ec3b20b17be3dbc32ca6ebf9f3423e9019` | `fbaae6ec3b20b17be3dbc32ca6ebf9f3423e9019` | Exact ancestor; no separate merge |
| codex/twentyi-stackcp-read-snapshot | `3fa72d7b3b447fa3d281cc981ed861c508f850f2` | `3fa72d7b3b447fa3d281cc981ed861c508f850f2` | Exact ancestor; no separate merge |
| codex/twentyi-vps-controls | `33a2a2b013a3d8d340d0f26bbabd960f96312189` | `33a2a2b013a3d8d340d0f26bbabd960f96312189` | Exact ancestor; no separate merge |
| codex/twentyi-wordpress-installed | `65c5515c777b24c81000109b4ab9f68747ae9af8` | `65c5515c777b24c81000109b4ab9f68747ae9af8` | Exact ancestor; no separate merge |
| codex/twentyi-wordpress-mobile-controls | `5560635bb2117556de289f0d24c5b170d48e9c77` | `5560635bb2117556de289f0d24c5b170d48e9c77` | Exact ancestor; no separate merge |

## Feature/source and evidence index

| Bounded capability | Origin branch / head | Current implementation paths | Retained proof |
|---|---|---|---|
| Current inventory, current package/domain/client authority and populated upgrade safeguards | codex/twentyi-cloud-runtime-preflight / `5bfd9c695d35103c3bf27e8eaf66b054c59741cf` | `site/app/providers/inventory.php`, `site/app/providers/registrar.php` | `work/2a-focused-root-review.json` (`e0c01d7692b9634ecf9de7ad66e1929788b26ab57b0f463369c735f82eb8a5c6`) |
| Scheduled GET/package inventory refresh | codex/twentyi-scheduled-refresh / `2b9b0dc043802587803017e691788e1035ef7f5d` | `site/app/providers/schedule.php`, `site/app/providers/schedule_storage.php`, `site/app/jobs/run.php` | `work/schedule-5fb-native-verification.json` (`606c5ac294a6f9bc9b2028e0f48cca09be43ac544555b2de7dfe3c72d425144f`) |
| Registrar inventory and explicit domain assignment | codex/twentyi-domain-controls / `725573f0a74192b420677c3556b0c7a983825bdf` | `site/app/providers/registrar.php`, `site/app/providers/registrar_storage.php` | Source-bound guide and suite evidence summarized in scope; not reexecuted by this audit |
| Cloud/VPS/managed inventory and current client assignment | codex/twentyi-cloud-assignment-integration / `2a86704fa0c7775206cf9ac93e27d0457be307fa` | `site/app/providers/cloud_links.php`, `site/app/providers/cloud_assignment.php`, `site/app/providers/cloud_assignment_storage.php` | `work/2a-focused-root-review.json` (`e0c01d7692b9634ecf9de7ad66e1929788b26ab57b0f463369c735f82eb8a5c6`) |
| Client hosting observation history | codex/twentyi-service-observations / `bff198cca9ea06973088e33d5a164b17d0c16d13` | `site/app/providers/service_observations.php`, `site/app/providers/service_observations_storage.php` | Source-bound guide and suite evidence summarized in scope; not reexecuted by this audit |
| Selected-plan order, consent and invoice eligibility | codex/twentyi-service-orders / `5bec4fb2cd0826386617098972ae3eb3a6fff0e1` | `site/app/hosting/orders.php`, `site/app/hosting/order_links.php`, `site/app/hosting/order_views.php` | `work/20i-5bec-native-verification.json` (`e8e39a3ab387834973041086d1859b729fc968369271e687f1b825df262fcf65`) |
| Hosting fulfillment approval, one send, retained-result recovery and allocated-package confirmation | codex/twentyi-service-orders / `5bec4fb2cd0826386617098972ae3eb3a6fff0e1` | `site/app/hosting/fulfillment.php`, `site/app/hosting/fulfillment_confirmation.php`, `site/app/hosting/fulfillment_views.php` | `work/20i-5bec-native-verification.json` (`e8e39a3ab387834973041086d1859b729fc968369271e687f1b825df262fcf65`) |
| Owner-reviewed provider plan mapping, readiness and immutable history | codex/twentyi-catalog-readiness / `872aec340c3b3e2c7e220b44df9b98b7479e2d24` | `site/app/providers/plan_catalog.php`, `site/app/providers/plan_catalog_document.php` | `work/catalog-readiness872-native-verification.json` (`3cdefbd527a3c581bd415bf889a51de42acb6c88f572eb4d43b5ac9a2f924507`) |
| Owner/client domain search and private policy-scoped history | codex/twentyi-client-domain-search / `c00874b1a42433ed35dcc5d42931d5c98c9ac868` | `site/app/providers/domain_search.php`, `site/app/providers/domain_search_clients.php` | `work/20i-1fbeb-client-native-verification.json` (`e55e1a03014b036c5a83a0abfd5b50c9614e5722c416dd8f8bf3538d33b8bd4c`) |
| Observed allowed registration periods | codex/twentyi-registration-periods / `ee3d76a169bbe71a998346ceb9018e75c34133dd` | `site/app/providers/registration_periods.php`, `site/app/providers/registration_period_storage.php` | `work/period-ee3-native-verification.json` (`c060cd900fe77561ecfc5f18c9a6c77265416098d49818b7c89be47ce1feb948`) |
| Domain renewal request/review/decline/withdrawal | codex/twentyi-domain-renewal-requests / `f62e6913ff428936d649fc37045d8bfd97f92b3e` | `site/app/providers/domain_renewals.php`, `site/app/providers/domain_renewals_storage.php` | Source-bound guide and suite evidence summarized in scope; not reexecuted by this audit |
| Provider renewal-date and upstream-expiry observations | codex/twentyi-domain-expiry-observations / `18d296b714adc24fa967926ffeadc28616ac6915` | `site/app/providers/domain_contracts.php`, `site/app/providers/domain_expiry.php` | `work/domain-date-echo-a132-native-verification.json` (`b0d1c8120223471568b5fce72951f8b548a1a8ca63b590fb778b8d82cf73b7cb`); `work/expiry-f42-native-verification.json` (`d8066e9f7f46b9010c496149c641cc5d6d28400fc180c5c3aa955b776fb75ef3`) |
| Owner-approved one-time paid renewal with permanent invoice allocation | codex/twentyi-domain-renewal-dispatch / `b221eb4be95ec1509458d916f1ffd10d65e71e64` | `site/app/providers/domain_renewal_agreements.php`, `site/app/providers/domain_renewal_dispatch.php`, `site/app/providers/domain_renewal_dispatch_storage.php` | `work/renewal-b221-root-focused-verification.json` (`526a49e00913e4c2743fcf4579bb4103c44448efe376e307e815e7b0368c4f8f`) |
| Private registration contact/draft review | codex/twentyi-registration-drafts-integration / `9779879bc93e030bab70be383186700aabea3982` | `site/app/providers/domain_registration_drafts.php`, `site/app/providers/domain_registration_drafts_crypto.php` | `work/registration-977-root-native-verification.json` (`08b1fa6babb64d215fa855c4232eecdb8e373fa33b3cb6776938ead93f9caaf0`) |
| Mailbox creation with transient secret and current client authority | codex/twentyi-mailbox-controls / `a9637f2bb2000196312f72e10f39f614980c98c7` | `site/app/providers/mailboxes.php`, `site/app/providers/mailbox_storage.php`, `site/app/providers/mailbox_views.php` | `work/20i-mailbox-334-native-verification.json` (`af8a925993959dc53f10ebadaf532f69e745693a6a20469a451e9ff9f9042270`) |
| Forwarder request and owner review | codex/twentyi-forwarding-requests / `1992e3769c5d017a16a68718451524e5ed74b734` | `site/app/providers/forwarding.php`, `site/app/providers/forwarding_storage.php` | Source-bound guide and suite evidence summarized in scope; not reexecuted by this audit |
| PHP version observation | codex/twentyi-package-php-version / `8b257538af84aef6cec9c62d83619c53fe0ecde7` | `site/app/providers/package_php.php`, `site/app/providers/package_php_storage.php` | Source-bound guide and suite evidence summarized in scope; not reexecuted by this audit |
| Document-root observations | codex/twentyi-document-roots-observations / `57e72f9f8c4a519f43fa896ffc4677d6da477343` | `site/app/providers/document_roots.php`, `site/app/providers/document_roots_storage.php` | Source-bound guide and suite evidence summarized in scope; not reexecuted by this audit |
| ForceSSL observation and owner change/recovery/readback | codex/twentyi-force-ssl-finished-recovery / `d72cef991bd3406facfab3be63d819b4d569a5a8` | `site/app/providers/package_force_ssl.php`, `site/app/providers/force_ssl_controls.php`, `site/app/providers/force_ssl_controls_storage.php` | `work/force-ssl-b5c2-independent-verification.json` (`3b59cc9f2003c846cdda4c9a98ac106a042a756d0a568c33cea8b118d46b3acf`); `work/force-ssl-bff7-independent-verification.json` (`5cabe9e40ff88dd0cdb5c365b87e03e07aebe27837cbf7e422062a7b8799f8b9`); `work/force-ssl-recovery34f-root-independent-verification.json` (`230952f35b6e448c15809b5536edcefbee9a3d97d7329691c5ff1f53517d9c03`) |
| Directory listing observation and owner change/recovery/readback | codex/twentyi-directory-listing-controls / `d98aeef3237a4a864bc04b003c55a208698042b2` | `site/app/providers/directory_listing.php`, `site/app/providers/directory_listing_controls.php`, `site/app/providers/directory_listing_controls_storage.php` | `work/directory-listing-root-native-verification.json` (`d349ec483d232f6bde4f84f1e7d5eab0f49d7f1757cab1396bcb8be0c26f8ae2`) |
| WordPress installed indication | codex/twentyi-wordpress-installed / `65c5515c777b24c81000109b4ab9f68747ae9af8` | `site/app/providers/package_wordpress_installed.php`, `site/app/providers/package_wordpress_installed_storage.php` | Source-bound guide and suite evidence summarized in scope; not reexecuted by this audit |
| Cloud provider identifiers and existing-server website creation/allocated confirmation | codex/twentyi-cloud-confirmation / `1a74952cb832ef851e5cad3a128c4bf8ad28f793` | `site/app/providers/cloud_providers.php`, `site/app/providers/cloud_sites.php`, `site/app/providers/cloud_site_confirmation.php` | `work/2a-focused-root-review.json` (`e0c01d7692b9634ecf9de7ad66e1929788b26ab57b0f463369c735f82eb8a5c6`) |
| Local StackCP auth-key preparation and unverified assignment | codex/twentyi-stackcp-prerequisites / `fbaae6ec3b20b17be3dbc32ca6ebf9f3423e9019` | `site/app/providers/stackcp_auth.php`, `site/app/providers/stackcp_assignments.php`, `site/app/providers/stackcp_auth_vault.php` | `work/stackcp-fbaae6ec-root-focused-verification.json` (`7457e928c1f081d9fd578afad1729d9201e640a265640e702b2319f81fc1fae9`) |
| StackCP package membership and whole-user grants observations | codex/twentyi-stackcp-access-observations / `819c3ea39b4ebbef8f37280dc5ede59cf06fd01c` | `site/app/providers/stackcp_access.php`, `site/app/providers/stackcp_access_sources.php`, `site/app/providers/stackcp_access_storage.php` | `work/819c-focused-root-review.json` (`b8c1d8bc5e3b8433f4b367501b5448072c756cb0d30f6df3eb1021eb9f887ef8`) |
| Validated StackCP owner read snapshot | codex/twentyi-stackcp-read-snapshot / `3fa72d7b3b447fa3d281cc981ed861c508f850f2` | `site/app/providers/stackcp_read_projection.php` | `work/stackcp-3fa72d7b-focused-evidence/20261016T164824729438Z/named-verification.json` (`efa6da57ee577ba970f788327a7b7ade648cc3b4d94c9659522c9b3984d0d1b7`); `work/3fa72d7b-windows-verification.json` (`faa38a5bbb302efeb2ceedd509a18c668bfa1396656c3661c0ced7eff4e484f3`) |
| Shared commercial/history/gateway, compatibility, packaging and acceptance infrastructure | codex/twentyi-stackcp-read-snapshot / `3fa72d7b3b447fa3d281cc981ed861c508f850f2` | `site/app/hosting/operations.php`, `site/app/hosting/reconciliation.php`, `tools/build.py`, `tools/acceptance_artifacts.py`, `.github/workflows/application-tests.yml` | Source-bound guide and suite evidence summarized in scope; not reexecuted by this audit |

## Original merge requirements and retained gates

- Preserve all39 main-only commits, including marker3/nullable bundle SHA256, immutable bundle filenames, serialized license mutation/activation helpers and bounded audit details. Preserve candidate physical-storage/support/current-client/invoice-purpose guards. Applicable known licensing defects are release blockers, not grounds for replacing main with candidate.
- Root should retain actual merge-tree conflict list and intentional resolutions. Expected sensitive areas are license service/deploy/generated inventory/portal workspace/native license tests plus continuity; recheck actual current parents instead of relying on an older conflict list.
- Candidate3fa already contains paid-renewal, directory/ForceSSL, registration, StackCP/cloud authority, parallel acceptance, synthetic delivery sealing and PDO/tokenizer corrections. Preserve148 native commands,158 browser/197 final report sets,173 artifact-helper checks,360 private paths and the distro lane until deliberate release-only additions require reviewed updates.
- Rebuild canonical installer only after source union. Update version/release manifests only after scope freeze. Include actual1.24 upload catalog and full update/recovery fixtures; existing source agreement is not upgrade acceptance.
- Preserve main-only TWENTYI_CURRENT_CHECKPOINT.md (absent in3fa) and replace stale top-level status rather than stacking contradictory “current” blocks.
- Run exact candidate, independent review, normal merge to main, exact-main acceptance, release packaging/tag/assets and download verification. No tag/assets or production deployment were changed by this audit.

Root created `codex/release-1.25.0` from exact main `35d8fb7efc94f6f851399d0755fa93097f1fce66` and began one normal merge of `3fa72d7b3b447fa3d281cc981ed861c508f850f2`. The enclosing merge commit preserves both histories. Local resolution proof is `work/release125-merge-resolution.json`; complete combined acceptance, exact-main acceptance and release publication remain pending. No duplicate feature branch stack is imported.

## Intentional shared-file resolutions

- Licensing service keeps main's serialized transaction/lock/internal mutation helpers and bounded audits, plus candidate physical-storage readiness. Native tests retain checksum/master/fulfillment coverage and candidate rollback/actor/caller-transaction cases, with persisted success/failure reports. At this initial merge checkpoint the four licensing findings still required a separate release correction. The integrated release source now contains that separately reviewed bounded correction, subject to its source-specific acceptance evidence; PR159 itself is not accepted by this statement.
- Fresh installer combines provider inventory marker5 with licensing marker3. The generated installer inventory is rebuilt from the resulting source.
- Workspace combines support-session denial, physical storage readiness and licensing marker readiness; preserve all provider/recurring links and invoice-purpose guards. The automatically merged portal controller retains both licensing actions and current domain/hosting billing restrictions.
- Admin licensing preparation form appears before the unavailable-storage refusal, so upgraded owners can explicitly prepare missing new storage without schema-changing GET requests.
- Main assignment/review documents supersede older candidate coordination text. Changelog, decisions, session log and roadmap retain both parents' independent historical entries. New status/handoff reflect the release freeze.

## Merge checkpoint validation

Canonical installer refresh and every shipping PHP/JavaScript syntax check pass on the merged source. Documentation check passes261 Markdown files,1712 local links and23 heading links. No combined native database/HTTP/browser acceptance is claimed. The licensing corrections and release-only acceptance coverage were pending at this initial merge checkpoint; subsequent implementation and execution are recorded in the versioned acceptance report.

Staging check also removed one inherited trailing blank line in `provider-domain-search.yml`; its workflow statements are unchanged.

## Main documentation synchronization

Main documentation checkpoint `d7153b076e91b524ff2f99a9a7aa5c910551fc5e` publishes the same owner release freeze, canonical b98b96eb branch and scope/map to make them discoverable. Its12 Markdown-only changes add no runtime, installer, schema or version delta. The release branch normally merges that checkpoint; its existing release-specific status and all prior histories remain, rather than copying main's pre-integration runtime wording. Both sources agree on frozen capabilities, licensing corrections, complete supported acceptance, published1.24 and no live deployment.

## Release-only correction and evidence contract

The integrated source includes the reviewed licensing/history repairs, selected-runtime provisioning, authentic published1.24 upgrade fixtures and deterministic observation privacy assertions. Existing148 native recipes remain. The complete release contract requires160 browser and204 final reports,208 artifact-helper checks, four complete PHP8.2/8.4 × MariaDB10.11/MySQL8.4 environments, the maintained/minimal lane and separate actual runtime provenance. These are source requirements, not claims that a run passed.

The [acceptance report](TEST_REPORT_1.25.0.md) identifies the actual candidate evidence cutoff, preserved failures, final exact-main receipts and publication conditions. The bounded INCLUDE/HOLD scope is unchanged. Additional unmerged Devin PRs are separate work; no stale whole-file proposal is implicitly imported by this correction history.
