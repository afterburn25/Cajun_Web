"""Fail closed if manual Authorize.Net probes can receive secrets from branch code."""
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
WORKFLOWS = {
    "software-authorize-sandbox.yml": (
        "tests/software_anet_sandbox_acceptance.py",
        "506a880be466ae6b09f4b17ae379248c955054d7eea298ca2c29d76e8d7da129",
    ),
    "license-financing-authorize-sandbox.yml": (
        "tests/finance_anet_sandbox_acceptance.py",
        "a5bc2cd923cae0674d90a20d50c8ecbeb750fa632e93c3b36563ce06853204ff",
    ),
}


for name, (driver, digest) in WORKFLOWS.items():
    source = (ROOT / ".github" / "workflows" / name).read_text(encoding="utf-8")
    required = [
        "workflow_dispatch:",
        "github.ref == 'refs/heads/main'",
        "ref: ${{ github.sha }}",
        "persist-credentials: false",
        "cancel-in-progress: false",
        driver,
        digest,
    ]
    missing = [value for value in required if value not in source]
    if missing:
        raise AssertionError(f"{name} lost sandbox workflow boundary: {missing}")
    if "secrets." not in source:
        raise AssertionError(f"{name} no longer uses approved secret storage")

print("Manual sandbox workflow secret boundary: PASS")
