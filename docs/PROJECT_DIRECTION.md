## Owner additions — 2026-10-17

The owner explicitly added installer corrections, reorganized collapsible admin navigation, public Hosting / Web Design / Products links, and admin import of 20i products with images. These bounded requests supersede the earlier release freeze only for this work. Installer correction is committed as `345b335e`; this branch adds the [reviewed navigation increment](NAVIGATION_1.25.0.md). Root coordinates shared seams and normal integration; the product-import lane remains separate and incomplete. All other held capabilities and the complete candidate/main/publication gates remain in force. No live deployment is authorized.

# Project direction

Recorded: 2026-10-12. This document captures accepted owner requirements, not a claim all features are implemented.

## Business and positioning

CajunWebPro designs and builds websites for other people and businesses. The site must market that service, show actual work, qualify inquiries, and eventually support the client relationship. It is not a DIY website builder and should not ask prospects to build their own website.

Brand name in the interface: **CajunWebPro**, without `.com`. The domain remains `cajunwebpro.com`. Louisiana/Cajun identity should support credibility without overwhelming the web-design message. Do not invent a team size, years in business, client counts, ratings, awards, or measured outcomes.

## Approved visual direction

Preserve the first selected rich navy-and-gold homepage concept: sharp blue/navy surfaces, metallic gold highlights, strong silver/gold headline treatment, a large laptop-and-phone scene, Louisiana/bridge imagery, the original fleur-de-lis wordmark, luminous but readable controls, organized service cards, an attractive portfolio, and a strong inquiry call to action.

The owner's central feedback is that a web design company's own website should demonstrate its design and technology capabilities. Plain, muted, generic-template layouts were rejected. Also rejected: overloaded neon scenery, repetitive logo redesigns, flying/disintegrating pixels, and unnecessary complexity. The target is fancy, professional, distinctive, organized, and usable—not a chaotic special-effects demo.

Text, navigation, forms, project cards, and interactions must be actual HTML/PHP/CSS/JavaScript elements, not a full-page mockup used as the functioning site. Decorative imagery must not be represented as a true navigable 3D implementation. Do not describe upscaled source images as native 4K detail.

Use subtle logo highlights, device depth, controlled section motion, portfolio interactions, and responsive lighting with restraint. Avoid autoplay audio, forced loading sequences, obstructive custom cursors, inaccessible interactions, and performance guarantees without measurements. Support keyboard use, touch, readable contrast, and reduced motion.

## Brand assets

Keep the exact existing horizontal navy-and-gold logo: fleur-de-lis to the left, Cajun/Web/Pro lettering, and the web-design/development descriptor. The later generated large crest is not the selected website wordmark. The separate user-uploaded fleur-de-lis is the requested favicon source. See `BRAND_ASSETS.md` for filenames and fingerprints.

## Content direction

Explain services clearly: custom business websites, redesigns, online stores, custom applications, branding/content options, and maintenance where genuinely offered. Give visitors useful information about the process, scope, ownership/handover terms, responsibilities, revisions, costs or ranges, and ongoing support.

Add an engaging 'Why custom / Why switch?' area and dedicated Wix/Squarespace comparisons. Position CajunWebPro around the actual done-for-you service, brand-specific design, information organization, approved custom functionality, and ongoing relationship. Present the cost/responsibility tradeoffs as well as strengths; do not pretend custom development is always the right choice.

Competitive claims require current official sources, dates, and applicable plan/product scope. Do not claim competitors universally have dull designs, no custom code, no SEO, no support, no ownership, or no integrations. Do not reproduce invented comparison claims such as 'anything you can imagine' or automatic guaranteed growth from generated artwork.

Portfolio examples must be labeled as concepts until they are actual authorized client work. Before/after comparisons and case results must be factual. The user has not supplied approved commercial package prices or contractual terms; these are configuration/content inputs, not values to invent.

## Approved expansion

The owner accepted all features proposed in the preceding planning discussion: interactive case studies, signature motion, a single-page project estimator, website-review requests, a private client portal, proposals/deposits/maintenance, consultation scheduling, packages/FAQ/local and industry information, lead pipeline, backups/restores, revision history, and a controlled update manager. The owner additionally requested richer comparison content and the attached favicon.

`ROADMAP.md` defines implementable acceptance criteria. Approval of scope and creation of a concept image do not establish implementation or production activation.

## Delivery and continuity

Keep PHP/MySQL or MariaDB shared-host deployment and the existing app/assets separation. Installation is browser based, keyless, and truly one page. Package and test actual complete releases with no wrapper-folder confusion or multi-patch dependency.

Git `main` is the continuing source of truth once the application baseline is imported. Every session documents what changed, how it was checked, what remains unresolved, and what to do next. Preserve functioning work and existing customer data while extending the product.


## Expansion approved 2026-10-12 — web design PLUS branded hosting resale

CajunWebPro must grow into a fully branded **web design, hosting, domains and VPS company**, not a generic template or site-builder product. Add polished hosting plan comparisons, domain search and checkout, VPS pages, account registration and a unified /clients portal for both website design projects and hosting accounts. Keep the original navy/gold visual design and source structure.

The owner chose a **100% custom PHP/MySQL storefront, billing platform, checkout, subscription engine and portal** (no WHMCS, no HostShop front end or Stripe). Integrate directly with 20i reseller APIs for provisioning and Authorize.net for payment processing. Admin must configure API Login ID, Transaction Key, Signature Key and Public Client Key separately for sandbox/live. Payment forms must blend into the site design, with Accept.js tokenization and PCI obligations explicitly addressed. Products/prices and service options belong in admin, not hardcoded guesses.

Reference [full hosting and billing scope](HOSTING_AND_BILLING_ROADMAP.md). Adding scope does not indicate these services are available or implemented.

## Web-design service payment forms — clarified 2026-10-12

Customers must be able to pay CajunWebPro for web-design work directly through branded Authorize.net forms, independently of a hosting purchase or 20i setup. Include fixed-price services, approved quote deposits, milestone invoices, final balances and optional expressly agreed maintenance subscriptions. Admin creates service-specific payment requests and secure links; customers can also pay their own invoices from the unified portal. Amounts come from approved admin prices/quotes, with receipts and accurate project balances. Deliver the standalone forms with the payment foundation (M2); deeper project and hosting bundles follow in M4. This is approved scope pending current 1.1.0 source inspection and implementation evidence.

## Admin webmail and team access — added 2026-10-12

Admin must support multiple connected email accounts with setup, receiving/reading and sending mail, account switching and a clear combined inbox, drafts/attachments and reusable sending templates. The owner can add additional administrator accounts and assign appropriate access, including mailbox permissions. Provide an unread-email bell in admin and the public-site header for signed-in admins only; selecting a notification opens that exact message in protected admin webmail. Visitors and ordinary clients must receive no private mail data. Keep the experience within the existing CajunWebPro design; exact setup, workflow and acceptance requirements are in [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md). These are approved additions awaiting current-source inspection and implementation.

## Own hosting-management platform — explicit owner direction 2026-10-12

Build our own WHMCS from scratch: CajunWebPro owns its storefront, customer/staff accounts, orders, invoicing, recurring billing, service lifecycle, support, admin and client interfaces. Direct 20i and Authorize.net integrations support those workflows. Include web-design projects/payments and admin email/templates/bells in the same coherent platform. Preserve the existing website and verified current source; implement custom modules incrementally rather than requiring a WHMCS installation. [Custom platform blueprint](CUSTOM_PLATFORM_BLUEPRINT.md) records the agreed boundaries and M0–M6 delivery sequence.

## Complete client workspace and support access — 2026-10-12

The client portal must be the home for every purchased service and web-design project: verified native 20i controls, project stages and dated updates, previews/approvals, support, payments and versioned website ZIP downloads with needed authorized content/database exports. Customers need a notification center and genuine progress/service observations. Keep admin email private. [Client portal roadmap](CLIENT_PORTAL_ROADMAP.md) defines capability coverage and release acceptance; StackCP handoffs are identified bridges, not completed native controls.

The owner and explicitly authorized support staff also need one-click access to a selected client's portal and an Open SSH action for their eligible VPS. Preserve staff attribution, visible support-session context and audit without requesting client passwords. VPS terminal access requires separately verified permissions, server authentication and a working supported transport; a portal login alone is not SSH. Implement A-09/A-10 with the client-data boundaries and shared-host-compatible deployment specified in the client-portal roadmap.


## 2026-10-15 clarification — licensing sold software products

The owner is assigning Devin licensing for software products offered by CajunWebPro, including generation of licenses for installing other products. Existing licensing development remains preserved; Codex's active20i work stays separate. The requirement does not replace CajunWebPro's one-page keyless installer. [Ownership and shared integration boundaries](AGENT_COORDINATION.md).
