# Cloud server inventory


## Client assignments integrated on the development branch

The `codex/twentyi-cloud-links` increment adds schema 5 and typed owner assignment/reapproval/removal forms. It uses existing client accounts and the current cloud observation; names or hosted-package membership cannot infer ownership. Each approval binds a profile revision, credential-context fingerprint and exact observation. Link revisions reject stale forms, and append-only events preserve original approvals even after removal. Owner password/authority and strict audit are required; no assignment action sends a provider request or changes billing.

Client cards expose only the explicitly assigned server's saved name/location/specifications with original units and observation time. Hosted package IDs never enter the client projection because they may identify other clients. Changed/disabled settings hide metadata; refreshing alone does not reapprove an assignment. Absence from an unknown/partial observation is not evidence of deletion or cancellation. Removing an assignment revokes portal visibility only. Orders, renewal policy, health/status and interactive controls remain unfinished.

Back up the original database/key, then use the existing owner preparation action to upgrade v1–v4 to5. Established missing tables require restoration, not empty reconstruction. Fresh setup stamps5. Exact c377 checkpoint passes 848 native and 7,585 packaged assertions including 1,406 HTTP checks, with no PHP diagnostics. Full release acceptance remains required. The earlier reader-only notes below describe the prior increment.

Development integration on `codex/twentyi-cloud-inventory` / PR #127; #128 normally merged at 235f98e1. Not integrated into main, released or live-verified. Codex retains complete 20i P0 ownership. This is the next path after the separate 1.24 inventory/operation candidate, not completion of the cloud platform.

## Evidence and boundaries

The [official Cloud Hosting API guide](https://docs.20i.com/managed-cloud-servers/api-cloud-hosting), updated 2024-02-13 and inspected 2026-10-15, documents GET `/cloud_server` and a list with IDs, names, locations, configuration and package IDs. Its example is printed PHP, so it does not establish every JSON scalar type. Decimal numeric facts accept bounded integers or decimal strings; floats, booleans and coercion are rejected. CPU cores, `RamMb`, `OsDiskSizeGb` and `DataDiskSizeGb` keep their original units. Missing optional facts remain unknown. Ambiguous flags/count, cPanel data and unrecognized values are dropped. No entitlement, live response, service health, cost, retail price or ownership is inferred.

The existing reader makes one bounded request through the existing fixed TLS transport. Only the exact `/cloud_server` list URL is added; arbitrary resource paths, queries, redirects, writes and power/access endpoints remain refused. A complete response means returned entries validated; account-wide coverage remains unknown. Mixed or duplicate rows are partial, and failed responses never replace saved observations.

## Native workflow

The existing provider-settings page adds a Cloud server inventory section with explicit owner-password refresh, current settings revision, latest result, last valid observation, units and separate refresh history. It shares package inventory cooldown/allowances. Metadata under changed settings stays hidden until refreshed. This read alone grants no client authority. The explicit assignments above govern client specification visibility; lifecycle controls remain implementation work.

Inventory schema v4 adds `provider_refreshes.operation` (nullable for genuine historical package intents) and `provider_observation_cloud`. It preserves original intent/result/link/history rows. The same engine stores operation before network access, checks account/operation scope, and commits result plus sanitized metadata and audit together. Package queries explicitly exclude cloud refreshes, including failures. Metadata storage is LONGTEXT because a bounded package-ID list can exceed TEXT, but provider bodies remain capped at 1 MiB. Missing stamped storage is a restore requirement; migration does not reconstruct lost history.

Back up the original database and external provider key. Use the existing owner preparation action for v1/v2/v3-to-v4; GET performs no schema change. Unsupported or incomplete storage keeps the preparation path reachable. Restore the original records/key when storage is missing; do not reinstall over a configured site.

## Historical reader checkpoint and remaining acceptance

Pure local reader/transport checks pass (82 default and 82 no-ini), along with existing package contracts. Newly added native matrices and extracted-package browser scenarios are prepared, not yet accepted. Live cloud-account entitlement/read acceptance, full combined release acceptance, product selection, orders, provisioning, status/power/rebuild, snapshots, billing and interactive SSH remain separate unfinished requirements. The complete original API reference is still needed; this specific public guide does not substitute for that source.
