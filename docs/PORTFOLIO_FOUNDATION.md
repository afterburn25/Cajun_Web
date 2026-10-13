# Portfolio browsing foundation

Started 2026-10-13 under the owner's instruction to begin independent work while another worker finishes 1.1.0 and publishes its source to GitHub (issue #2). Branch: `devin/public-experience`. Scope: the W-01 record contract and deterministic filtering/ordering component, now adapted to the verified `site/` application.

## Implemented component

The PHP 8.2+ classes in [foundation/portfolio](../foundation/portfolio) need no framework, database, network access or Composer installation. The canonical sources stay here; verbatim copies are bundled into `site/app/foundation/portfolio/` by `tools/build.py` (which rejects divergent copies) and pinned in the installer's per-file SHA-256 inventory, matching the access/invitations bundling pattern.

- [PortfolioItem](../foundation/portfolio/PortfolioItem.php) carries an immutable snapshot of one portfolio record: identity, slug, copy, an explicit kind label, an explicit status, category slugs, a display position and optional live URL, cover capture and case study.
- [CaseStudy](../foundation/portfolio/CaseStudy.php) carries long-form presentation fields (challenge, solution, deliverables, results), a single-capture gallery and before/after pairs.
- [PortfolioMedia](../foundation/portfolio/PortfolioMedia.php) carries one capture's asset reference and required alternative text.
- [BeforeAfterPair](../foundation/portfolio/BeforeAfterPair.php) carries a genuine paired redesign capture; both sides are required and must reference different assets.
- [PortfolioQuery](../foundation/portfolio/PortfolioQuery.php) normalizes a category selection: blanks, duplicates and non-strings are dropped, and an empty selection means unfiltered.
- [PortfolioCatalog](../foundation/portfolio/PortfolioCatalog.php) returns published records only, filters by exact category membership, and applies a total deterministic order.

## Record contract

These are adapter input fields. The live adapter in [site/app/portfolio.php](../site/app/portfolio/browse.php) maps each `projects` row as follows: `id` → `id`, `slug` → `slug`, `title`/`summary` → verbatim, `is_demo` → `kind` (`1` = `concept`, otherwise `client-work`), `status` → `status` (`published`/`archived` mapped; anything else is `draft`), `category` → single-entry `categories`, `sort_order` → `position`, `website_url` → `liveUrl`, `cover`/`media_id` (via `image_url`) → `cover`, and `challenge`/`solution`/line-split `deliverables` → `caseStudy`. No schema columns were added; before/after pairs are not synthesized from these fields.

| Field | Required source / meaning |
|---|---|
| `id` | Authoritative immutable record identifier; duplicates across records are rejected |
| `slug` | Public detail-path slug, matched exactly without case folding; duplicate published slugs are rejected |
| `title`, `summary` | Display copy for the record |
| `kind` | `client-work` or `concept`; no third value, so unlabeled or inflated claims cannot be expressed |
| `status` | `published`, `draft` or `archived`; only `published` may appear in public output |
| `categories` | Exact non-empty category slugs for browsing filters |
| `position` | Explicit display weight; lower sorts first |
| `liveUrl` | Optional approved external link, verbatim |
| `cover` | Optional card capture with alternative text |
| `caseStudy` | Optional challenge/solution/deliverables/results with gallery and paired captures |

Category matching is exact (no case folding, no numeric coercion). Multi-category selection is a union: a record matches when it carries at least one selected category. Unknown categories produce an empty result, not an error. Ordering is `position`, then `slug`, then `id`, slug/id use byte-exact comparisons, so output never depends on record input order.

## Truthfulness boundary

The contract labels every record `client-work` or `concept`; public output must render that label rather than implying invented customers. Case-study text, media and live URLs are adapter-supplied facts about real approved work. This component does not generate copy, metrics, endorsements or captures, and a before/after pair cannot be formed from a single or duplicated asset. Draft and archived records are excluded before filtering, so an unpublished record cannot surface through a category match or slug lookup.

## Trusted adapter boundary

Never construct portfolio records from browser fields, query parameters or client-side storage. The later adapter reads the real project's authoritative storage and supplies validated fields. `PortfolioQuery` is the only object built from request-derived selections, and it only narrows the published set. Callers still own escaping, route handling, caching and response rendering.

## Application integration (landed on `devin/public-experience`)

- `site/app/portfolio.php` is the trusted adapter: it loads `status='published'` rows once, builds validated `PortfolioItem` records (malformed rows are logged and skipped), and delegates visibility, category filtering and ordering to `PortfolioCatalog`.
- `?page=work` renders every published card in contract order and marks non-matching cards `hidden` when a bounded `cat` query parameter is present. Filter controls are real links (`index.php?page=work&cat=<category>`), so filtering works without JavaScript and produces shareable URLs; `site.js` progressively enhances them into instant in-page filtering with `history`/`popstate` sync. An honest empty state renders when a real category request matches nothing.
- `?page=project&slug=…` resolves through `publishedBySlug`, so draft/archived/unknown slugs keep the existing 404 path.
- The existing `growth_portfolio_view` device previews and before/after slider are retained; before/after requires distinct available media references in `growth_portfolio_{id}` settings. No fabricated captures, results or client claims were added.
- `tests/native.php` covers the adapter against a real MariaDB schema (ordering, categories, exact/unknown/blank filters, draft exclusion); `tests/browser_acceptance.py` covers the filtered route, `aria-current`/`hidden` markup, the empty state, case-study 200/404 and a Chromium click-through.

Remaining W-01 acceptance still belongs to combined integration: real HTTP/Chromium runs on the PR merge ref, review of the CI screenshots, and Codex release sequencing.

## Verification

Run `php tests/portfolio/run.php`. The dependency-free [test runner](../tests/portfolio/run.php) checks published-only visibility, deterministic ordering independent of input order, filter normalization and unknown/blank selections, exact slug lookup, truthful label preservation, genuine-pair requirements and malformed/ambiguous record rejection. It uses synthetic `fx-*` fixtures only and exits nonzero on failure without relying on PHP's configurable assertions.

Local PHP 8.4.25: 46 checks pass and all seven PHP files pass syntax checks. [GitHub portfolio-catalog workflow](../.github/workflows/portfolio-catalog.yml) runs syntax and those same isolated decisions in official PHP 8.2 and 8.4 CLI containers with a read-only checkout mount, no container network, no application secrets and no database. [Branch run 38351581276](https://github.com/afterburn25/Cajun_Web/actions/runs/38351581276) and [PR merge-ref run 38351602715](https://github.com/afterburn25/Cajun_Web/actions/runs/38351602715) passed on [PR #7](https://github.com/afterburn25/Cajun_Web/pull/7); the documentation check and the existing access-policy job also passed on the merge ref ([run 38351602718](https://github.com/afterburn25/Cajun_Web/actions/runs/38351602718), [run 38351602723](https://github.com/afterburn25/Cajun_Web/actions/runs/38351602723)).

The isolated checks do not establish rendering, media delivery, real project content, admin editing, database behavior, installation, target-host behavior or production deployment. The adapter checks in `tests/native.php` and `tests/browser_acceptance.py` run in the shared application CI on PHP 8.3/MariaDB with real HTTP and Chromium; their results on the PR merge ref are the integration evidence, not the isolated suite alone.

## Codex integration review — 2026-10-13

Current candidate: 1.11.0 on current 1.10.0 source, with PR #7 ancestry preserved. Numeric categories retain string types and duplicate published slugs fail before filtering/lookup. Local isolated suite now passes 55 checks; historical 46-check evidence above predates review fixes. [Browsing guide](PORTFOLIO_BROWSING.md) records actual adapter/UI scope and pending release acceptance.
