"""Create sandbox Accept.js nonces on a runner that never receives a transaction key."""
from __future__ import annotations

import argparse
from datetime import date, timedelta
from pathlib import Path
import json
import os
import sys


ACCEPT_JS = "https://jstest.authorize.net/v1/Accept.js"
TEST_CARD = "4007000000027"
TEST_CVV = "900"


def required(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise RuntimeError(f"Missing required sandbox input: {name}")
    return value


def tokenize(page, login: str, client_key: str) -> dict[str, str]:
    expiration = date.today().replace(day=1) + timedelta(days=400)
    page.set_content("<!doctype html><meta charset=utf-8><title>Sandbox tokenization</title>")
    page.add_script_tag(url=ACCEPT_JS)
    page.wait_for_function("window.Accept && typeof window.Accept.dispatchData === 'function'", timeout=30000)
    result = page.evaluate(
        """({login, clientKey, card, month, year, code}) => new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('Accept.js timeout')), 30000);
            window.Accept.dispatchData({
                authData: {apiLoginID: login, clientKey: clientKey},
                cardData: {cardNumber: card, month: month, year: year, cardCode: code}
            }, response => {
                clearTimeout(timer);
                resolve(response);
            });
        })""",
        {
            "login": login,
            "clientKey": client_key,
            "card": TEST_CARD,
            "month": f"{expiration.month:02d}",
            "year": f"{expiration.year % 100:02d}",
            "code": TEST_CVV,
        },
    )
    if not isinstance(result, dict) or result.get("messages", {}).get("resultCode") == "Error":
        messages = result.get("messages", {}).get("message", []) if isinstance(result, dict) else []
        codes = [str(item.get("code", "")) for item in messages if isinstance(item, dict)]
        raise RuntimeError("Accept.js sandbox tokenization failed: " + ",".join(codes))
    opaque = result.get("opaqueData")
    if (
        not isinstance(opaque, dict)
        or opaque.get("dataDescriptor") != "COMMON.ACCEPT.INAPP.PAYMENT"
        or not isinstance(opaque.get("dataValue"), str)
        or not 8 <= len(opaque["dataValue"]) <= 4096
    ):
        raise RuntimeError("Accept.js sandbox did not return a valid opaque payment token")
    return {"dataDescriptor": opaque["dataDescriptor"], "dataValue": opaque["dataValue"]}


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--count", type=int, choices=range(1, 9), required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    login = required("ANET_SANDBOX_API_LOGIN_ID")
    client_key = required("ANET_SANDBOX_PUBLIC_CLIENT_KEY")
    if "ANET_SANDBOX_TRANSACTION_KEY" in os.environ:
        raise RuntimeError("Tokenization runner must not receive the sandbox transaction key")
    from playwright.sync_api import sync_playwright

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        try:
            tokens = [tokenize(browser.new_page(), login, client_key) for _ in range(args.count)]
        finally:
            browser.close()
    args.output.parent.mkdir(parents=True, exist_ok=True)
    descriptor = os.open(args.output, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(descriptor, "w", encoding="utf-8") as handle:
        json.dump({"tokens": tokens}, handle, separators=(",", ":"))
        handle.write("\n")
    print(f"Authorize.net sandbox tokenization: PASS ({args.count} opaque nonces retained for the next isolated job)")


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(f"Sandbox tokenization failed: {type(exc).__name__}: {exc}", file=sys.stderr)
        raise
