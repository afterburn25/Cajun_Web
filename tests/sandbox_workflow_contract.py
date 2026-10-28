"""Regression pins for reviewed sandbox workflow and credential-boundary bytes."""
from hashlib import sha256
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
CHECKOUT = "actions/checkout@11d5960a326750d5838078e36cf38b85af677262"
UPLOAD = "actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02"
DOWNLOAD = "actions/download-artifact@d3f86a106a0bac45b974a628896c90dbdf5c8093"
REVIEWED_FILES = {
    ".github/workflows/software-authorize-sandbox.yml": "79024fd1503d308b7b67a8f6eb76c33360989a8e9ad409989d31c99d52ccd7f4",
    ".github/workflows/license-financing-authorize-sandbox.yml": "88a172f065e70b780cd7746dec2f12cd82fa2f4eef620108cf56b2f21db3b34b",
    ".github/workflows/sandbox-workflow-security.yml": "ac20f94c10c30422b54d80379ab6639158365c0430995e588f7573fdda6d4764",
    "tests/software_anet_sandbox_acceptance.py": "21fa7504f6780ad665328f51bc516e86f60dd4a516ce4064fb14741ea7e74e83",
    "tests/finance_anet_sandbox_acceptance.py": "685e9e27e4ee727624dab859b9b44715ec5214e09474e08e53efc58797c867d3",
    "tests/anet_sandbox_runtime.py": "df4ba0e1696ee7a7457c84ed28276a2dbe143bf39e36d56b5372150998215e81",
    "tests/anet_sandbox_tokenize.py": "f7cf297d49527f0d83759f4fecf5c35e68f18ecd07f8bc86493f03233fbff400",
    "tests/anet_sandbox_runtime_contract.py": "ed7f8d8130caa5911538e77373f42fb539b408dee7398b6fae3ce47a1a7e0d75",
}
WORKFLOWS = {
    "software-authorize-sandbox.yml": "  authorize-sandbox:\n",
    "license-financing-authorize-sandbox.yml": "  finance-sandbox:\n",
}


for name, expected in REVIEWED_FILES.items():
    actual = sha256((ROOT / name).read_bytes()).hexdigest()
    if actual != expected:
        raise AssertionError(f"{name} differs from reviewed sandbox source; review before updating its pin")

for name, gateway_marker in WORKFLOWS.items():
    source = (ROOT / ".github" / "workflows" / name).read_text(encoding="utf-8")
    required = [
        "workflow_dispatch:",
        "sandbox-dispatch-boundary:",
        "test \"$DISPATCH_REF\" = 'refs/heads/main'",
        "  sandbox-tokenize:\n",
        "environment: authorize-net-sandbox",
        "playwright==1.64.1",
        CHECKOUT,
        UPLOAD,
        DOWNLOAD,
        "retention-days: 1",
        gateway_marker,
        "ANET_SANDBOX_TOKEN_FILE:",
        "cancel-in-progress: false",
    ]
    missing = [value for value in required if value not in source]
    if missing:
        raise AssertionError(f"{name} lost sandbox workflow boundary: {missing}")
    token_job, gateway_job = source.split(gateway_marker, 1)
    if "ANET_SANDBOX_TRANSACTION_KEY" in token_job:
        raise AssertionError(f"{name} exposes the transaction key to browser dependency code")
    if "pip install" in gateway_job or "playwright" in gateway_job or "requests" in gateway_job:
        raise AssertionError(f"{name} executes downloaded Python code on the transaction-key runner")
    if "@v4" in source:
        raise AssertionError(f"{name} uses a mutable action tag")

runtime = (ROOT / "tests" / "anet_sandbox_runtime.py").read_text(encoding="utf-8")
if "import requests" in runtime or "playwright" in runtime or "https://api.authorize.net" in runtime:
    raise AssertionError("Credential-bearing sandbox runtime lost its standard-library/test-endpoint boundary")
tokenizer = (ROOT / "tests" / "anet_sandbox_tokenize.py").read_text(encoding="utf-8")
if 'if "ANET_SANDBOX_TRANSACTION_KEY" in os.environ' not in tokenizer:
    raise AssertionError("Tokenization driver no longer refuses an injected transaction key")

print("Reviewed sandbox workflows isolate browser dependencies from the transaction-key runners: PASS")
