# CajunWebPro 1.9.0 release scope

Candidate: bounded P-06 prepared client website ZIP delivery. Source starts at main `cb7556a2837a0b374392541180e2ed2d670a1edd`; implementation branch `codex/client-deliveries`, contract `16dcaaf`. Exact accepted source/CI/download hashes will be recorded after release gates pass.

Scope: sanitized bounded ZIP uploads, immutable manifests and versions, owner-reviewed publication, owned client history and authenticated resumable downloads. Static and files-only scopes are explicit; no database export, automatic builds, live deployment, customer email or paid-in-full restriction. Full P-06 remains partial. Card checkout and actual merchant acceptance remain pending.

Acceptance requires PHP archive/native MariaDB/concurrent tests, real HTTP/Chromium downloads and local static-site opening, fresh installation and the checksum-pinned actual 1.8.0 UPDATE path. All eight assets require draft/public download verification against exact-main CI. No target-host or live-provider claim.

See [setup, limits and limitations](CLIENT_DELIVERIES.md). Devin PR #7 remains preserved and independent.
