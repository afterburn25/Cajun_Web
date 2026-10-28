# Authorize.Net sandbox workflow security review

## Finding and correction

**High severity before correction.** The original PR271 workflows used the workflow-dispatch ref as their checkout source and then exposed the three Authorize.Net sandbox secrets to Python from that ref. The provider probes are standalone and do not load candidate application PHP, so branch selection added no integration assurance. A modified selected branch could instead receive the sandbox credentials.

The correction registers exact reviewed copies of both provider probes on `main` and admits secret-bearing jobs only when `github.ref` is `refs/heads/main`. Checkout is pinned to the event SHA with credential persistence disabled. Both workflows use non-overlapping concurrency groups. SHA-256 checks pin the provider-probe bytes before secret availability is checked, and `tests/sandbox_workflow_contract.py` preserves these boundaries.

## Verified scope

- Both workflow YAML files parse successfully.
- The workflow contract passes.
- Both Python drivers compile.
- Driver SHA-256 values match integration candidate `03c4f606b5e536c7173ebf35200a40715228e527` byte-for-byte.
- The source contracts allow only the Authorize.Net sandbox API and Accept.js hosts and reject the production hosts.
- Repository documentation/dependency checks and whitespace validation pass.

No Authorize.Net request was sent, no secret value was read, and no production switch, release, merge to `main` or deployment is included. The manual runs remain provider-contract evidence only; installed application, ledger, webhook and release acceptance remain separate gates.
