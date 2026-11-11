"""Additive local-intake acceptance wiring on the exact current recipe."""
import hashlib
import json


def digest(value):
    return hashlib.sha256(json.dumps(value, separators=(',', ':')).encode()).hexdigest()


def verify(root, contract, workflow, commands, check):
    full_contract = json.loads((root / 'tools/acceptance-contract.json').read_text())
    item = full_contract['cloud_site_intake']
    additions = item['additions']
    full_native = full_contract['native_commands']
    payment = full_contract.get('hosting_payment_acceptance_extension')
    if payment:
        payment_additions = payment['additions']
        check('Static intake projects only the exact later hosting-payment commands',
              len(payment_additions) == len(set(payment_additions)) == 4
              and all(full_native.count(command) == 1 for command in payment_additions))
        full_native = [command for command in full_native if command not in payment_additions]
    identity = full_contract.get('client_identity_native_command_extension')
    if identity:
        identity_additions = identity['additions']
        check('Static intake projects only the exact later public-identity commands',
              len(identity_additions) == len(set(identity_additions)) == 2
              and all(full_native.count(command) == 1 for command in identity_additions))
        full_native = [command for command in full_native if command not in identity_additions]
    check('Static intake retains the complete169-command native recipe',
          len(full_native) == item['full_native_count'] == 169)
    check('Static intake receives the exact162-command projected recipe',
          len(commands) == item['call_native_count'] == 162
          and commands == contract['native_commands'])
    position = commands.index(item['additions_after']) + 1
    check('Two portable intake commands run once after real request tests',
          len(additions) == len(set(additions)) == 2
          and commands[position:position + 2] == additions
          and all(commands.count(command) == 1 for command in additions))
    previous = [command for command in commands if command not in additions]
    check('Static intake preserves all160 prior native commands and their order',
          len(previous) == item['predecessor_native_count'] == 160
          and digest(previous) == item['predecessor_native_sha256'])

    reports = json.loads((root / 'tools/acceptance-reports.json').read_text())
    for key, expected_count in (('browser', 180), ('final', 226)):
        current = reports[key]
        if payment:
            payment_reports = payment['reports']
            check('Static intake projects only the exact later hosting-payment reports in ' + key,
                  len(payment_reports) == len(set(payment_reports)) == 3
                  and all(current.count(name) == 1 for name in payment_reports))
            current = [name for name in current if name not in payment_reports]
        if identity:
            identity_reports = identity['reports']
            check('Static intake projects only the exact later public-identity reports in ' + key,
                  len(identity_reports) == len(set(identity_reports)) == 2
                  and all(current.count(name) == 1 for name in identity_reports))
            current = [name for name in current if name not in identity_reports]
        check('Static intake requires both portable reports exactly once in ' + key,
              len(current) == item['current_reports'][key]['count'] == expected_count
              and digest(current) == item['current_reports'][key]['sha256']
              and len(current) == len(set(current))
              and all(current.count(name) == 1 for name in item['reports']))
        retained = [name for name in current if name not in item['reports']]
        baseline = item['predecessor_reports'][key]
        check('Static intake preserves every prior ' + key + ' report',
              len(retained) == baseline['count']
              and digest(retained) == baseline['sha256'])

    for path, expected in item['source_sha256'].items():
        check('Static intake acceptance source bound ' + path,
              hashlib.sha256((root / path).read_bytes().replace(b'\r\n', b'\n')).hexdigest()
              == expected)
    helper = (root / 'tests/cloud_site_requests_wiring.py').read_bytes().replace(b'\r\n', b'\n')
    check('Previous cloud request wiring remains byte-identical',
          hashlib.sha256(helper).hexdigest()
          == item['predecessor_source_sha256']['tests/cloud_site_requests_wiring.py'])

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
          and "'codex/issue343-cloud-intake-*'" in focus)
    return previous
