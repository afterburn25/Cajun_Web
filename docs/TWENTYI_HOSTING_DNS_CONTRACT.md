# Hosting DNS proposal contract

This isolated increment starts at `427e3a233706f22cd39918d8b97256a7fb2b1e90` on `codex/twentyi-hosting-dns-contract`. It adds three pure PHP value types and synthetic validation tests outside the deployed application. It does not read, change or claim ownership of a DNS zone. Hosting DNS integration remains unfinished.

## Sources and their limits

The owner's supplied reference `74ca50eb-af93-4063-a0a8-a89f5bc63ab4/Pasted text.txt` is 195,404 bytes, 3,116 lines, SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95`. Its DNS section at lines 1368–1415 is retained in the [redacted reference](reference/20i-owner-api-34e713-redacted.txt). This is the newer complete reference, not the earlier unavailable attachment. The existing [intake](TWENTYI_OWNER_API_REFERENCE_INTAKE.md) already identifies the missing DNS response shape.

The section documents GET and POST at `/package/{packageId}/domain/{domainId}/dns`. GET is described as returning current records and suggested nameservers, but gives no response envelope or field types. POST describes `conflictPolicy` and `insertPolicy` as strings, a `new` map containing the following record objects, and `delete` as an array of strings. Every illustrated record field is a string.

| Type | Illustrated fields |
|---|---|
| A | `host`, `ip` |
| AAAA | `host`, `ipv6` |
| CNAME | `reuseId`, `host`, `target` |
| MX | `host`, `target`, `pri` |
| TXT | `host`, `txt` |
| SRV | `host`, `pri`, `weight`, `port`, `target` |

The prose requires active authoritative DNS for effects, says additions may become updates, and explains that CNAME conflicts require explicit deletion unless `conflictPolicy` is `replace`. That single mentioned value does not establish the complete policy enum, default or field requiredness. The generated one-object-per-type example does not establish how multiple records of one type are submitted.

The retained SDK snapshot is [20i-authored code redistributed at immutable commit 696a5f12](https://github.com/LunarDevelopment/20iRestModule/tree/696a5f1299cd2b1e67ae7f688d644ac800f41b67). Its composer package is `20i/api-modules` 1.0.6, with 2018 20i copyright and fixed `api.20i.com` service origin. All 23 retained files (19 PHP plus README, changes, license and composer metadata) were searched and their manifest hashes verified. They contain no DNS-record read response or change example. [The package test](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/test-real/04-get-package.php) checks a package name; that cannot establish an allocated package-domain identifier or authoritative zone. The SDK's direct JSON decoding and registrar nameserver example cannot establish a hosting-DNS envelope or success result. The local immutable-source manifest and investigation are retained at `work/catalog-dns-source/`; no SDK example was executed.

The [official DNS guide](https://docs.20i.com/domain-names/guide-dns), inspected 2026-10-16, explains the six record meanings and excludes a root CNAME. It is a UI guide, not an API response schema. The [DNS zone import guide](https://docs.20i.com/web-hosting/import-dns-zone-file) likewise does not define API responses. The [official API documentation guide](https://docs.20i.com/api/20i-api-documentation) points to authenticated My20i documentation; the public link led to sign-in. No authentication or provider request was attempted.

## Pure components

- `HostingDnsContext` retains a validated copy of the existing package assignment witness, a distinct opaque package-domain reference and a canonical zone name. It recomputes the package document, digest and scope before copying it. Reusing this witness does not inherit PHP-version operation authority or prove current database state. `identity_status` is always `unverified`.
- `HostingDnsRecord` retains only the six evidenced field sets and exact accepted values under the local policy below. It does not represent a saved provider record or produce a wire record.
- `HostingDnsProposal` creates a canonical, hashed local review document from typed records and deletion references. It validates restored values again, detaches caller-owned scalar references, rejects duplicates and within-proposal CNAME conflicts, and sets `execution` to `unavailable`.

None has a URL, request descriptor, transport, provider response parser, success classifier, credential resolver, database write or clock. The proposal's local `records` list and `delete_references` are deliberately distinct from the provider's `new`/`delete` envelope. Accepting multiple A values for local review does not establish wire multiplicity. Hashes bind exact local bytes; they are not signatures, current-authority checks, provider revisions or execution consent.

The supplied path says `{domainId}`. No canonical zone name, registrar identifier or package name is substituted into that position. The opaque reference can contain characters unsuitable for a URL; preserving it is not permission to interpolate or encode it into a provider request. Package-domain identity evidence is required before any executable request builder.

## Local validation policy

These bounds and syntax restrictions are defensive application choices, not a claim that they describe the provider's entire accepted schema.

| Value | Accepted local subset |
|---|---|
| Opaque domain, reuse or deletion reference | Nonempty UTF-8 string, at most 128 bytes, no control characters or surrounding whitespace; never interpreted as a provider ID grammar |
| Zone or record target | Lowercase ASCII multi-label DNS name, no trailing dot, wildcard or IP literal; labels at most 63 bytes and total at most 253 |
| Relative host | `@` or lowercase ASCII labels allowing underscores for service names; no wildcard, trailing dot, escape, slash or control character; combined host plus zone at most 253 bytes |
| A / AAAA address | One syntactically valid address of the matching family; no CIDR, hostname or interface zone |
| Priority, weight, port | Canonical decimal string from 0 to 65535; no sign, leading zeros, exponent or numeric coercion |
| TXT | Nonempty UTF-8, at most 4,096 bytes, no controls; accepted quotes and backslashes retained exactly, without guessing provider quoting or DNS wire chunking |
| Proposal | 1–32 total additions/deletion references and at most 32,768 canonical JSON bytes; duplicate records/references refused; CNAME cannot coexist with another proposed record at the same host |

The root CNAME refusal also agrees with the official guide. Undocumented record types/TTL fields are refused. CNAME requires the illustrated `reuseId` locally, while its provider meaning and requiredness remain unverified. Existing zone records are unavailable here, so conflict validation applies only inside the local proposal. Nothing automatically deletes a conflicting existing record.

The proposal constructor accepts an explicitly supplied protected credential solely for an echo check; it cannot resolve or authorize that credential. It refuses its raw bytes or exact standard Base64 form in proposed record values, deletion references, domain reference or zone name, and retains neither secret. Exceptions use finite reasons without raw values. This narrow defense does not cover every possible encoding or authorize future public projection. Intermediate context/record objects have no persistence or UI route and must never be treated as independently safe saved/public records.

## Required evidence before application integration

1. Exact package-domain ID source, current membership/zone relationship and authoritative status; package-list names alone are insufficient. Revalidate account, profile revision, credential, current assignment and zone scope before credential resolution and again before dispatch.
2. GET envelope, record collection/identity fields, nameserver fields, explicit empty/error distinctions and bounded typed parsing with credential-owned decoded-value filtering. Do not persist raw unknown provider responses.
3. Policy enums/defaults/requiredness, `reuseId` semantics, deletion identity against a reviewed observation, multi-record input shape and any TTL/TXT/target normalization rules.
4. Provider acknowledgement/refusal/partial/async semantics and readback consistency evidence. HTTP success alone cannot mean DNS was changed, and an uncertain write cannot be retried automatically.
5. Existing gateway/ledger admission, exact operation-specific immutable intent/result/audit witnesses, explicit current-owner/client permission, reviewed consent binding the observed zone and canonical change, expiry/revocation handling and owner-reviewed recovery. Keep independent history-loss/order witnesses and the current scheduler transaction/mutex rules.
6. Root-reviewed shared interfaces, native database regressions, actual admin/client browser flows and exact generated/package acceptance. No automatic exposure through the current PHP-version permission or route.

The application, shared readers/transport, schema, installer and licensing stay unchanged in this branch. The only workflow addition runs these pure tests in PHP8.2/8.4 containers with `--network none`; it cannot invoke application acceptance. Nameservers, subdomains, document roots and complete DNS editing are separate unfinished work.

## Actual validation

Local PHP 8.4 runs `php tests/hosting_dns_contract.php` and `php -n tests/hosting_dns_contract.php`: 158 distinct named checks pass in each mode. All four added PHP files pass syntax checks. Cases cover all field sets, wrong families/types/keys, strict numeric/name bounds, duplicate/CNAME conflicts, exact opaque identities, immutable witness/hash changes, malformed restored objects, scalar-reference detachment, credential echoes, exact JSON/size limits and absence of request/result methods. Reports are retained under the branch's ignored `qa/` directory.

These are pure synthetic tests only. No native database, UI, packaged application, live DNS or provider acceptance is claimed. Root independently reviewed the exact three pure types, tests and guide with no concrete blocker for this non-executable scope. The narrow PHP8.2/8.4 workflow is approved and added; its exact-source results are verified below. No application full run is needed for this non-shipping increment. Preserve the separate root candidate and all prior failed/passing runs.

### Exact remote pure evidence

Frozen source `96fba852629ae0b441051008225d1b790bc4597f`, tree `8d5b4e3ac6632b5ff1854c74fb86038abd217a36`, passes the single automatic [run38855162311](https://github.com/afterburn25/Cajun_Web/actions/runs/38855162311). Both Linux containers ran with network disabled:158 distinct default and158 minimal checks per PHP version,632 executions total. Both actual archives were downloaded; fresh metadata, SHA256, size, CRC, four safe unique members, all six source/workflow hashes, exact source/tree, actual PHP versions and every named result agree.

| PHP | Artifact | Bytes | SHA256 |
|---|---|---|---|
| 8.2 | 12026543068 | 4514 | `141dcced55bae3084ed38d6028633b539d8143adb5817c97101221ebd5c0b38c` |
| 8.4 | 12027355024 | 4553 | `60bc39b5f4ecefa27c44111842e6fe6b2f36d8bfd55eb1cab4234c024a63c7b9` |

Artifacts, metadata/jobs and all named proof are retained under `work/20i-hosting-dns-contract/qa/`, particularly `pure-matrix-verification.json`. The documentation-only evidence descendant preserves the tested foundation/test/workflow and every shipping file. No PR, native/full dispatch, DNS operation or release occurred. Next obtain the missing package-domain identity and actual read/write response contracts before proposing any application integration.
