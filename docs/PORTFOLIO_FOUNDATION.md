# Portfolio browsing foundation

Started 2026-10-13 under the owner's instruction to begin independent work while another worker finishes 1.1.0 and publishes its source to GitHub (issue #2). Branch: `devin/public-experience`. Scope: the W-01 record contract and deterministic filtering/ordering component, not a finished portfolio page.

## Implemented component

The standalone PHP 8.2+ classes in [foundation/portfolio](../foundation/portfolio) need no framework, database, network access or Composer installation. They do not replace the existing application's portfolio, routes or admin. Keep this directory outside deployment packages until the verified 1.1.0 source is inspected, an adapter is integrated and the release inventory is updated.

- [PortfolioItem](../foundation/portfolio/PortfolioItem.php) carries an immutable snapshot of one portfolio record: identity, slug, copy, an explicit kind label, an explicit status, category slugs, a display position and optional live URL, cover capture and case study.
- [CaseStudy](../foundation/portfolio/CaseStudy.php) carries long-form presentation fields (challenge, solution, deliverables, results), a single-capture gallery and before/after pairs.
- [PortfolioMedia](../foundation/portfolio/PortfolioMedia.php) carries one capture's asset reference and required alternative text.
- [BeforeAfterPair](../foundation/portfolio/BeforeAfterPair.php) carries a genuine paired redesign capture; both sides are required and must reference different assets.
- [PortfolioQuery](../foundation/portfolio/PortfolioQuery.php) normalizes a category selection: blanks, duplicates and non-strings are dropped, and an empty selection means unfiltered.
- [PortfolioCatalog](../foundation/portfolio/PortfolioCatalog.php) returns published records only, filters by exact category membership, and applies a total deterministic order.

## Record contract

These are adapter input fields, not assumed existing database column names. After 1.1.0 is published, the real project/media storage is mapped onto them without guessing schema.

| Field | Required source / meaning |
|---|---|
| `id` | Authoritative immutable record identifier; duplicates across records are rejected |
| `slug` | Public detail-path slug, matched exactly without case folding |
| `title`, `summary` | Display copy for the record |
| `kind` | `client-work` or `concept`; no third value, so unlabeled or inflated claims cannot be expressed |
| `status` | `published`, `draft` or `archived`; only `published` may appear in public output |
| `categories` | Exact non-empty category slugs for browsing filters |
| `position` | Explicit display weight; lower sorts first |
| `liveUrl` | Optional approved external link, verbatim |
| `cover` | Optional card capture with alternative text |
| `caseStudy` | Optional challenge/solution/deliverables/results with gallery and paired captures |

Category matching is exact (no case folding, no numeric coercion). Multi-category selection is a union: a record matches when it carries at least one selected category. Unknown categories produce an empty result, not an error. Ordering is `position`, then `slug`, then `id`, all byte-exact comparisons, so output never depends on record input order.

## Truthfulness boundary

The contract labels every record `client-work` or `concept`; public output must render that label rather than implying invented customers. Case-study text, media and live URLs are adapter-supplied facts about real approved work. This component does not generate copy, metrics, endorsements or captures, and a before/after pair cannot be formed from a single or duplicated asset. Draft and archived records are excluded before filtering, so an unpublished record cannot surface through a category match or slug lookup.

## Trusted adapter boundary

Never construct portfolio records from browser fields, query parameters or client-side storage. The later adapter reads the real project's authoritative storage and supplies validated fields. `PortfolioQuery` is the only object built from request-derived selections, and it only narrows the published set. Callers still own escaping, route handling, caching and response rendering.

## Integration work after 1.1.0 is published

1. Inspect the real portfolio/projects/media schema, admin controls and public routes; map fields onto this contract and agree ownership of any shared template, route or schema change with Codex before editing it.
2. Build the adapter that loads published records, resolves real capture/media paths and supplies honest labels; synthetic fixtures stay in tests.
3. Wire responsive browsing/filtering and case-study rendering through the actual PHP application, preserving navy/gold branding, exact logo/favicon, app/assets layout and the one-page keyless installer.
4. Render before/after interactions only where genuine paired assets exist; keep keyboard/touch access and relevant reduced-motion behavior.
5. Run native application acceptance: real PHP rendering, desktop/mobile layouts, changed routes/data paths and media integrity. Add files to the installer/build inventory only after integration is accepted.

## Verification

Run `php tests/portfolio/run.php`. The dependency-free [test runner](../tests/portfolio/run.php) checks published-only visibility, deterministic ordering independent of input order, filter normalization and unknown/blank selections, exact slug lookup, truthful label preservation, genuine-pair requirements and malformed/ambiguous record rejection. It uses synthetic `fx-*` fixtures only and exits nonzero on failure without relying on PHP's configurable assertions.

Local PHP 8.4.25: 46 checks pass and all seven PHP files pass syntax checks. [GitHub portfolio-catalog workflow](../.github/workflows/portfolio-catalog.yml) runs syntax and those same isolated decisions in official PHP 8.2 and 8.4 CLI containers with a read-only checkout mount, no container network, no application secrets and no database.

These checks do not establish rendering, media delivery, real project content, admin editing, database behavior, installation, target-host behavior or production deployment. Those remain pending integration with the verified current source.
