# Owner API usage supplement - 2026-10-16

The owner supplied these PHP usage examples in response to the pending question about the `addDomain` registration-term field and response schema. These examples are a separate evidence item from the complete reference SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95`; that original reference is unchanged.

The examples initialize `TwentyI\API\Services` with the literal placeholder `your_api_token`. They fetch `/domain`, select a domain by case-insensitive `name`, use its `id`, fetch `/domain/{id}/nameservers` and read `->result`, then post to the same path with `ns` set to the replacement nameserver array and `old-ns` set to that previously read result. A second example fetches `/domain-search/mybusinessname` and prints its result. Both use example domain names and contain no real credentials or customer records.

This supports the supplied nameserver read/update request sequence and domain-search path. It does not show the nameserver mutation acknowledgement or domain-search response structure. The `addDomain` registration-term field and registration response grammar are still absent. No registration term, success wrapper, charge result, retry safety, current pricing or provider eligibility is inferred. No example was executed.

Private [registration drafts](TWENTYI_REGISTRATION_DRAFTS.md) continue independently. The [pure registration contract](TWENTYI_DOMAIN_REGISTRATION_CONTRACT.md) keeps its unverified dispatch boundary. Existing nameserver/search implementations and their source-bound tests retain their own evidence; this supplement is documentation, not new live account acceptance.
