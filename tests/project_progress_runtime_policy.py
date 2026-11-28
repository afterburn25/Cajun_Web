"""Static fail-closed policy for the held project-progress runtime adapter."""
from __future__ import annotations

import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
RUNTIME = (ROOT / "site/app/projects/progress.php").read_text(encoding="utf-8")
FORMS = (ROOT / "site/app/projects/progress_forms.php").read_text(encoding="utf-8")
LAYOUT = (ROOT / "site/includes/layout/project_progress.php").read_text(encoding="utf-8")
WORKFLOW = (ROOT / ".github/workflows/project-progress.yml").read_text(encoding="utf-8")


def section(source: str, start: str, end: str | None = None) -> str:
    begin = source.index(start)
    return source[begin:] if end is None else source[begin : source.index(end, begin)]


CHECKS: list[dict[str, object]] = []


def check(name: str, passed: bool) -> None:
    CHECKS.append({"name": name, "passed": passed})
    if not passed:
        raise AssertionError(name)


try:
    locked = section(RUNTIME, "function project_progress_locked", "function project_progress_touch")
    save = section(RUNTIME, "function project_progress_save", "function project_progress_view")
    client = section(RUNTIME, "function project_progress_client_get")
    ready = section(RUNTIME, "function project_progress_require_ready", "function project_progress_locked")

    check(
        "Runtime refuses inherited transactions and nests the staff mutex outside its transaction",
        "if(db()->inTransaction())" in locked
        and "staff_locked(fn()=>staff_transaction($work))" in locked,
    )
    check(
        "Every prepared read and write requires the exact activation marker and all three retained tables",
        "project_progress_ready()" in ready
        and "information_schema.tables" in ready
        and all(name in ready for name in [
            "studio_progress_events",
            "studio_progress_requests",
            "studio_progress_heads",
        ]),
    )
    check(
        "Staff authority is re-evaluated only inside the locked save",
        save.index("project_progress_locked") < save.index("project_progress_staff()"),
    )
    check(
        "Save locks the active client and project before comparing the retained optimistic token",
        save.index("project_progress_client_row($client)")
        < save.index("project_progress_job($id,$client)")
        < save.index("hash_equals($state['expected'],$expected)"),
    )
    check(
        "Idempotent replay binds the exact actor, client, request and immutable input",
        "project_progress_input($id,$client,(int)$actor['id'],$expected,$snapshot)" in save
        and "$existing['input_json']!==$input" in save
        and "hash_equals(hash('sha256',$input),$existing['input_sha256'])" in save,
    )
    check(
        "Workspace updates retain owner identity and require exactly one changed row",
        "WHERE id=? AND client_id=?" in save
        and "->rowCount()!==1" in save
        and "Project ownership changed. Existing details were preserved." in save,
    )
    check(
        "Event, request, head and content-free audit remain in one transaction body",
        save.index("project_progress_append($event);query('INSERT INTO '.table('studio_progress_requests')")
        < save.index("project_progress_store_head($event,$tracked)")
        < save.index("project_progress_audit((int)$actor['id'],'saved'"),
    )
    check(
        "Client history rechecks the parent identity and scoped project grant under the same lock",
        client.index("project_progress_locked")
        < client.index("project_progress_client_row($client)")
        < client.index("hash_equals($row['auth_version']")
        < client.index("contact_require_permission('projects',$client)")
        < client.index("project_progress_job($id,$client)"),
    )
    check(
        "Support history cannot fall back to client identity and must retain the scoped audit",
        "support_check($support,false)" in client
        and "if(!$ctx)return null" in client
        and "if($view===null)throw new RuntimeException" in client
        and "A current scoped support session is required." in client
        and "Current support access to this project is required." in client
        and "support_audit($ctx['session'],'project.progress','project'" in client,
    )
    check(
        "Public history projection excludes staff, request and fingerprint fields",
        "forClient((int)$job['client_id'])" in RUNTIME
        and "if($staff)$result+=['client_id'" in RUNTIME,
    )
    check(
        "Forms retain exact bounded retry identity without accepting private fields",
        all(token in FORMS for token in [
            "'request_id'=>32",
            "'expected'=>64",
            "ProjectSnapshot::fromArray($fields)",
        ])
        and "private" not in FORMS.lower(),
    )
    check(
        "Rendered client timeline escapes values and never renders internal attribution",
        "esc($fields['title'])" in LAYOUT
        and "esc($event['id'])" in LAYOUT
        and "staff_id" not in LAYOUT
        and "before_sha256" not in LAYOUT,
    )
    check(
        "Runtime slice contains no payment, provider, message or activity mutation",
        not any(token in RUNTIME for token in [
            "payment_",
            "provider_",
            "mail_",
            "activity_",
        ]),
    )
    check(
        "Hosted runtime evidence keeps all four PHP and database lanes",
        "php: ['8.2', '8.4']" in WORKFLOW
        and "mariadb:10.11" in WORKFLOW
        and "mysql:8.4" in WORKFLOW
        and "php tests/project_progress_native.php" in WORKFLOW,
    )
    check(
        "Pure form evidence runs with default and no-ini PHP",
        "php tests/project-progress/forms.php" in WORKFLOW
        and "php -n tests/project-progress/forms.php" in WORKFLOW,
    )
except Exception as exc:
    if not CHECKS or CHECKS[-1]["passed"]:
        CHECKS.append({"name": f"Unexpected policy failure: {exc}", "passed": False})

passed = bool(CHECKS) and all(item["passed"] for item in CHECKS)
print(json.dumps({
    "passed": passed,
    "count": len(CHECKS),
    "checks": CHECKS,
    "scope": "Static runtime locking, current authority, optimistic history, privacy and hosted-matrix policy.",
}, indent=2))
raise SystemExit(0 if passed else 1)
