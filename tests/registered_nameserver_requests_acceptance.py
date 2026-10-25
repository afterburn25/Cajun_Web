"""Finite client-request acceptance extension; no application or network execution."""
import copy
import ast
import hashlib
import json

from registered_nameserver_changes_acceptance import source_predecessor

BASE = 'af78abd81ce39839cd100e1c882a59b04258a25b'
# BEGIN FIXED SOURCE INVENTORY
SOURCE_PATHS = {
    '.github/workflows/application-tests.yml',
    '.github/workflows/provider-nameserver-changes.yml',
    '.github/workflows/provider-registrar.yml',
    'site/admin/controllers/actions.php',
    'site/admin/controllers/registered_nameserver_changes.php',
    'site/admin/controllers/registered_nameserver_requests.php',
    'site/admin/controllers/router.php',
    'site/admin/views/registered_nameserver_requests.php',
    'site/admin/views/registered_nameservers.php',
    'site/app/providers/domain_contracts.php',
    'site/app/providers/inventory.php',
    'site/app/providers/operations.php',
    'site/app/providers/registered_nameserver_changes.php',
    'site/app/providers/registered_nameserver_changes_storage.php',
    'site/app/providers/registered_nameserver_changes_views.php',
    'site/app/providers/registered_nameserver_requests.php',
    'site/app/providers/registered_nameserver_requests_document.php',
    'site/app/providers/registered_nameserver_requests_storage.php',
    'site/app/providers/registered_nameserver_requests_views.php',
    'site/app/providers/registered_nameservers.php',
    'site/app/providers/registered_nameservers_storage.php',
    'site/app/providers/registrar.php',
    'site/app/staff/accounts.php',
    'site/database/migrations/registered_nameserver_requests_migrate.php',
    'site/database/schema/registered_nameserver_requests_schema.php',
    'site/includes/layout/admin.php',
    'site/includes/layout/provider_services.php',
    'site/portal/controllers/actions.php',
    'site/portal/controllers/registered_nameserver_requests.php',
    'site/portal/controllers/router.php',
    'site/portal/views/registered_nameserver_requests.php',
    'tests/acceptance_artifacts_test.py',
    'tests/browser_acceptance.py',
    'tests/provider_http_server.py',
    'tests/registered_nameserver_changes.php',
    'tests/registered_nameserver_changes_acceptance.py',
    'tests/registered_nameserver_changes_cases.py',
    'tests/registered_nameserver_changes_http_fixture_test.py',
    'tests/registered_nameserver_changes_native.php',
    'tests/registered_nameserver_changes_upgrade.py',
    'tests/registered_nameserver_changes_upgrade_fixture.php',
    'tests/registered_nameserver_changes_upgrade_test.py',
    'tests/registered_nameserver_requests.php',
    'tests/registered_nameserver_requests_acceptance.py',
    'tests/registered_nameserver_requests_cases.py',
    'tests/registered_nameserver_requests_document.php',
    'tests/registered_nameserver_requests_native.php',
    'tests/registered_nameserver_requests_storage.php',
    'tests/registered_nameserver_requests_upgrade.py',
    'tests/registered_nameserver_requests_upgrade_fixture.php',
    'tests/registered_nameserver_requests_upgrade_test.py',
    'tests/registered_nameserver_requests_views.php',
    'tests/registered_nameservers_cases.py',
    'tests/registered_nameservers_native.php',
    'tests/registered_nameservers_upgrade.py',
    'tools/acceptance-reports.json',
}
DELTA_PATHS = {
    '.github/workflows/application-tests.yml',
    '.github/workflows/provider-nameserver-changes.yml',
    'tests/acceptance_artifacts_test.py',
    'tests/provider_http_server.py',
    'tests/registered_nameserver_changes.php',
    'tests/registered_nameserver_changes_acceptance.py',
    'tests/registered_nameserver_changes_http_fixture_test.py',
    'tests/registered_nameservers_cases.py',
    'tools/acceptance-reports.json',
}
UNCHANGED_PATHS = {
    'tests/browser_acceptance.py',
    'tests/registered_nameserver_changes_native.php',
    'tests/registered_nameserver_changes_upgrade.py',
    'tests/registered_nameserver_changes_upgrade_fixture.php',
    'tests/registered_nameserver_changes_upgrade_test.py',
    'tests/registered_nameservers_upgrade.py',
    'tests/registered_nameservers_upgrade_fixture.php',
    'tests/registered_nameservers_upgrade_test.py',
    'tools/acceptance_artifacts.py',
}
CONTRACT_SHA256 = 'f8903bce497918ebdb0ae834e98e9c97c2b9121f77fbaf3572e15ec396c81a40'
REPORTS_SHA256 = 'e6161a58263d6666f0aeda828d34843b986353efbcc49afc08d0c5c7bed4ceb1'
# END FIXED SOURCE INVENTORY
OLD_MINIMAL = '"$PHP_BINARY" -n tests/registered_nameserver_changes.php > qa/registered-nameserver-changes-service-no-ini-results.json'
FIXED_MINIMAL = '"$PHP_BINARY" -n -d extension=tokenizer -d extension=pdo tests/registered_nameserver_changes.php > qa/registered-nameserver-changes-service-no-ini-results.json'
NATIVE = [
    '"$PHP_BINARY" tests/registered_nameserver_requests_document.php > qa/registered-nameserver-requests-document-results.json',
    '"$PHP_BINARY" -n tests/registered_nameserver_requests_document.php > qa/registered-nameserver-requests-document-no-ini-results.json',
    '"$PHP_BINARY" tests/registered_nameserver_requests.php > qa/registered-nameserver-requests-service-results.json',
    '"$PHP_BINARY" -n -d extension=tokenizer -d extension=pdo tests/registered_nameserver_requests.php > qa/registered-nameserver-requests-service-no-ini-results.json',
    '"$PHP_BINARY" tests/registered_nameserver_requests_storage.php > qa/registered-nameserver-requests-storage-results.json',
    '"$PHP_BINARY" -n tests/registered_nameserver_requests_storage.php > qa/registered-nameserver-requests-storage-no-ini-results.json',
    '"$PHP_BINARY" tests/registered_nameserver_requests_views.php > qa/registered-nameserver-requests-views-results.json',
    '"$PHP_BINARY" -n tests/registered_nameserver_requests_views.php > qa/registered-nameserver-requests-views-no-ini-results.json',
    '"$PHP_BINARY" tests/registered_nameserver_requests_native.php',
]
NATIVE_REPORTS = [command.rsplit('qa/', 1)[1] for command in NATIVE[:-1]] + [
    'registered-nameserver-requests-native-results.json',
]
UPGRADE = [
    '.ci-venv/bin/python -B tests/registered_nameserver_requests_upgrade_test.py --report qa/apache-registered-nameserver-requests-upgrade-guards-results.json',
    '.ci-venv/bin/python -B tests/registered_nameserver_requests_upgrade.py',
]
APACHE_REPORTS = [
    'apache-registered-nameserver-requests-upgrade-guards-results.json',
    'apache-registered-nameserver-requests-upgrade-results.json',
]
CAPTURES = ['Client-Nameserver-Request-' + state + '-' + size + '.png'
            for state in ('Client-Ready', 'Owner-Review', 'Owner-Uncertain', 'Client-Observed')
            for size in ('Desktop', 'Mobile')]


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()


def require(condition, message):
    if not condition:
        raise ValueError(message)


def commands_predecessor(commands):
    require(len(commands) == len(set(commands)) == 210, 'Exact client-request command inventory required')
    position = commands.index('"$PHP_BINARY" tests/registered_nameserver_changes_native.php') + 1
    require(commands[position:position + len(NATIVE)] == NATIVE, 'Exact client-request command insertion required')
    require(commands.count(FIXED_MINIMAL) == 1 and OLD_MINIMAL not in commands, 'Explicit distro minimal dependency correction required')
    prior = [OLD_MINIMAL if command == FIXED_MINIMAL else command for command in commands if command not in NATIVE]
    require(len(prior) == 201, 'All original native commands required')
    return prior


def reports_predecessor(reports, extension):
    # The closed second-domain TLS cases extend the existing required report.
    browser = NATIVE_REPORTS
    additions = {'browser': browser, 'final': browser + APACHE_REPORTS}
    require(extension['reports'] == additions, 'Exact client-request report inventory required')
    prior = copy.deepcopy(reports)
    for lane, names in additions.items():
        require(len(reports[lane]) == len(set(reports[lane])) and all(reports[lane].count(n) == 1 for n in names), 'Missing or duplicate client-request report')
        prior[lane] = [name for name in reports[lane] if name not in names]
    require(len(prior['browser']) == 219 and len(prior['final']) == 274
            and sha(canonical(prior)) == extension['predecessor_reports_sha256'], 'Original complete report sequence changed')
    return prior


def verify(a, root, tmp, check, deny, contract):
    extension = contract['registered_nameserver_requests']
    check('Client nameserver acceptance binds the exact af78 prerequisite', extension['source_predecessor'] == BASE)
    check('Client nameserver native additions and one distro correction are explicit',
          extension['native_additions'] == NATIVE
          and extension['command_correction'] == {'before': OLD_MINIMAL, 'after': FIXED_MINIMAL})
    check('Client nameserver current source inventory is closed',
          set(extension['source_sha256']) == SOURCE_PATHS
          and set(extension['source_delta']) == DELTA_PATHS
          and set(extension['unchanged_source_sha256']) == UNCHANGED_PATHS)
    for path, expected in extension['source_sha256'].items():
        check('Client nameserver current source pin ' + path,
              sha(root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')) == expected)
    # Current source is authenticated above before any old hash or command map is restored.
    prior = copy.deepcopy(contract)
    del prior['registered_nameserver_requests']
    prior['native_commands'] = commands_predecessor(contract['native_commands'])
    check('Client nameserver acceptance retains the complete original contract',
          sha(canonical(prior)) == extension['predecessor_contract_sha256'] == CONTRACT_SHA256)
    restored = {}
    for path, delta in extension['source_delta'].items():
        raw = root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
        restored[path] = source_predecessor(raw, delta)
        check('Client nameserver preserves complete predecessor source ' + path, True)
        deny('Client nameserver rejects altered source ' + path,
             lambda data=raw, proof=delta: source_predecessor(data + b'\n# changed', proof))
    check('Client nameserver source inverses bind the exact af78 bytes',
          set(restored) == set(extension['predecessor_source_sha256'])
          and all(sha(raw) == extension['predecessor_source_sha256'][path] for path, raw in restored.items()))
    check('Client nameserver keeps original browser transport and upgrade source',
          all(sha(root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')) == expected
              for path, expected in extension['unchanged_source_sha256'].items()))
    reports = a.load(root / 'tools/acceptance-reports.json')
    preceding_reports = reports_predecessor(reports, extension)
    check('Client nameserver actual report inventory is227 browser and284 final',
          len(reports['browser']) == 228 and len(reports['final']) == 285
          and sha(canonical(preceding_reports)) == REPORTS_SHA256)
    for label in ('missing', 'duplicate', 'changed predecessor'):
        wrong = copy.deepcopy(reports)
        if label == 'missing':
            wrong['final'].remove(APACHE_REPORTS[-1])
        elif label == 'duplicate':
            wrong['browser'].append(NATIVE_REPORTS[0])
        else:
            wrong['final'][0] += '.changed'
        deny('Client nameserver report union rejects ' + label,
             lambda value=wrong: reports_predecessor(value, extension))
    for label in ('missing', 'duplicate', 'reordered', 'missing distro modules'):
        wrong = list(contract['native_commands'])
        if label == 'missing': wrong.remove(NATIVE[0])
        elif label == 'duplicate': wrong.append(NATIVE[0])
        elif label == 'reordered':
            first, second = wrong.index(NATIVE[0]), wrong.index(NATIVE[1])
            wrong[first], wrong[second] = wrong[second], wrong[first]
        else: wrong[wrong.index(FIXED_MINIMAL)] = OLD_MINIMAL
        deny('Client nameserver command union rejects ' + label,
             lambda value=wrong: commands_predecessor(value))
    sample = tmp / 'client-nameserver-reports'
    for name in extension['reports']['final']:
        lane = 'apache' if name.startswith('apache-') else 'native-browser'
        check('Client nameserver report has one evidence owner ' + name,
              a.evidence_allowed('qa/' + name, lane)
              and all(not a.evidence_allowed('qa/' + name, other)
                      for other in ('build', 'recovery', 'apache', 'native-browser') if other != lane))
        target = sample / 'qa' / name
        a.write(target, {'passed': False, 'checks': [{'name': 'Synthetic failure', 'passed': False}]})
        check('Client nameserver failed report cannot pass ' + name, not a.reports(sample)[name]['passed'])
        target.unlink()
        check('Client nameserver missing report cannot pass ' + name, name not in a.reports(sample))
    a.write(sample / 'qa/apache-registered-nameserver-requests-upgrade-retention.json', {'phases': {}})
    check('Client nameserver retention diagnostic is not an acceptance report', not a.reports(sample))
    check('Client nameserver captures are exact and disjoint from existing captures',
          extension['screenshots'] == CAPTURES and len(set(CAPTURES)) == 8
          and not set(CAPTURES).intersection(prior['registered_nameserver_changes_installed']['screenshots']))
    for name in CAPTURES:
        check('Client nameserver capture has browser evidence ownership ' + name,
              a.evidence_allowed('qa/' + name, 'native-browser') and not a.evidence_allowed('qa/' + name, 'apache'))
    workflow = root.joinpath('.github/workflows/application-tests.yml').read_text(encoding='utf-8')
    block = workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n', 1)[1].split('      - name: Real HTTP', 1)[0]
    check('Client nameserver actual full workflow matches all173 native commands',
          [line.strip() for line in block.splitlines()] == contract['native_commands'])
    check('Client nameserver installed commands append after both original producers',
          extension['apache_commands'] == UPGRADE and all(workflow.count(command) == 1 for command in UPGRADE)
          and workflow.index('.ci-venv/bin/python -B tests/registered_nameserver_changes_upgrade.py')
          < workflow.index(UPGRADE[0]) < workflow.index(UPGRADE[1]))
    check('Client nameserver full workflow keeps one original browser execution',
          workflow.count('.ci-venv/bin/python tests/browser_acceptance.py') == 1)
    focused = root.joinpath('.github/workflows/provider-nameserver-changes.yml').read_text(encoding='utf-8')
    docker_commands = [command.replace('"$PHP_BINARY"', 'php').replace('-n -d extension=tokenizer -d extension=pdo', '-n') for command in NATIVE]
    check('Client nameserver focused official PHP commands preserve plain minimal mode',
          all(focused.count(command + '\n') == 1 for command in docker_commands)
          and '-d extension=tokenizer' not in focused
          and focused.index('php tests/registered_nameserver_changes_native.php\n') < focused.index(docker_commands[0] + '\n'))
    check('Client nameserver focused evidence lists every new report exactly once for upload',
          all(focused.count('            qa/' + name + '\n') == 1 for name in NATIVE_REPORTS)
          and 'test-runtime/' not in focused)
    check('Client nameserver full and focused lanes keep immutable original runs',
          'cancel-in-progress: false' in workflow and 'cancel-in-progress: false' in focused
          and focused.count('timeout-minutes: 20') == 1 and focused.count('timeout-minutes: 10') == 1)
    # The existing TLS suite keeps each original test body; new second-resource
    # probes add outcomes in that same required report, never replace its cases.
    fixture_path = 'tests/registered_nameserver_changes_http_fixture_test.py'
    old_tree = ast.parse(restored[fixture_path])
    new_tree = ast.parse(root.joinpath(fixture_path).read_text(encoding='utf-8'))
    def methods(tree):
        return {node.name: ast.dump(node, include_attributes=False) for node in ast.walk(tree)
                if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)) and node.name.startswith('test_')}
    before, after = methods(old_tree), methods(new_tree)
    check('Client nameserver TLS extension preserves all original declared test method bodies',
          len(before) == 20 and all(after.get(name) == body for name, body in before.items()))
    check('Client nameserver old owner fixture adaptation preserves all existing assertions',
          len(extension['source_delta']['tests/registered_nameserver_changes.php']['replacements']) == 1
          and 'registered_nameserver_requests_graph():?array {return null;}' in root.joinpath('tests/registered_nameserver_changes.php').read_text())
    return {'contract': prior, 'source': restored, 'reports': preceding_reports}
