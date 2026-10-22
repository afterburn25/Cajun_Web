"""Additive intake checks, preserving the existing cloud-request acceptance recipe."""
import hashlib
import json


def digest(value):
    return hashlib.sha256(json.dumps(value, separators=(',', ':')).encode()).hexdigest()


def verify(root, contract, workflow, commands, check):
    item = contract['cloud_site_intake']
    additions = item['additions']
    check('Static intake retains the complete240-command native recipe',
          len(commands) == len(contract['native_commands']) and commands == contract['native_commands'])
    position = commands.index(item['additions_after']) + 1
    check('Two portable intake commands run once after real request tests',
          len(additions) == len(set(additions)) == 2
          and commands[position:position + 2] == additions
          and all(commands.count(command) == 1 for command in additions))
    previous = [command for command in commands if command not in additions]
    check('Static intake preserves all238 prior native commands and their order',
          len(previous) == item['predecessor_native_count']
          and digest(previous) == item['predecessor_native_sha256'])
    reports = json.loads((root / 'tools/acceptance-reports.json').read_text())
    prior_reports = dict(reports)
    for key in ('browser', 'final'):
        current = reports[key]
        check('Static intake requires both portable reports exactly once in ' + key,
              len(current) == len(set(current))
              and all(current.count(name) == 1 for name in item['reports']))
        prior_reports[key] = [name for name in current if name not in item['reports']]
        baseline = item['predecessor_reports'][key]
        check('Static intake preserves every prior ' + key + ' report',
              len(prior_reports[key]) == baseline['count']
              and digest(prior_reports[key]) == baseline['sha256'])
    for path, expected in item['source_sha256'].items():
        check('Static intake acceptance source bound ' + path,
              hashlib.sha256((root / path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == expected)
    # The only old-helper change supplies the already-filtered registry above.
    helper = (root / 'tests/cloud_site_requests_wiring.py').read_text()
    helper = helper.replace('commands, check, reports=None):', 'commands, check):', 1)
    helper = helper.replace("    if reports is None:\n        reports = json.loads((root / 'tools/acceptance-reports.json').read_text())",
                            "    reports = json.loads((root / 'tools/acceptance-reports.json').read_text())", 1)
    check('Previous cloud request wiring retains its exact predecessor checks',
          hashlib.sha256(helper.encode()).hexdigest() == item['predecessor_source_sha256']['tests/cloud_site_requests_wiring.py'])
    focus = (root / '.github/workflows/provider-cloud-sites.yml').read_text()
    selector = "(github.event_name == 'push' || github.event_name == 'workflow_dispatch') && matrix.php == '8.2' && matrix.database.label == 'mariadb-10.11'"
    check('Genuine expiry runs only on focused PHP8.2 MariaDB push or manual jobs',
          focus.count("CWP_TEST_CLOUD_INTAKE_EXPIRED: ${{ " + selector + " && '1' || '0' }}") == 1
          and focus.count('-e CWP_TEST_CLOUD_INTAKE_EXPIRED') == 1
          and 'CWP_TEST_CLOUD_INTAKE_EXPIRED' not in workflow)
    check('Only the selected genuine-expiry job receives forty minutes',
          focus.count('timeout-minutes: ${{ ' + selector + ' && 40 || 20 }}') == 1)
    check('Focused intake portable checks use both official PHP modes',
          focus.count('php tests/cloud_site_intake_contract.php > qa/cloud-site-intake-default.json') == 1
          and focus.count('php -n tests/cloud_site_intake_contract.php > qa/cloud-site-intake-minimal.json') == 1)
    check('Focused artifacts retain both portable intake reports',
          all(focus.count('            qa/' + name + '\n') == 1 for name in item['reports']))
    check('Focused push and pull request paths include both intake test files',
          focus.count("'tests/cloud_site_intake_contract.php'") == 2
          and focus.count("'tests/cloud_site_intake_wiring.py'") == 2
          and 'codex/twentyi-client-cloud-intake]' in focus)
    return previous, prior_reports
