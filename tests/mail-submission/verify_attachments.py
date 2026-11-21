"""Independently parse every synthetic attachment-format MIME fixture."""
from pathlib import Path
import base64
import email
import email.policy
import email.utils
import hashlib
import json
import sys


source = Path(sys.argv[1])
document = json.loads(source.read_text(encoding="utf-8"))
assert document["passed"] is True
checks = []


def check(name, condition):
    checks.append({"name": name, "passed": bool(condition)})
    assert condition, name


formats = []
for fixture in document["messages"]:
    name = fixture["name"]
    snapshot = fixture["snapshot"]
    raw = base64.b64decode(fixture["mime_base64"], validate=True)
    message = email.message_from_bytes(raw, policy=email.policy.default)
    check(name + " fixture checksum is exact", hashlib.sha256(raw).hexdigest() == fixture["mime_sha256"])
    check(name + " independent parser has no MIME defects", not message.defects)
    check(name + " exact literal subject survives", str(message["Subject"]) == snapshot["subject"])
    check(name + " fixed Message-ID survives", str(message["Message-ID"]) == snapshot["message_id"])
    check(name + " exact From survives", email.utils.getaddresses(message.get_all("From", [])) == [("", snapshot["from"])])
    visible_to = [address for _, address in email.utils.getaddresses(message.get_all("To", [])) if address]
    visible_cc = [address for _, address in email.utils.getaddresses(message.get_all("Cc", [])) if address]
    check(name + " visible recipients stay ordered", visible_to == snapshot["to"] and visible_cc == snapshot["cc"])
    check(
        name + " hidden recipients and authority headers stay absent",
        all(
            message.get(header) is None
            for header in ["Bcc", "Resent-Bcc", "Reply-To", "Return-Path", "Disposition-Notification-To", "X-Mailer"]
        ),
    )

    attachments = []
    plain = None
    html = None
    for part in message.walk():
        if part.is_multipart():
            continue
        if part.get_content_disposition() == "attachment":
            attachments.append(part)
        elif part.get_content_type() == "text/plain" and plain is None:
            plain = part
        elif part.get_content_type() == "text/html" and html is None:
            html = part

    expected = snapshot.get("attachments", [])
    check(name + " declared descriptor count matches wire files", len(attachments) == len(expected))
    for index, (part, descriptor) in enumerate(zip(attachments, expected)):
        decoded = part.get_payload(decode=True)
        check(name + f" attachment {index} filename is exact", part.get_filename() == descriptor["name"])
        check(name + f" attachment {index} content type is exact", part.get_content_type() == descriptor["mime"])
        check(
            name + f" attachment {index} digest and length are exact",
            hashlib.sha256(decoded).hexdigest() == descriptor["sha256"] and len(decoded) == descriptor["bytes"],
        )
    check(
        name + " plaintext body survives alongside MIME structure",
        plain is not None and plain.get_payload(decode=True) == snapshot["body"].replace("\n", "\r\n").encode("utf-8"),
    )
    check(
        name + " formatted alternative matches the private snapshot",
        (html is None and "body_html" not in snapshot)
        or (html is not None and html.get_payload(decode=True) == snapshot["body_html"].replace("\n", "\r\n").encode("utf-8")),
    )
    if "thread" in snapshot:
        check(
            name + " copied thread headers remain literal and complete",
            str(message["In-Reply-To"]) == snapshot["thread"]["in_reply_to"]
            and str(message["References"]) == " ".join(snapshot["thread"]["references"]),
        )
    else:
        check(name + " ordinary message has no copied thread headers", message["In-Reply-To"] is None and message["References"] is None)
    check(name + " wire is canonical bounded CRLF", b"\r\n\r\n" in raw and raw.endswith(b"\r\n") and max(map(len, raw.split(b"\r\n"))) <= 998 and len(raw) <= 1048576)
    check(name + " parsed header fields have no defects", all(not getattr(value, "defects", ()) for _, value in message.items()))
    if name.startswith("format-"):
        formats.append(snapshot["format"])

check("Every snapshot format 1 through 8 has an independent fixture", formats == list(range(1, 9)))
check("Maximum fixture uses format 8 and exact four-file total", document["messages"][-1]["snapshot"]["format"] == 8 and sum(item["bytes"] for item in document["messages"][-1]["snapshot"]["attachments"]) == 524288)

result = {
    "passed": True,
    "scope": "Independent Python standard-library parsing of synthetic formats 1-8 and maximum attachment MIME only; no database, application storage, socket, SMTP, provider or live operation",
    "checks": checks,
}
if len(sys.argv) > 2:
    Path(sys.argv[2]).write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8", newline="\n")
print("PASS:", len(checks), "independent attachment MIME decoding checks")
