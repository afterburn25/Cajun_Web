# CajunWebPro 1.26.0 candidate scope

Version 1.26.0 is in development on `codex/twentyi-direct-hosting-catalog`. It is not published or deployed. The current downloadable release remains [1.25.0](RELEASE_1.25.0_PUBLICATION.md), whose source, tag and assets remain unchanged.

## Included change

The owner can explicitly refresh the 20i hosting type catalog, review types that have no installed hosting account yet, and import them as local drafts. Existing inventory imports remain available. Both sources preserve existing products, edited prices, uploaded images and original provenance. The owner configures local price, features and service terms and can upload product artwork. [Behavior and evidence](TWENTYI_HOSTING_CATALOG.md).

The source archive also includes the public diagnostic scripts retained on main. They are not installed by COMPLETE-UPLOAD or UPDATE.

## Remaining boundaries

Cloud catalog import, automatic provider images and provider pricing are unfinished. Hosting publication and ordering are not added by this increment. No live provider writes, payments, customer mail or site deployment are performed by release testing. The supplied 20i documentation remains available and does not need to be resent.

## Acceptance and upgrade work

The feature's shipping implementation passed the four-way focused native matrix at source `9f0b83fd`. A subsequent test-only correction preserves original source records when modeling changed credentials. Combined browser checkpoint39093140366 runs against immutable source `12da59d1`; its outcome is pending here. These results do not certify the later 1.26.0 package.

The new candidate retains all historical baselines and adds the actual published 1.25.0 COMPLETE package, source `b79c67110829126916e3a65e616191542448bccc`, SHA256 `bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39`. The archive has 2,895 original members. Transfer validates both the retained 1.24 and new 1.25 inputs independently. Existing organized update/recovery cases gain the 1.25 baseline. Separate latest-release cases are wired for populated settings, business records, product imports, images and explicit catalog-storage activation. Their installed runtime outcome remains pending; [the test record](TEST_REPORT_1.26.0.md) distinguishes source, portable and full application evidence.

Before publication: finish the combined browser checks, run the assembled candidate's upgrade/recovery and full supported-runtime acceptance, review actual UI captures, merge normally, verify exact-main acceptance and freshly downloaded release assets. No completed 1.25.0 evidence is relabeled as 1.26.0 acceptance.
