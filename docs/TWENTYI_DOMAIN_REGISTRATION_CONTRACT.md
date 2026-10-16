# Domain registration draft contract

Base: frozen a63cc18ecfdb6d22ba1c138809c6e7bd01659d1a. This increment is a pure component under `foundation/twentyi-domain-registration`, outside the shipping tree. It adds no route, schema, sender, credential resolver, operation admission, charge or release.

## Supported evidence

The owner's full API reference has SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95` (195,404 bytes). Lines57–122 describe POST `/reseller/{resellerId}/addDomain`; line1 documents the self-account alias `*`. The registration call charges the registration fee to the reseller balance. The illustrated keys include name, contact, nameservers, privacyService, caRegistryAgreement, otherContacts, limits and stackUser.

That registration section **does not identify a term/year request field or a return schema**. The component does not invent either. `documentedFields()` is a field-review projection, not a complete request body; no method returns a TransportRequest. Requested years remain solely in the local draft.

The contact example explicitly names organisation, name, address, telephone, email, cc, pc, sp, city and23 string extension keys. The component validates bounded text and those exact keys. It does not establish which fields, values or extensions a particular registry requires, or whether that registry will accept the contact. Country codes are two uppercase ASCII letters syntactically, not an asserted country entitlement.

The reference excludes IDNs. This local subset rejects non-ASCII domains and xn-- labels, requires lower-case canonical DNS names, and refuses IP addresses. Local limits (1–10 requested years,1–13 explicit nameservers and bounded contact text) are application policy, not provider limits. Nameserver ordering is preserved; duplicate nameservers are refused. No default nameserver behavior is inferred from the transfer endpoint.

Privacy is an explicit boolean choice and remains distinct from payment, contact consent and WHOIS suppression. The reference specifically warns that .uk privacy is a chargeable add-on distinct from suppressing disclosure. A .ca draft may exist without a registry acceptance record. A recorded acceptance must carry the exact domain, direct `client:<id>` identity, agreement-text SHA256 and recorded epoch before the review projection can contain `caRegistryAgreement:true`. This is a typed supplied assertion; only future authenticated native journals can prove the registrant actually accepted the presented agreements. It is not legal sufficiency or remote acceptance evidence.

Lines354–362 describe a **different contact-update endpoint** and registry mappings. Its Nominet enum and Tucows mappings are not automatically applied to addDomain. The registration example provides field names but not complete per-registry accepted values. Empty extension remains the supplied example's empty PHP array; unknown extension names, nulls and arbitrary nested structures are refused in this narrow subset.

## Distinct transfer evidence

Lines1186–1230 describe transferDomain years, emulateYears, authcode, optional contacts, limits, nameservers, privacy and StackCP grants. Transfer true means purchase accepted, not completed transfer. In particular, UK transfer years may be unset/zero, and emulateYears can create a transfer-and-renew effect.

The retained provider-authored2018 SDK at commit `696a5f1299cd2b1e67ae7f688d644ac800f41b67`, [transfer example](https://github.com/LunarDevelopment/20iRestModule/blob/696a5f1299cd2b1e67ae7f688d644ac800f41b67/test-real/real-00-transfer-domain.php), accesses `$response->result` after transfer. Its REST wrapper directly decodes JSON. That differs from assuming a bare boolean based on the current prose. Neither is copied into registration. The SDK is retained redistributed provider source, not current-account acceptance.

The existing canonical operation register leaves both domains.register and domains.transfer shape-incomplete and excludes generic approval. Tests verify that remains true.

## Implemented pure behavior

- RegistrationContact accepts only the documented bounded contact field names and23 named extensions; it returns detached private data only through explicit export.
- RegistrationDraft captures exact local client/name/term/privacy/contact/nameserver/CA-record scope, canonicalizes maps, and rejects noncanonical or duplicate-key JSON during restoration. Fingerprints are equality checks, not authorization signatures.
- Normal JSON serialization, var_dump and print_r redact private fields; implicit PHP serialization refuses. Explicit private export is intentionally sensitive and requires encrypted native storage. Arbitrary trusted PHP reflection, var_export or logging an explicit private export is not covered.
- RegistrationContract records the documented operation and route, enumerates unresolved prerequisites, and never permits dispatch. Any supplied HTTP status or response body remains unverified, with registered:null, no retry authority and no assertion that a balance charge did not occur.

No raw contact, credential or response data appears in exception messages or pure reports. The component receives no provider credential. Future adapter classification must reject credential/contact echoes while sensitive values are still locally owned.

## Verification and remaining work

The first checkpoint has252 distinct pure cases in each local PHP mode (normal and no-INI), covering immutable reference detachment, exact restoration, private diagnostics, contact syntax, all documented extension names, term/privacy separation, CA-domain/client binding, unavailable registration outcomes and unchanged generic refusal. Source manifest and retained reports are under ignored `qa/registration-contract`; root source review is pending. No native database, browser, live API or current registry acceptance is claimed.

[Native prerequisites and proposed integration seams](TWENTYI_DOMAIN_REGISTRATION_NATIVE_PREREQUISITES.md) distinguish remaining engineering from exact missing provider input.
