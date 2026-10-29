"""Offline regressions for the fresh-runner Authorize.net sandbox boundary."""
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest.mock import patch
import json
import os
import subprocess
import sys

import anet_sandbox_runtime as runtime


checks = []


def check(name, condition):
    checks.append(name)
    if not condition:
        raise AssertionError(name)


def denied(name, callback):
    try:
        callback()
    except (RuntimeError, ValueError, json.JSONDecodeError):
        checks.append(name)
    else:
        raise AssertionError(name)


token = {"dataDescriptor": "COMMON.ACCEPT.INAPP.PAYMENT", "dataValue": "opaque-token-123"}
with TemporaryDirectory(prefix="cwp-sandbox-runtime-") as directory:
    path = Path(directory) / "tokens.json"
    path.write_text(json.dumps({"tokens": [token, token]}) + "\n", encoding="utf-8")
    with patch.dict(os.environ, {"ANET_SANDBOX_TOKEN_FILE": str(path)}, clear=True):
        check("Exact opaque-token handoff accepted", runtime.load_tokens(2) == [token, token])
        denied("Unexpected token count rejected", lambda: runtime.load_tokens(1))
        path.write_text(json.dumps({"tokens": [token], "extra": True}), encoding="utf-8")
        denied("Unexpected handoff field rejected", lambda: runtime.load_tokens(1))
        path.write_text(json.dumps({"tokens": [{**token, "dataValue": "bad\nvalue"}]}), encoding="utf-8")
        denied("Control character in opaque token rejected", lambda: runtime.load_tokens(1))
        path.write_bytes(b"x" * 65537)
        denied("Oversized token handoff rejected", lambda: runtime.load_tokens(1))


class Response:
    status = 200

    def __init__(self, url=runtime.API):
        self.url = url

    def __enter__(self):
        return self

    def __exit__(self, *args):
        return False

    def geturl(self):
        return self.url

    def read(self):
        return b'\xef\xbb\xbf{"messages":{"resultCode":"Ok"}}'


class Opener:
    def __init__(self, response):
        self.response = response
        self.request = None

    def open(self, request, timeout):
        self.request = request
        check("Finite sandbox timeout retained", timeout == 30)
        return self.response


opener = Opener(Response())
with patch.object(runtime, "build_opener", return_value=opener):
    reply = runtime.gateway("testRequest", {"refId": "fixed"}, "login", "key", "contract/1")
check("Standard-library sandbox envelope accepted", runtime.result_ok(reply))
check("Only sandbox API endpoint requested", opener.request.full_url == runtime.API)
check("Sandbox request is POST", opener.request.get_method() == "POST")
payload = json.loads(opener.request.data.decode("utf-8"))
check("Authentication stays inside exact request method", payload == {"testRequest": {"merchantAuthentication": {"name": "login", "transactionKey": "key"}, "refId": "fixed"}})

with patch.object(runtime, "build_opener", return_value=Opener(Response("https://example.invalid/redirect"))):
    denied("Redirected sandbox response rejected", lambda: runtime.gateway("testRequest", {}, "login", "key", "contract/1"))

root = Path(__file__).resolve().parents[1]
with TemporaryDirectory(prefix="cwp-sandbox-tokenizer-") as directory:
    output = Path(directory) / "must-not-exist.json"
    refused = subprocess.run(
        [sys.executable, str(root / "tests" / "anet_sandbox_tokenize.py"), "--count", "1", "--output", str(output)],
        env={
            **os.environ,
            "ANET_SANDBOX_API_LOGIN_ID": "public-login",
            "ANET_SANDBOX_PUBLIC_CLIENT_KEY": "public-client-key",
            "ANET_SANDBOX_TRANSACTION_KEY": "must-not-reach-browser-code",
        },
        capture_output=True,
        text=True,
    )
    check("Tokenizer refuses transaction key before importing browser dependencies", refused.returncode != 0 and "must not receive" in refused.stderr and not output.exists())

for name in ("software_anet_sandbox_acceptance.py", "finance_anet_sandbox_acceptance.py"):
    source = (root / "tests" / name).read_text(encoding="utf-8")
    check(name + " has no browser dependency", "playwright" not in source and "requests" not in source)
    check(name + " does not receive public browser key", "ANET_SANDBOX_PUBLIC_CLIENT_KEY" not in source)

print(f"Authorize.net sandbox isolated-runtime contract: PASS ({len(checks)} checks)")
