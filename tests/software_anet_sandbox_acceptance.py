"""Real Authorize.net sandbox acceptance.

Manual CI only. Uses sandbox-only endpoints and Authorize.net's documented
sandbox card. Never prints merchant credentials, card data, or opaque nonces.
"""
from __future__ import annotations

from datetime import date, timedelta
from pathlib import Path
import json
import os
import secrets
import sys

import requests
from playwright.sync_api import sync_playwright

ACCEPT_JS = "https://jstest.authorize.net/v1/Accept.js"
API = "https://apitest.authorize.net/xml/v1/request.api"
TEST_CARD = "4007000000027"  # Authorize.net documented sandbox Visa.
TEST_CVV = "900"             # Documented sandbox CVV successful-match trigger.
AMOUNT = "1.23"


def required(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise RuntimeError(f"Missing required sandbox secret: {name}")
    return value


def gateway(method: str, body: dict, login: str, key: str) -> dict:
    payload = {
        method: {
            "merchantAuthentication": {
                "name": login,
                "transactionKey": key,
            },
            **body,
        }
    }
    response = requests.post(
        API,
        json=payload,
        timeout=30,
        allow_redirects=False,
        headers={"Content-Type": "application/json", "User-Agent": "CajunWebPro-sandbox-acceptance/1"},
    )
    if response.status_code != 200:
        raise RuntimeError(f"Authorize.net sandbox HTTP status {response.status_code}")
    text = response.content.lstrip(b"\xef\xbb\xbf").decode("utf-8")
    obj = json.loads(text)
    if not isinstance(obj, dict):
        raise RuntimeError("Authorize.net sandbox returned an invalid JSON envelope")
    return obj


def result_ok(reply: dict) -> bool:
    return reply.get("messages", {}).get("resultCode") == "Ok"


def tokenize(login: str, client_key: str) -> dict:
    expiration = date.today().replace(day=1) + timedelta(days=400)
    month = f"{expiration.month:02d}"
    year = f"{expiration.year % 100:02d}"
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        try:
            page = browser.new_page()
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
                    "month": month,
                    "year": year,
                    "code": TEST_CVV,
                },
            )
        finally:
            browser.close()
    if not isinstance(result, dict) or result.get("messages", {}).get("resultCode") == "Error":
        messages = result.get("messages", {}).get("message", []) if isinstance(result, dict) else []
        codes = [str(item.get("code", "")) for item in messages if isinstance(item, dict)]
        raise RuntimeError("Accept.js sandbox tokenization failed: " + ",".join(codes))
    opaque = result.get("opaqueData")
    if (
        not isinstance(opaque, dict)
        or opaque.get("dataDescriptor") != "COMMON.ACCEPT.INAPP.PAYMENT"
        or not isinstance(opaque.get("dataValue"), str)
        or len(opaque["dataValue"]) < 8
    ):
        raise RuntimeError("Accept.js sandbox did not return a valid opaque payment token")
    return {
        "dataDescriptor": opaque["dataDescriptor"],
        "dataValue": opaque["dataValue"],
    }


def main() -> None:
    login = required("ANET_SANDBOX_API_LOGIN_ID")
    transaction_key = required("ANET_SANDBOX_TRANSACTION_KEY")
    client_key = required("ANET_SANDBOX_PUBLIC_CLIENT_KEY")

    run_id = os.environ.get("GITHUB_RUN_ID") or secrets.token_hex(5)
    invoice = ("CWP-SBX-" + run_id)[-20:]
    ref = ("SBX" + run_id)[-20:]

    # 1) Real browser -> Accept.js -> sandbox nonce.
    opaque = tokenize(login, client_key)

    # 2) Real sandbox transaction using only the one-use nonce on the server side.
    charge = gateway(
        "createTransactionRequest",
        {
            "refId": ref,
            "transactionRequest": {
                "transactionType": "authCaptureTransaction",
                "amount": AMOUNT,
                "payment": {"opaqueData": opaque},
                "order": {"invoiceNumber": invoice, "description": "CajunWebPro sandbox acceptance"},
            },
        },
        login,
        transaction_key,
    )
    transaction = charge.get("transactionResponse") or {}
    trans_id = str(transaction.get("transId") or "")
    if not result_ok(charge) or str(transaction.get("responseCode") or "") != "1" or not trans_id.isdigit():
        code = transaction.get("responseCode")
        raise RuntimeError(f"Sandbox transaction was not approved; responseCode={code!r}")

    # 3) Independent readback shape used by production reconciliation.
    details = gateway("getTransactionDetailsRequest", {"transId": trans_id}, login, transaction_key)
    tx = details.get("transaction") or {}
    if (
        not result_ok(details)
        or str(tx.get("transId") or "") != trans_id
        or str(tx.get("order", {}).get("invoiceNumber") or "") != invoice
        or f"{float(tx.get('authAmount')):.2f}" != AMOUNT
        or str(tx.get("transactionStatus") or "") not in {"capturedPendingSettlement", "settledSuccessfully"}
    ):
        raise RuntimeError("Sandbox transaction readback does not match the created invoice/amount/status")

    # 4) Fresh nonce for ARB because Accept.js opaque data is one-use.
    arb_opaque = tokenize(login, client_key)
    subscription_invoice = ("CWP-ARB-" + run_id)[-20:]
    subscription_name = ("CajunWebPro SBX " + run_id)[-50:]
    start_date = (date.today() + timedelta(days=2)).isoformat()
    created = gateway(
        "ARBCreateSubscriptionRequest",
        {
            "refId": ("ARB" + run_id)[-20:],
            "subscription": {
                "name": subscription_name,
                "paymentSchedule": {
                    "interval": {"length": 1, "unit": "months"},
                    "startDate": start_date,
                    "totalOccurrences": 9999,
                },
                "amount": AMOUNT,
                "payment": {"opaqueData": arb_opaque},
                "order": {"invoiceNumber": subscription_invoice},
            },
        },
        login,
        transaction_key,
    )
    subscription_id = str(created.get("subscriptionId") or "")
    if not result_ok(created) or not subscription_id.isdigit():
        raise RuntimeError("Sandbox ARB subscription creation failed")

    subscription = gateway(
        "ARBGetSubscriptionRequest",
        {"subscriptionId": subscription_id, "includeTransactions": False},
        login,
        transaction_key,
    )
    sub = subscription.get("subscription") or {}
    schedule = sub.get("paymentSchedule") or {}
    interval = schedule.get("interval") or {}
    if (
        not result_ok(subscription)
        or str(sub.get("name") or "") != subscription_name
        or f"{float(sub.get('amount')):.2f}" != AMOUNT
        or str(interval.get("length") or "") != "1"
        or str(interval.get("unit") or "") != "months"
        or str(schedule.get("startDate") or "") != start_date
        or str(schedule.get("totalOccurrences") or "") != "9999"
        or str(sub.get("order", {}).get("invoiceNumber") or "") != subscription_invoice
        or str(sub.get("status") or "") not in {"active", "suspended"}
    ):
        raise RuntimeError("Sandbox ARB readback does not match the created subscription contract")

    cancelled = gateway(
        "ARBCancelSubscriptionRequest",
        {"subscriptionId": subscription_id},
        login,
        transaction_key,
    )
    if not result_ok(cancelled):
        raise RuntimeError("Sandbox ARB cleanup cancellation failed")

    # Best-effort cleanup for the one-time transaction while it is unsettled.
    void_result = "not_attempted"
    try:
        void = gateway(
            "createTransactionRequest",
            {
                "refId": ("VOID" + run_id)[-20:],
                "transactionRequest": {
                    "transactionType": "voidTransaction",
                    "refTransId": trans_id,
                },
            },
            login,
            transaction_key,
        )
        void_result = "ok" if result_ok(void) else "not_voidable"
    except Exception:
        void_result = "transport_or_state_error"

    report = {
        "scope": "Authorize.net sandbox only; no live payment processing",
        "accept_js": "sandbox",
        "api_endpoint": API,
        "test_card_source": "Authorize.net sandbox testing guide",
        "tokenization_passed": True,
        "transaction_approved": True,
        "transaction_readback_passed": True,
        "transaction_status": str(tx.get("transactionStatus")),
        "arb_created": True,
        "arb_readback_passed": True,
        "arb_cancelled": True,
        "one_time_cleanup": void_result,
        "credentials_or_nonce_recorded": False,
        "passed": True,
    }
    out = Path("qa/software-anet-sandbox-acceptance.json")
    out.parent.mkdir(exist_ok=True)
    out.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print("Authorize.net real sandbox tokenization / transaction / ARB acceptance: PASS")


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        # Never print environment values, card data, or opaque tokens.
        print(f"Sandbox acceptance failed: {type(exc).__name__}: {exc}", file=sys.stderr)
        raise
