"""Regression pins for reviewed workflow/driver bytes, not an authorization boundary.

GitHub environment policy and environment-only secrets enforce secret access.
Changing these pins requires review of the new workflow and driver bytes.
"""
from hashlib import sha256
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
REVIEWED_FILES = {
    '.github/workflows/software-authorize-sandbox.yml': '90485056a70734af75041bb8e527551e2a9f29cb6aa0b6f270075a015eb8fcfe',
    'tests/software_anet_sandbox_acceptance.py': '506a880be466ae6b09f4b17ae379248c955054d7eea298ca2c29d76e8d7da129',
    '.github/workflows/license-financing-authorize-sandbox.yml': '50b619b0b246d531e1ecdb4acd335fbc1e70baba6987a29124efa36e0bf88db2',
    'tests/finance_anet_sandbox_acceptance.py': 'a5bc2cd923cae0674d90a20d50c8ecbeb750fa632e93c3b36563ce06853204ff',
}


for name, expected in REVIEWED_FILES.items():
    actual = sha256((ROOT / name).read_bytes()).hexdigest()
    if actual != expected:
        raise AssertionError(f"{name} differs from reviewed sandbox source; review before updating its pin")

print("Reviewed manual sandbox workflow/driver bytes: PASS (environment policy is a separate gate)")
