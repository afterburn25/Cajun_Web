#!/usr/bin/env python3
"""Static fail-closed seams for project history routing, activity and packaging."""
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
read = lambda name: (ROOT / name).read_text(encoding="utf-8")
CONTROLLER = read("site/admin/controllers/studio.php")
REGISTRY = read("site/app/settings/database_schemas.php")
ACTION = read("site/app/projects/action_items.php")
ACTIVITY = read("site/app/activity/events.php")
PROGRESS_ACTIVITY = read("site/app/projects/progress_activity.php")
ROUTER = read("site/portal/controllers/router.php")
ADMIN_CLIENTS = read("site/admin/views/studio/clients.php")
ADMIN_JOB = read("site/admin/views/studio/job.php")
PORTAL = read("site/portal/views/workspace.php")
INVENTORY = read("site/install/inventory.generated.php")
WORKFLOW = read(".github/workflows/project-progress.yml")
BROWSER = read("tests/browser_acceptance.py")
UPGRADE = read("tests/organized_update_cases.py")
UPGRADE_FIXTURE = read("tests/organized_update_fixture.php")
checks = []

def check(name: str, passed: bool) -> None:
    checks.append({"name": name, "passed": bool(passed)})

try:
    required = REGISTRY[REGISTRY.index("function database_schema_required_markers"):REGISTRY.index("function database_schema_separate_setups")]
    marker = "'project_progress_schema_version'=>['depends'"
    descriptor = REGISTRY[REGISTRY.index(marker):]
    descriptor = descriptor.splitlines()[0]
    check("Unified owner workflow exposes one optional no-password project-history migration",
          "project_progress_schema_version" not in required
          and "'function'=>'project_progress_migrate'" in descriptor
          and "'arguments'=>'none'" in descriptor)
    legacy = CONTROLLER[CONTROLLER.index("function studio_legacy_job_save"):CONTROLLER.index("function studio_admin_action")]
    check("Legacy project save owns the staff mutex and transaction before rechecking activation",
          "staff_locked(fn()=>staff_transaction" in legacy
          and legacy.index("studio_client_manager()") < legacy.index("project_progress_mode()")
          and legacy.index("project_progress_mode()") < legacy.index("UPDATE '.table('studio_jobs')")
          and "$id=studio_legacy_job_save();" in CONTROLLER)
    check("Prepared project writes use the journaled runtime and retain exact focus identity",
          "if(project_progress_mode())" in CONTROLLER
          and "project_progress_save(" in CONTROLLER
          and "'focus_progress'=>$result['event_id']" in CONTROLLER)
    check("Prepared route selection requires the marker and all retained storage",
          "if($version==='1'){project_progress_require_ready();return true;}" in read("site/includes/layout/project_progress.php"))
    check("Review actions recheck project, contact and resolved support authority before bounded reads",
          "studio_job($jobId,$clientId)" in ACTION
          and "contact_require_permission('projects',$clientId)" in ACTION
          and "support_can('support.login')" in ACTION
          and "ProjectActionItems::MAX_REVIEW_ROWS+1" in ACTION)
    check("Progress activity validates complete native history and keeps contact identity personal",
          "$actor['type']==='contact'?(int)$actor['client_id']" in PROGRESS_ACTIVITY
          and ACTIVITY.count("project_progress_activity_validate(") >= 4
          and "['type'=>'contact'" in ACTIVITY
          and "e.sort_sequence" in ACTIVITY)
    check("Project pages are private and route prepared history without replacing retained workspace sections",
          "if(array_key_exists('job',$_GET))header('Cache-Control: no-store, private')" in ROUTER
          and "project_progress_editor" in ADMIN_CLIENTS
          and "project_progress_timeline" in ADMIN_JOB
          and "project_action_items_render" in PORTAL
          and "Project conversation" in PORTAL
          and "Invoices & payment instructions" in PORTAL)
    required = [
        "app/projects/action_items.php", "app/projects/progress.php",
        "app/projects/progress_activity.php", "app/projects/progress_forms.php",
        "database/migrations/project_progress_migrate.php",
        "database/schema/project_progress_schema.php",
        "database/schema/project_progress_validate_schema.php",
        "includes/foundation/projects/ProjectActionItems.php",
        "includes/layout/project_progress.php",
    ]
    check("Installer inventory contains every new project-history shipping dependency",
          all(("'" + path + "'=>") in INVENTORY for path in required))
    check("Hosted evidence retains all four PHP/database lanes and current integration cases",
          "php: ['8.2', '8.4']" in WORKFLOW
          and "mariadb:10.11" in WORKFLOW and "mysql:8.4" in WORKFLOW
          and "php tests/project_progress_native.php" in WORKFLOW
          and "tests/project_progress_screen_native_cases.php" in WORKFLOW
          and "python3 tests/project_progress_integration_policy.py" in WORKFLOW)
    check("Full Application browser acceptance invokes the current project-history journey",
          "from project_progress_cases import run_browser as project_progress_browser" in BROWSER
          and "project_progress_browser(globals(),browser)" in BROWSER)
    check("Published-source upgrade acceptance preserves populated history through recovery",
          "from project_progress_upgrade_cases import run as progress_upgrade" in UPGRADE
          and "progress_upgrade({**locals(),'token':token})" in UPGRADE
          and "($argv[2]??'')==='progress'" in UPGRADE_FIXTURE)
    check("Read adapters contain no external, billing, message or provisioning mutation",
          not any(token in ACTION + PROGRESS_ACTIVITY for token in [
              "curl_", "http_request", "payment_", "provider_", "mail_send",
              "INSERT INTO", "UPDATE ", "DELETE FROM",
          ]))
    check("Project action model remains byte-identical in the deployed tree",
          (ROOT / "foundation/projects/ProjectActionItems.php").read_bytes()
          == (ROOT / "site/includes/foundation/projects/ProjectActionItems.php").read_bytes())
except Exception as exc:
    checks.append({"name": "Unexpected policy failure: " + str(exc), "passed": False})

result = {
    "passed": all(item["passed"] for item in checks),
    "count": len(checks),
    "scope": "Static atomic routing, authorization, activity privacy, hosted matrix and package inventory policy.",
    "checks": checks,
}
print(json.dumps(result, indent=2))
raise SystemExit(0 if result["passed"] else 1)
