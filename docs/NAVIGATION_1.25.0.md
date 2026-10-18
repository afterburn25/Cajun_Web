# Administration and public navigation — 1.25.0 candidate

Current outcome: included in [published and download-verified 1.25.0](RELEASE_1.25.0_PUBLICATION.md) at immutable source `b79c67110829126916e3a65e616191542448bccc`. Full exact-main acceptance and the 330-image team review passed with the recorded limits. Earlier branch/local-test statements below preserve their historical cutoff; they do not claim target-host deployment.

The owner requested collapsible administration categories, clearer placement of settings, and Hosting, Web Design and Products in the public menu. This increment starts from installer correction `345b335eeea8331bba5800aa926a3bc1c7507100` on `codex/admin-navigation-1.25.0`. The enclosing commit records its resulting source identity. These are candidate changes, not a published release or live deployment.

## Administration

Overview remains directly accessible. The existing 36 destinations are grouped under Clients & Projects, Website Content, Hosting & Domains, Billing & Products, Email, Settings and Payment Testing. Empty categories are omitted according to the existing permissions and email-access checks. The current page's category opens automatically; the original edit-page aliases and unread inquiry count remain supported.

Categories use native disclosures with keyboard controls. Optional preferences are scoped to the signed-in account and installation path. The mobile drawer has a close button, Escape handling and focus management. Billing destinations still use the existing configured-site URL helper; this change makes no DNS or hosting-account calls.

## Public website

The main menu now includes Hosting, Web Design and Products. Web Design retains the existing services destination. Hosting introduces a consultation page covering requirements, migration and ongoing care, with no invented plans or prices. Products uses the existing published web-design, website-care and additional-service catalog projection, including an empty state. Provider products remain drafts under the existing catalog policy. Client Portal access, portfolio, about, process and contact destinations remain available.

The wider menu switches to its mobile control at 1,200 pixels and retains navigation when JavaScript is disabled. The approved logo, colors and page styles are preserved.

## Verification and limits
The following local evidence and remaining-work statements record the implementation checkpoint before complete release acceptance.


The admin renderer and actual assets passed 134 local PHP/Chromium checks across permission profiles and desktop/mobile widths. The public controller, templates and assets passed 91 local checks across seven widths. Both harnesses used explicit synthetic permission/settings/database seams; neither is native database acceptance. Each author viewed six actual browser screenshots. Independent cross-review inspected the corresponding source, results and pixels. Root also inspected representative desktop and mobile previews.

Root verification passed all 2,888 current installer dependency hashes, touched PHP/Python/JavaScript syntax, 215 acceptance-helper checks and 69 build rejection checks. The full builder refresh also checks every shipping PHP file.

Retained local report SHA256 values:

- Admin: `89125b0f30283e90563a0ecd57d96c1e5b10485bff98b85a70ff2ed5466147c8` (134-check rerun; original 129/131-check evidence retained).
- Public: `a4ef0eed6f150c4cd123ff08b2dc5555e70fc411550b3128dabb8992bfc369ac`.
- Independent admin focus review: `ef7dfaba029e5e259e7ce4bd11cdaf6ac02aa51ef2ec9b823a1257fee882ef9d`.
- Independent public review: `b5a7b152f6410634f14442cbc6284563aeff5c2ced0a258811927db4e79d0358`.

The native staff browser fixture must open the mobile drawer and the relevant disclosure before checking visible authorized links, while still checking forbidden links even when hidden. This adapts the real interaction without relaxing account authorization checks. Independent review reproduced a mobile keyboard-focus defect when reopening a collapsed active category. The narrow correction focuses its visible summary and retains its collapsed choice; three added local checks cover the sequence. Complete candidate and exact-main acceptance, including the original required release images, remain release gates.

## Related work and hosting incident

Installer correction [PR206](https://github.com/afterburn25/Cajun_Web/pull/206) retains existing settings during ordinary file replacement and provides the explicit separate fresh-start path. Its original [focused run](https://github.com/afterburn25/Cajun_Web/actions/runs/39044452947) was building at the 15:32 UTC observation. Bounded 20i product import is being developed on a separate branch; it is not implemented by the navigation change.

On 2026-10-17, public DNS initially failed for the main domain and its custom nameservers. The owner subsequently reported a possible Hostinger payment issue. A fresh read-only check returned successful DNS answers and HTTPS 200 for the main domain. The www hostname resolved but failed certificate hostname validation. No registrar, DNS, payment, SSL or deployed-site changes were made during this investigation. The suspected billing cause is owner-reported, not independently confirmed.

The initial documentation check ran before runtime mirror preparation finished and refused the mismatch; the completed check subsequently passed 267 Markdown files and 1,798 local links. The final focus change also made its installer dependency pin stale, which the explicit current-source check caught. Root refreshed the inventory again after the reviewed source was frozen. These intermediate failures are retained separately and do not count as acceptance.

Next: finish exact-source fixture and package checks, integrate the reviewed branches normally, run complete acceptance on the combined candidate and main, then publish and freshly verify all release assets. Published 1.24.0 remains unchanged.
