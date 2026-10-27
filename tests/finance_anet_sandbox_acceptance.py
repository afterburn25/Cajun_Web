"""Real Authorize.net sandbox acceptance for financed-site payment shapes.

Manual CI only. Exercises sandbox Accept.js, an initial-payment transaction,
a finite installment subscription, cancellation, and a finite replacement.
Never prints or artifacts merchant credentials, card data, or opaque nonces.
"""
from __future__ import annotations

from datetime import date, timedelta
from decimal import Decimal, InvalidOperation
from pathlib import Path
import json
import os
import secrets
import sys

import requests
from playwright.sync_api import sync_playwright

ACCEPT_JS = "https://jstest.authorize.net/v1/Accept.js"
API = "https://apitest.authorize.net/xml/v1/request.api"
TEST_CARD = "4007000000027"
TEST_CVV = "900"
DOWN_PAYMENT = "2.22"
INSTALLMENT = "1.11"


def required(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise RuntimeError(f"Missing required sandbox secret: {name}")
    return value


def gateway(method: str, body: dict, login: str, key: str) -> dict:
    response = requests.post(
        API,
        json={
            method: {
                "merchantAuthentication": {"name": login, "transactionKey": key},
                **body,
            }
        },
        timeout=30,
        allow_redirects=False,
        headers={"Content-Type": "application/json", "User-Agent": "CajunWebPro-finance-sandbox/1"},
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


def exact_amount(value) -> str:
    try:
        amount = Decimal(str(value))
    except (InvalidOperation, ValueError):
        raise RuntimeError("Authorize.net sandbox returned an invalid decimal amount")
    if amount.as_tuple().exponent < -2 or amount < 0:
        raise RuntimeError("Authorize.net sandbox returned unsupported amount precision")
    return f"{amount:.2f}"


def tokenize(login: str, client_key: str) -> dict:
    expiration = date.today().replace(day=1) + timedelta(days=400)
    month = f"{expiration.month:02d}"
    year = f"{expiration.year % 100:02d}"
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        try:
            page = browser.new_page()
            page.set_content("<!doctype html><meta charset=utf-8><title>Finance sandbox tokenization</title>")
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
        raise RuntimeError("Accept.js finance sandbox tokenization failed: " + ",".join(codes))
    opaque = result.get("opaqueData")
    if (
        not isinstance(opaque, dict)
        or opaque.get("dataDescriptor") != "COMMON.ACCEPT.INAPP.PAYMENT"
        or not isinstance(opaque.get("dataValue"), str)
        or len(opaque["dataValue"]) < 8
    ):
        raise RuntimeError("Accept.js finance sandbox did not return a valid opaque payment token")
    return {"dataDescriptor": opaque["dataDescriptor"], "dataValue": opaque["dataValue"]}


def read_subscription(subscription_id: str, login: str, key: str) -> dict:
    reply = gateway(
        "ARBGetSubscriptionRequest",
        {"subscriptionId": subscription_id, "includeTransactions": False},
        login,
        key,
    )
    if not result_ok(reply):
        raise RuntimeError("Finance sandbox subscription readback failed")
    return reply.get("subscription") or {}


def create_subscription(
    *,
    label: str,
    invoice: str,
    start_date: str,
    occurrences: int,
    amount: str,
    login: str,
    transaction_key: str,
    client_key: str,
) -> str:
    opaque = tokenize(login, client_key)
    created = gateway(
        "ARBCreateSubscriptionRequest",
        {
            "refId": label[-20:],
            "subscription": {
                "name": ("CajunWebPro finance " + label)[-50:],
                "paymentSchedule": {
                    "interval": {"length": 1, "unit": "months"},
                    "startDate": start_date,
                    "totalOccurrences": occurrences,
                },
                "amount": amount,
                "payment": {"opaqueData": opaque},
                "order": {"invoiceNumber": invoice},
            },
        },
        login,
        transaction_key,
    )
    subscription_id = str(created.get("subscriptionId") or "")
    if not result_ok(created) or not subscription_id.isdigit():
        raise RuntimeError("Finance sandbox ARB subscription creation failed")
    sub = read_subscription(subscription_id, login, transaction_key)
    schedule = sub.get("paymentSchedule") or {}
    interval = schedule.get("interval") or {}
    if (
        exact_amount(sub.get("amount")) != amount
        or str(interval.get("length") or "") != "1"
        or str(interval.get("unit") or "") != "months"
        or str(schedule.get("startDate") or "") != start_date
        or str(schedule.get("totalOccurrences") or "") != str(occurrences)
        or str(sub.get("order", {}).get("invoiceNumber") or "") != invoice
        or str(sub.get("status") or "") not in {"active", "suspended"}
    ):
        raise RuntimeError("Finance sandbox ARB readback does not match the finite signed contract")
    return subscription_id


def cancel_subscription(subscription_id: str, login: str, key: str) -> str:
    reply = gateway("ARBCancelSubscriptionRequest", {"subscriptionId": subscription_id}, login, key)
    if not result_ok(reply):
        raise RuntimeError("Finance sandbox ARB cancellation failed")
    sub = read_subscription(subscription_id, login, key)
    status = str(sub.get("status") or "").lower()
    if status not in {"canceled", "cancelled", "terminated"}:
        raise RuntimeError("Finance sandbox ARB cancellation was not confirmed by independent readback")
    return status


def main() -> None:
    login = required("ANET_SANDBOX_API_LOGIN_ID")
    transaction_key = required("ANET_SANDBOX_TRANSACTION_KEY")
    client_key = required("ANET_SANDBOX_PUBLIC_CLIENT_KEY")
    run_id = os.environ.get("GITHUB_RUN_ID") or secrets.token_hex(5)

    down_invoice = ("FIN-DOWN-" + run_id)[-20:]
    down_opaque = tokenize(login, client_key)
    charge = gateway(
        "createTransactionRequest",
        {
            "refId": ("FD" + run_id)[-20:],
            "transactionRequest": {
                "transactionType": "authCaptureTransaction",
                "amount": DOWN_PAYMENT,
                "payment": {"opaqueData": down_opaque},
                "order": {
                    "invoiceNumber": down_invoice,
                    "description": "CajunWebPro finance sandbox initial payment",
                },
            },
        },
        login,
        transaction_key,
    )
    tx = charge.get("transactionResponse") or {}
    trans_id = str(tx.get("transId") or "")
    if not result_ok(charge) or str(tx.get("responseCode") or "") != "1" or not trans_id.isdigit():
        raise RuntimeError("Finance sandbox initial payment was not approved")

    details = gateway("getTransactionDetailsRequest", {"transId": trans_id}, login, transaction_key)
    readback = details.get("transaction") or {}
    if (
        not result_ok(details)
        or str(readback.get("transId") or "") != trans_id
        or str(readback.get("order", {}).get("invoiceNumber") or "") != down_invoice
        or exact_amount(readback.get("authAmount")) != DOWN_PAYMENT
        or str(readback.get("transactionStatus") or "") not in {"capturedPendingSettlement", "settledSuccessfully"}
    ):
        raise RuntimeError("Finance sandbox initial-payment readback does not match invoice/amount/status")

    original_start = (date.today() + timedelta(days=2)).isoformat()
    original_invoice = ("FIN-ARB-" + run_id)[-20:]
    original_id = create_subscription(
        label="FIN-ORIG-" + run_id,
        invoice=original_invoice,
        start_date=original_start,
        occurrences=4,
        amount=INSTALLMENT,
        login=login,
        transaction_key=transaction_key,
        client_key=client_key,
    )
    original_cancel_status = cancel_subscription(original_id, login, transaction_key)

    # Replacement shape mirrors the retained financing logic: the old identity
    # is retired and the replacement contains only the remaining signed periods.
    replacement_start = (date.today() + timedelta(days=33)).isoformat()
    replacement_invoice = ("FIN-REPL-" + run_id)[-20:]
    replacement_id = create_subscription(
        label="FIN-REPL-" + run_id,
        invoice=replacement_invoice,
        start_date=replacement_start,
        occurrences=3,
        amount=INSTALLMENT,
        login=login,
        transaction_key=transaction_key,
        client_key=client_key,
    )
    replacement_cancel_status = cancel_subscription(replacement_id, login, transaction_key)

    cleanup = "not_attempted"
    try:
        void = gateway(
            "createTransactionRequest",
            {
                "refId": ("FVOID" + run_id)[-20:],
                "transactionRequest": {"transactionType": "voidTransaction", "refTransId": trans_id},
            },
            login,
            transaction_key,
        )
        cleanup = "ok" if result_ok(void) else "not_voidable"
    except Exception:
        cleanup = "transport_or_state_error"

    report = {
        "scope": "Authorize.net sandbox financing shapes only; no live payment processing",
        "accept_js": "sandbox",
        "api_endpoint": API,
        "initial_payment_approved": True,
        "initial_payment_readback_passed": True,
        "original_installments": 4,
        "original_arb_created": True,
        "original_arb_cancelled": True,
        "original_cancel_status": original_cancel_status,
        "replacement_installments": 3,
        "replacement_arb_created": True,
        "replacement_arb_cancelled": True,
        "replacement_cancel_status": replacement_cancel_status,
        "one_time_cleanup": cleanup,
        "credentials_or_nonce_recorded": False,
        "passed": True,
    }
    out = Path("qa/finance-anet-sandbox-acceptance.json")
    out.parent.mkdir(exist_ok=True)
    out.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print("Authorize.net real sandbox finance initial-payment / finite-ARB / replacement acceptance: PASS")


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(f"Finance sandbox acceptance failed: {type(exc).__name__}: {exc}", file=sys.stderr)
        raise
