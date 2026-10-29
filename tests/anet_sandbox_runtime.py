"""Standard-library-only helpers for credential-bearing sandbox API steps."""
from __future__ import annotations

from decimal import Decimal, InvalidOperation
from pathlib import Path
import json
import os
from urllib.error import HTTPError
from urllib.request import HTTPRedirectHandler, Request, build_opener


API = "https://apitest.authorize.net/xml/v1/request.api"


class _NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def required(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise RuntimeError(f"Missing required sandbox input: {name}")
    return value


def gateway(method: str, body: dict, login: str, key: str, user_agent: str) -> dict:
    payload = json.dumps({
        method: {
            "merchantAuthentication": {"name": login, "transactionKey": key},
            **body,
        }
    }).encode("utf-8")
    request = Request(
        API,
        data=payload,
        method="POST",
        headers={"Content-Type": "application/json", "User-Agent": user_agent},
    )
    try:
        with build_opener(_NoRedirect).open(request, timeout=30) as response:
            status = response.status
            final_url = response.geturl()
            raw = response.read()
    except HTTPError as exc:
        raise RuntimeError(f"Authorize.net sandbox HTTP status {exc.code}") from None
    if status != 200 or final_url != API:
        raise RuntimeError(f"Authorize.net sandbox HTTP status {status}")
    obj = json.loads(raw.lstrip(b"\xef\xbb\xbf").decode("utf-8"))
    if not isinstance(obj, dict):
        raise RuntimeError("Authorize.net sandbox returned an invalid JSON envelope")
    return obj


def result_ok(reply: dict) -> bool:
    return reply.get("messages", {}).get("resultCode") == "Ok"


def exact_amount(value) -> str:
    try:
        amount = Decimal(str(value))
    except (InvalidOperation, ValueError):
        raise RuntimeError("Authorize.net sandbox returned an invalid decimal amount")
    if not amount.is_finite() or amount.as_tuple().exponent < -2 or amount < 0:
        raise RuntimeError("Authorize.net sandbox returned unsupported amount precision")
    return f"{amount:.2f}"


def load_tokens(expected: int) -> list[dict[str, str]]:
    path = Path(required("ANET_SANDBOX_TOKEN_FILE"))
    if path.is_symlink() or not path.is_file() or path.stat().st_size > 65536:
        raise RuntimeError("Sandbox token handoff is not a bounded regular file")
    document = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(document, dict) or set(document) != {"tokens"}:
        raise RuntimeError("Sandbox token handoff has an invalid envelope")
    tokens = document["tokens"]
    if not isinstance(tokens, list) or len(tokens) != expected:
        raise RuntimeError("Sandbox token handoff has an invalid token count")
    clean = []
    for token in tokens:
        if (
            not isinstance(token, dict)
            or set(token) != {"dataDescriptor", "dataValue"}
            or token.get("dataDescriptor") != "COMMON.ACCEPT.INAPP.PAYMENT"
            or not isinstance(token.get("dataValue"), str)
            or not 8 <= len(token["dataValue"]) <= 4096
            or any(ord(character) < 33 or ord(character) > 126 for character in token["dataValue"])
        ):
            raise RuntimeError("Sandbox token handoff contains an invalid opaque token")
        clean.append(token)
    return clean
