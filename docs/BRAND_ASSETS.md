# Approved branding and visual-reference provenance

Exact-main acceptance verified actual public/admin/client/installer favicon references and downloaded icon bytes; original wordmark/source hashes are unchanged. See [release register](RELEASES.md) and [test report](TEST_REPORT_1.1.0.md).

Updated 2026-10-13: application assets are recovered under `site/assets/`; the exact original favicon source is retained at [brand/favicon-source.png](../brand/favicon-source.png), SHA-256 `9152404cc8d0a199e4c77f3fe4dd2595c8c45967267dd2bd8bbd855e5164a70d`, recovered from the owner's local `icon.png`. Visual comparison confirms the shipped square favicon depicts this same fleur-de-lis with transparent padding. The original horizontal `site/assets/logo.png` retains SHA-256 `9c44559bb716076c51ec40daff0f394956a32d24039efa6e3fe33039fc10fe29`. Public, admin, portal and installer heads reference `assets/favicon.png`; root `favicon.ico` is also packaged. Browser/package verification is recorded with the [release matrix](RELEASE_1.1.0.md).

The following provenance notes describe the original 2026-10-12 artifacts; their former not-imported status is superseded above.

## Primary website wordmark — preserve exactly

- Artifact: `CajunWebPro-Website-Logo.png`.
- Baseline distribution path: `assets/logo.png`.
- Dimensions: 1080 × 302 pixels; RGBA PNG.
- SHA-256: `9c44559bb716076c51ec40daff0f394956a32d24039efa6e3fe33039fc10fe29`.
- This session verified that the standalone artifact and the packaged logo have identical bytes.
- Appearance: navy/royal-blue and metallic-gold fleur-de-lis at left, CajunWebPro wordmark, Web Design & Development descriptor. No `.com` in the visible logo.

Do not use the later generated large circular/crested logo instead. Do not redraw, change typography, remove the descriptor, stretch, flatten transparency, or replace this mark without a new owner request. The domain can still include `.com` in URLs; the wordmark should not.

## Favicon — owner's exact attached source

- Artifact: `icon(1).png`.
- Dimensions: 248 × 301 pixels; RGBA PNG.
- SHA-256: `9152404cc8d0a199e4c77f3fe4dd2595c8c45967267dd2bd8bbd855e5164a70d`.
- Appearance: isolated blue/navy and gold fleur-de-lis, no text.
- User instruction: use this attached image as the favicon.

Keep this original unchanged as a source asset when importing. Produce square variants with transparent padding and preserved aspect ratio. Check legibility at browser-tab sizes, alpha edges, light/dark backgrounds, and actual icon references. Do not claim the existing baseline `assets/favicon.png` already satisfies this request without comparison. Update the installer's dependent-file inventory for added/changed outputs.

The baseline's separate `assets/emblem.png` has SHA-256 `eef8a21455d89155e742548ac37faa98a686264cde46fe1e3e0da8818fa6697f`; it is not byte-identical to the owner's favicon attachment. Similar appearance is not permission to silently substitute it.

## Website visual references

### Selected foundation

`cajunwebpro_louisiana_digital_excellence.png` is the first selected rich navy/gold agency homepage concept. Preserve its overall design direction: sharp blue/navy, gold/silver emphasis, laptop/phone artwork, bridge/Louisiana context, polished services and portfolio, strong calls to action.

The actual packaged PHP design was revised after the owner found earlier versions too plain. Compare implementation screenshots against the approved concept, not against unrelated Novaix/Nexora mockups generated earlier in the conversation.

### Later expansion concept

`cajunwebpro_s_luxury_web_agency_homepage.png` illustrates additional sales/portfolio/client-portal/comparison content. Treat it as a layout reference only, not a source of verified client work, measured results, payment capability, or competitor facts. Do not copy fake claims, statistics, reviews, pricing or customer identities from artwork into live content.

## Import and derivative rules

Recover original files from current attachments or the file Library, verify fingerprints, and commit a stable original/derivative mapping. Add actual repository paths here after import. Keep all website images local in deployable assets where practical; do not depend on expiring chat sandbox URLs. No font files are to be exported from the assistant environment.

Transparency and source resolution must be verified; upscaling does not create native 4K detail. Use actual HTML for website text, controls and forms rather than embedding a full-page image.
