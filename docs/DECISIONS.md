# Decision record

Recorded on 2026-10-12 from the owner's project instructions. This is a consolidated record, not an invented timestamp-by-timestamp history of earlier work. Supersede decisions explicitly; do not silently reverse them.

| ID | Accepted decision | Reason / practical consequence |
|---|---|---|
| D-001 | `afterburn25/Cajun_Web`, default `main`, is the ongoing project source of truth | Owner requested durable documentation so new sessions resume accurately; source import is still pending at bootstrap |
| D-002 | Every work session updates direction/status/handoff/history as applicable | Chat memory alone produced loss of continuity and cannot serve as release evidence |
| D-003 | Service-company site, not a DIY website builder | Owner builds websites for clients and wants to showcase/sell that work |
| D-004 | PHP/MySQL shared-hosting application | Preserve normal browser installation without required server root, CLI builds or Node services |
| D-005 | Original horizontal navy/gold CajunWebPro logo without `.com` | Owner selected the existing website mark and rejected unwanted substitutes |
| D-006 | Attached `icon(1).png` is the favicon source | Exact owner-supplied fleur-de-lis; implementation must be checked rather than assumed |
| D-007 | Fancy, sharp, technology-driven but organized public presentation | Earlier muted/plain templates and overloaded effects both failed the desired balance |
| D-008 | No flying/disintegrating pixels | Explicit repeated visual rejection; do not reintroduce through new artwork or animation |
| D-009 | Keyless, genuinely one-page installer | Owner did not request an installation key and rejected Next/Back setup screens and alternate repair entry points |
| D-010 | One complete flat deployment package with one canonical installer | Prior wrapper-folder and standalone-file inconsistencies made deployment confusing |
| D-011 | Preserve private data/configuration/guards and use targeted safe storage | Do not fix deployment by erasing data, disabling installation protection, broad chmod/chown, or promising impossible privileges |
| D-012 | Implement the complete approved feature expansion | Portfolio/motion/estimator/review/client/proposal/payment/care/scheduling/content/pipeline/backup/history/update work is captured in the roadmap, not marked done |
| D-013 | Add more information and Wix/Squarespace switching comparisons | Owner wants a persuasive reason to hire CajunWebPro; claims still require truthful, current evidence and clear service scope |
| D-014 | Keep evidence layers separate | A concept is not implementation; implemented source is not tested deployment; a release ZIP is not the running host |

## Open implementation choices

No actual payment/calendar provider account, package pricing, contract wording, guaranteed service response times, source-directory layout, or new schema migration design has been finalized here. Stripe and calendar integration were suggestions, not already connected services. Record the choice and evidence when made.

## Future entry format

`ID · date · status (accepted/superseded) · request/context · decision · consequences · affected files/roadmap items · verification · supersedes (if any)`.
