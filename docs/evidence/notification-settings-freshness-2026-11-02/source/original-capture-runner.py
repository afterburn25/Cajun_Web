from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
import hashlib
import json
import subprocess
import time

ROOT = Path('/workspace/scratch/8cc2b4f257ab/Cajun_Web_notification_pause_fix')
OUT = Path('/workspace/scratch/8cc2b4f257ab/notification-pause-local-evidence/source-checks')
OUT.mkdir()
COMMANDS = [
    ('settings-provenance', ['python3', 'tests/notification_settings_freshness_provenance.py']),
    ('workflow-contract', ['python3', 'tests/account_notification_workflow_contract.py']),
    ('fixture-combination', ['python3', 'tests/account_notification_fixture_provenance.py']),
    ('core-navigation', ['python3', 'tests/core_browser_fixture_contract.py']),
    ('provider-provenance', ['python3', 'tests/provider_browser_fixture_provenance.py']),
    ('navigation-helper', ['python3', 'tests/owner_navigation_contract_test.py']),
    ('account-policy', ['php8.3', 'tests/account_security_policy.php']),
    ('account-policy-minimal', ['php8.3', '-n', 'tests/account_security_policy.php']),
    ('admin-policy', ['php8.3', 'tests/admin_session_no_reentry.php']),
    ('acceptance-helpers', ['python3', 'tests/acceptance_artifacts_test.py', '--report', str(OUT / 'acceptance-helpers.json')]),
    ('account-notification-contract', ['python3', 'tests/account_notification_contract.py']),
    ('account-contract', ['python3', 'tests/account_integration_contract.py']),
    ('notification-contract', ['python3', 'tests/notification_integration_contract.py']),
    ('ticket-contract', ['python3', 'tests/tickets_integration_contract.py']),
    ('runtime-contract', ['python3', 'tests/combined_runtime_repair.py']),
    ('lint-feed', ['php8.3', '-l', 'site/app/notifications/feed.php']),
    ('lint-outbox', ['php8.3', '-l', 'site/app/notifications/outbox.php']),
    ('lint-inventory', ['php8.3', '-l', 'site/install/inventory.generated.php']),
    ('lint-native', ['php8.3', '-l', 'tests/notification_center_native.php']),
    ('lint-regression', ['php8.3', '-l', 'tests/notification_settings_freshness_cases.php']),
]


def execute(item):
    name, command = item
    started = time.monotonic()
    result = subprocess.run(command, cwd=ROOT, capture_output=True)
    (OUT / (name + '.stdout.log')).write_bytes(result.stdout)
    (OUT / (name + '.stderr.log')).write_bytes(result.stderr)
    row = {'name': name, 'command': command, 'exit_code': result.returncode,
           'elapsed_seconds': round(time.monotonic() - started, 3),
           'stdout_sha256': hashlib.sha256(result.stdout).hexdigest(),
           'stderr_bytes': len(result.stderr)}
    try:
        report = json.loads(result.stdout)
    except (json.JSONDecodeError, UnicodeDecodeError):
        report = None
    if isinstance(report, dict) and 'checks' in report:
        row.update(report_passed=report.get('passed'), check_count=len(report['checks']))
    print(json.dumps(row), flush=True)
    return row


with ThreadPoolExecutor(max_workers=4) as workers:
    rows = list(workers.map(execute, COMMANDS))

before = subprocess.check_output(['git', 'diff', '--binary'], cwd=ROOT)
rows.append(execute(('canonical-build', ['python3', 'tools/build.py', '--refresh'])))
after = subprocess.check_output(['git', 'diff', '--binary'], cwd=ROOT)
unchanged = before == after
report = {'parent': '3cc10c144e2cd4e78ff5952cee9f60710a07a9ff',
          'scope': 'Current-source proof commands on uncommitted correction; immutable clean-commit admission remains pending.',
          'passed': all(row['exit_code'] == 0 and row['stderr_bytes'] == 0 for row in rows) and unchanged,
          'canonical_refresh_preserved_diff': unchanged, 'commands': rows}
(OUT / 'execution.json').write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps({'passed': report['passed'], 'commands': len(rows), 'canonical_refresh_preserved_diff': unchanged}), flush=True)
raise SystemExit(0 if report['passed'] else 1)
