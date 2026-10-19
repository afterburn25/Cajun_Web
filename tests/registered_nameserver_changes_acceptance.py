"""Exact additive installed-test wiring; no application, SQL or network execution."""
import copy
import hashlib
import json

BASE = '5654649dc62095298ce703b39b54d12d56c49f09'
REPORTS = {
    'browser': ['registered-nameserver-changes-http-fixture-results.json'],
    'final': ['registered-nameserver-changes-http-fixture-results.json',
              'apache-registered-nameserver-changes-upgrade-guards-results.json',
              'apache-registered-nameserver-changes-upgrade-results.json'],
}
DELTA_PATHS = {
    '.github/workflows/application-tests.yml', '.github/workflows/provider-nameserver-changes.yml',
    'tests/acceptance_artifacts_test.py', 'tests/provider_http_server.py', 'tests/registered_nameservers_cases.py',
}
NEW_PATHS = {
    'tests/registered_nameserver_changes_cases.py', 'tests/registered_nameserver_changes_http_fixture_test.py',
    'tests/registered_nameserver_changes_upgrade.py', 'tests/registered_nameserver_changes_upgrade_fixture.php',
    'tests/registered_nameserver_changes_upgrade_test.py', 'tests/registered_nameserver_changes_acceptance.py',
}


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()


def require(value, message):
    if not value:
        raise ValueError(message)


def source_predecessor(raw, delta):
    require(sha(raw) == delta['current_sha256'], 'Changed installed nameserver test producer')
    for edit in reversed(delta['replacements']):
        require(set(edit) == {'before', 'after'} and edit['before'] and edit['after'], 'Exact source insertion required')
        before, after = edit['before'].encode(), edit['after'].encode()
        require(raw.count(after) == 1, 'Unique original source insertion required')
        raw = raw.replace(after, before, 1)
    require(sha(raw) == delta['predecessor_sha256'], 'Preceding producer source changed')
    return raw


def report_predecessor(reports, extension):
    require(extension['reports'] == REPORTS, 'Exact three installed report names required')
    prior = copy.deepcopy(reports)
    for lane, additions in REPORTS.items():
        require(len(reports[lane]) == len(set(reports[lane])) and all(reports[lane].count(n) == 1 for n in additions), 'Missing or duplicated installed report')
        prior[lane] = [name for name in reports[lane] if name not in additions]
    require(sha(canonical(prior)) == extension['predecessor_reports_sha256'], 'Preceding report sequence changed')
    return prior


def verify(a, root, tmp, check, deny, contract, source_predecessors=None, reports_predecessor=None):
    # Only the outer current-source verifier supplies these exact predecessor bytes.
    source_predecessors = {} if source_predecessors is None else source_predecessors
    def source(path):
        return source_predecessors.get(path, root.joinpath(path).read_bytes().replace(b'\r\n', b'\n'))
    extension = contract['registered_nameserver_changes_installed']
    prior = copy.deepcopy(contract)
    del prior['registered_nameserver_changes_installed']
    check('Nameserver installed tests preserve the entire565 predecessor contract',
          extension['source_predecessor'] == BASE and sha(canonical(prior)) == extension['predecessor_contract_sha256'])
    check('Nameserver installed source changes are limited to declared producers and wiring',
          set(extension['source_delta']) == DELTA_PATHS
          and set(extension['source_sha256']) == DELTA_PATHS | NEW_PATHS | {'tools/acceptance-reports.json'})
    for path, expected in extension['source_sha256'].items():
        check('Nameserver installed current source pin ' + path,
              sha(source(path)) == expected)
    restored = {}
    for path, delta in extension['source_delta'].items():
        raw = source(path)
        restored[path] = source_predecessor(raw, delta)
        check('Nameserver installed retains complete original producer ' + path, True)
        deny('Nameserver installed rejects changed producer ' + path,
             lambda data=raw, proof=delta: source_predecessor(data + b'\n# changed', proof))
    original_sources = {path: restored[path] for path in ('tests/provider_http_server.py', 'tests/registered_nameservers_cases.py')}
    check('Nameserver HTTP predecessor pins match actual recovered sources',
          all(sha(raw) == prior['registered_nameservers']['source_sha256'][path] for path, raw in original_sources.items()))
    unchanged = {'tests/browser_acceptance.py', 'tools/acceptance_artifacts.py',
                 'tests/registered_nameservers_upgrade.py', 'tests/registered_nameservers_upgrade_fixture.php',
                 'tests/registered_nameservers_upgrade_test.py', 'tests/registered_nameserver_changes_native.php'}
    check('Nameserver installed tests retain the original native browser transfer and upgrade producers',
          set(extension['unchanged_source_sha256']) == unchanged
          and all(sha(root.joinpath(p).read_bytes().replace(b'\r\n', b'\n')) == h for p, h in extension['unchanged_source_sha256'].items()))
    reports = a.load(root / 'tools/acceptance-reports.json') if reports_predecessor is None else reports_predecessor
    preceding_reports = report_predecessor(reports, extension)
    check('Nameserver installed reports retain all217 browser and270 final predecessors',
          len(preceding_reports['browser']) == 217 and len(preceding_reports['final']) == 270
          and len(reports['browser']) == 218 and len(reports['final']) == 273)
    for label in ('missing', 'duplicate', 'changed original'):
        wrong = copy.deepcopy(reports)
        if label == 'missing': wrong['final'].remove(REPORTS['final'][-1])
        elif label == 'duplicate': wrong['browser'].append(REPORTS['browser'][0])
        else: wrong['final'][0] += '.changed'
        deny('Nameserver installed report union rejects ' + label, lambda value=wrong: report_predecessor(value, extension))
    sample = tmp / 'nameserver-installed-reports'
    for name in REPORTS['final']:
        lane = 'apache' if name.startswith('apache-') else 'native-browser'
        check('Nameserver installed report has one evidence owner ' + name,
              a.evidence_allowed('qa/' + name, lane)
              and all(not a.evidence_allowed('qa/' + name, other) for other in ('build', 'recovery', 'apache', 'native-browser') if other != lane))
        file = sample / 'qa' / name
        a.write(file, {'passed': False, 'checks': [{'name': 'Synthetic failed outcome', 'passed': False}]})
        check('Nameserver installed failed report cannot pass ' + name, not a.reports(sample)[name]['passed'])
        file.unlink()
        check('Nameserver installed missing report cannot pass ' + name, name not in a.reports(sample))
    captures = ['Nameserver-Change-' + state + '-' + size + '.png'
                for state in ('Ready', 'Interrupted', 'Observed', 'Orphan') for size in ('Desktop', 'Mobile')]
    check('Nameserver installed inventories eight exact new browser captures', extension['screenshots'] == captures)
    for name in captures:
        check('Nameserver change capture retains browser evidence ownership ' + name,
              a.evidence_allowed('qa/' + name, 'native-browser')
              and not a.evidence_allowed('qa/' + name, 'apache'))
    workflow = source('.github/workflows/application-tests.yml').decode('utf-8')
    fixture = '.ci-venv/bin/python -B tests/registered_nameserver_changes_http_fixture_test.py --report qa/' + REPORTS['browser'][0]
    upgrade = ['.ci-venv/bin/python -B tests/registered_nameserver_changes_upgrade_test.py --report qa/' + REPORTS['final'][1],
               '.ci-venv/bin/python -B tests/registered_nameserver_changes_upgrade.py']
    check('Nameserver fixture precedes the unchanged full browser exactly once',
          workflow.count(fixture) == 1 and workflow.count('.ci-venv/bin/python tests/browser_acceptance.py') == 1
          and workflow.index(fixture) < workflow.index('.ci-venv/bin/python tests/browser_acceptance.py'))
    check('Nameserver installed upgrade guards and producer follow the original upgrade once',
          all(workflow.count(command) == 1 for command in upgrade)
          and workflow.index('.ci-venv/bin/python -B tests/registered_nameservers_upgrade.py') < workflow.index(upgrade[0]) < workflow.index(upgrade[1]))
    a.write(sample / 'qa/apache-registered-nameserver-changes-upgrade-retention.json', {'phases': {}})
    check('Nameserver installed retention snapshot cannot masquerade as a passing report', not a.reports(sample))
    return {'workflow': restored['.github/workflows/application-tests.yml'].decode(), 'source': original_sources}
