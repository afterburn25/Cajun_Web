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

from anet_sandbox_runtime import API, exact_amount, gateway as runtime_gateway, load_tokens, required, result_ok

AMOUNT = "1.23"


def gateway(method: str, body: dict, login: str, key: str) -> dict:
    return runtime_gateway(method, body, login, key, "CajunWebPro-sandbox-acceptance/1")


def main() -> None:
    login = required("ANET_SANDBOX_API_LOGIN_ID")
    transaction_key = required("ANET_SANDBOX_TRANSACTION_KEY")
    tokens = iter(load_tokens(3))

    run_id = os.environ.get("GITHUB_RUN_ID") or secrets.token_hex(5)
    invoice = ("CWP-SBX-" + run_id)[-20:]
    ref = ("SBX" + run_id)[-20:]

    # 1) Real browser -> Accept.js -> sandbox nonce.
    opaque = next(tokens)

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
        or exact_amount(tx.get("authAmount")) != AMOUNT
        or str(tx.get("transactionStatus") or "") not in {"capturedPendingSettlement", "settledSuccessfully"}
    ):
        raise RuntimeError("Sandbox transaction readback does not match the created invoice/amount/status")

    # 4) Deterministic sandbox decline using Authorize.net's documented
    # general bank-decline ZIP trigger (46282). Use a fresh one-use nonce.
    decline_opaque = next(tokens)
    declined = gateway(
        "createTransactionRequest",
        {
            "refId": ("DECL" + run_id)[-20:],
            "transactionRequest": {
                "transactionType": "authCaptureTransaction",
                "amount": AMOUNT,
                "payment": {"opaqueData": decline_opaque},
                "billTo": {
                    "firstName": "Sandbox",
                    "lastName": "Decline",
                    "address": "1 Test Street",
                    "city": "Test",
                    "state": "CA",
                    "zip": "46282",
                    "country": "US",
                },
                "order": {
                    "invoiceNumber": ("CWP-DECL-" + run_id)[-20:],
                    "description": "CajunWebPro sandbox decline acceptance",
                },
            },
        },
        login,
        transaction_key,
    )
    declined_tx = declined.get("transactionResponse") or {}
    if str(declined_tx.get("responseCode") or "") != "2":
        raise RuntimeError(
            "Authorize.net documented sandbox decline trigger did not return response code 2"
        )
    if not declined_tx.get("errors") and not declined_tx.get("messages"):
        raise RuntimeError("Sandbox declined transaction did not include a reason payload")

    # 5) Fresh nonce for ARB because Accept.js opaque data is one-use.
    arb_opaque = next(tokens)
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
        or exact_amount(sub.get("amount")) != AMOUNT
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
    cancelled_readback = gateway(
        "ARBGetSubscriptionRequest",
        {"subscriptionId": subscription_id, "includeTransactions": False},
        login,
        transaction_key,
    )
    cancelled_sub = cancelled_readback.get("subscription") or {}
    if (
        not result_ok(cancelled_readback)
        or str(cancelled_sub.get("status") or "").lower() not in {"canceled", "cancelled", "terminated"}
    ):
        raise RuntimeError("Sandbox ARB cancellation was not confirmed by independent readback")

    # Best-effort cleanup for the one-time transaction while it is unsettled.
    void_result = "not_attempted"
    void_readback = "not_attempted"
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
        if result_ok(void):
            void_result = "ok"
            void_details = gateway("getTransactionDetailsRequest", {"transId": trans_id}, login, transaction_key)
            void_tx = void_details.get("transaction") or {}
            void_status = str(void_tx.get("transactionStatus") or "")
            if not result_ok(void_details) or void_status not in {
                "voided", "declined", "couldNotVoid", "settledSuccessfully"
            }:
                raise RuntimeError("Sandbox void readback returned an unrecognized transaction state")
            void_readback = void_status
        else:
            void_result = "not_voidable"
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
        "decline_trigger_zip": "46282",
        "decline_response_code": str(declined_tx.get("responseCode")),
        "decline_shape_passed": True,
        "arb_created": True,
        "arb_readback_passed": True,
        "arb_cancelled": True,
        "arb_cancel_readback_passed": True,
        "one_time_cleanup": void_result,
        "one_time_cleanup_readback": void_readback,
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
