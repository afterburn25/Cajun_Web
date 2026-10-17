## Observed product import candidate — 2026-10-17

The explicit owner addition now has a reviewed [hosting-draft and local-image implementation](TWENTYI_PRODUCT_IMPORT.md), based on navigation source e8ccefc9. Root integrates its shared CI wiring and generated inventory. It preserves prior acceptance requirements and adds three reports and two images. Native/HTTP verification is pending; complete 20i catalogs and automatic provider images remain unresolved. This does not mark the full import roadmap or release complete.

## Owner additions — 2026-10-17

The owner explicitly added installer corrections, reorganized collapsible admin navigation, public Hosting / Web Design / Products links, and admin import of 20i products with images. These bounded requests supersede the earlier release freeze only for this work. Installer correction is committed as `345b335e`; this branch adds the [reviewed navigation increment](NAVIGATION_1.25.0.md). Root coordinates shared seams and normal integration; the product-import lane remains separate and incomplete. All other held capabilities and the complete candidate/main/publication gates remain in force. No live deployment is authorized.

# Current release coordination

The owner prioritizes verified publication of 1.25.0 before new20i capabilities. Root owns `codex/release-1.25.0`, normal merges, shared conflicts, canonical inventory, current-candidate review, exact-main acceptance and release/download verification. [Frozen scope](RELEASE_SCOPE_1.25.0.md) is the boundary.

- Catalog: source/evidence scope and complete supported runtime acceptance proposal.
- Domains: actual published1.24 populated client/provider/business upgrade and recovery coverage.
- VPS: reviewed main-preserving licensing corrections and regression evidence.
- Devin: remaining non-20i implementation on separate branches under [assignment157](https://github.com/afterburn25/Cajun_Web/issues/157). Existing158/159/161 repairs remain; no unaccepted proposal is automatically in this release.

Agents prepare external bounded patches; root applies shared changes sequentially. Preserve all unfinished branches, parent evidence and published versions. No new capability branch or nonessential performance refactor until1.25 is published and downloaded back successfully. [Status](../PROJECT_STATUS.md) and [handoff](../SESSION_HANDOFF.md) track remaining gates. No live deployment is authorized.
