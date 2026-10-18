"""Finite purchase-inbox additions; older guards receive their exact reviewed inputs."""
import copy
import hashlib
import json
from pathlib import Path

BASE = '0ddbd23d6489e2b9445304123e214d0a2e188f31'
COMMANDS = [
    '"$PHP_BINARY" tests/hosting_checkout_purchases_guards.php > qa/hosting-checkout-purchases-default.json',
    '"$PHP_BINARY" -n -d extension=pdo -d extension=tokenizer tests/hosting_checkout_purchases_guards.php > qa/hosting-checkout-purchases-minimal.json',
    '"$PHP_BINARY" tests/hosting_purchases_views.php > qa/hosting-purchases-views-default.json',
    '"$PHP_BINARY" -n -d extension=tokenizer tests/hosting_purchases_views.php > qa/hosting-purchases-views-minimal.json',
    'CWP_TEST_HOSTING_PURCHASES=1 "$PHP_BINARY" tests/hosting_checkout_purchases_native.php',
]
REPORTS = ['hosting-checkout-purchases-default.json', 'hosting-checkout-purchases-minimal.json', 'hosting-purchases-views-default.json', 'hosting-purchases-views-minimal.json', 'hosting-checkout-purchases-native-results.json']
AFTER = 'CWP_TEST_HOSTING_PLAN_SETUP=1 "$PHP_BINARY" tests/hosting_catalog_plan_setup_native.php'
SOURCES = {
    'site/app/hosting/checkout_purchases.php', 'site/app/hosting/order_views.php', 'site/app/hosting/checkout_purchases_views.php',
    'site/admin/controllers/hosting_purchases.php', 'site/admin/views/hosting_purchases.php',
    'site/portal/controllers/hosting_purchases.php', 'site/portal/views/hosting_purchases.php',
    'site/admin/controllers/router.php', 'site/portal/controllers/router.php', 'site/app/staff/accounts.php',
    'site/includes/layout/admin.php', 'site/admin/views/hosting_catalog.php', 'site/portal/views/hosting_catalog.php',
    'site/portal/views/hosting_checkout.php', 'tests/hosting_checkout_purchases_guards.php',
    'tests/hosting_purchases_views.php', 'tests/hosting_checkout_purchases_native.php',
    '.github/workflows/application-tests.yml', '.github/workflows/catalog-storefront.yml',
    'tests/acceptance_artifacts_test.py', 'tests/imported_plan_setup_wiring.py',
    'tests/hosting_purchase_inbox_wiring.py', 'tools/acceptance-reports.json',
}
HISTORICAL = SOURCES - {
    'site/app/hosting/checkout_purchases.php', 'site/app/hosting/checkout_purchases_views.php',
    'site/admin/controllers/hosting_purchases.php', 'site/admin/views/hosting_purchases.php',
    'site/portal/controllers/hosting_purchases.php', 'site/portal/views/hosting_purchases.php',
    'tests/hosting_checkout_purchases_guards.php', 'tests/hosting_purchases_views.php',
    'tests/hosting_checkout_purchases_native.php', 'tests/hosting_purchase_inbox_wiring.py',
}
UNCHANGED = {
    'tools/acceptance_artifacts.py', 'tests/browser_acceptance.py', 'tests/hosting_checkout_http_browser.py',
    'tests/hosting_checkout_http_cases.py', 'tests/catalog_storefront_checkout_cases.py',
    'tests/hosting_catalog_plan_setup_cases.py', 'tests/hosting_checkout_http_fixture.php',
    '.github/workflows/hosting-catalog-offers.yml',
}


def require(ok, message):
    if not ok:
        raise ValueError(message)


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()


def inverse(raw, delta):
    require(set(delta) == {'predecessor_sha256', 'current_sha256', 'replacements'}, 'Unexpected purchase-inbox inverse fields')
    require(sha(raw) == delta['current_sha256'], 'Purchase-inbox current source changed')
    for edit in reversed(delta['replacements']):
        require(set(edit) == {'before', 'after'} and edit['before'] and edit['after'], 'Invalid purchase-inbox replacement')
        before, after = edit['before'].encode(), edit['after'].encode()
        require(raw.count(after) == 1, 'Purchase-inbox inverse is not unique')
        raw = raw.replace(after, before, 1)
    require(sha(raw) == delta['predecessor_sha256'], 'Purchase-inbox predecessor source changed')
    return raw


def previous_contract(contract):
    ext = contract['hosting_purchase_inbox']
    require(ext['base'] == BASE and ext['additions'] == COMMANDS and ext['additions_after'] == AFTER, 'Purchase-inbox command contract changed')
    commands = contract['native_commands']
    position = commands.index(AFTER) + 1
    require(len(commands) == 176 + len(COMMANDS) and commands[position:position+len(COMMANDS)] == COMMANDS
            and all(commands.count(command) == 1 for command in COMMANDS), 'Purchase-inbox recipe missing, duplicated or reordered')
    prior = copy.deepcopy(contract)
    del prior['hosting_purchase_inbox']
    prior['native_commands'] = commands[:position] + commands[position+len(COMMANDS):]
    for pin in ext['updated_pins']:
        value = prior
        for part in pin['location']:
            value = value[part]
        require(value[pin['path']] == pin['current_sha256'], 'Purchase-inbox current serialized pin differs')
        value[pin['path']] = pin['predecessor_sha256']
    require(sha(canonical(prior)) == ext['predecessor_contract_sha256'], 'Original0dd acceptance contract changed')
    return prior


def previous_reports(reports, ext):
    require(ext['reports'] == REPORTS, 'Purchase-inbox report contract changed')
    prior = copy.deepcopy(reports)
    for lane, before in [('browser', 197), ('final', 248)]:
        values = reports[lane]
        require(len(values) == len(set(values)) == before + len(REPORTS)
                and values[-len(REPORTS):] == REPORTS
                and all(values.count(name) == 1 for name in REPORTS), 'Purchase-inbox required reports changed')
        prior[lane] = values[:-len(REPORTS)]
    require(sha(canonical(prior)) == ext['predecessor_reports_sha256'], 'Original0dd report inventory changed')
    return prior


def previous_source(root, path):
    """Invert only declared current source, never independent synthetic test trees."""
    raw = root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
    if root.resolve() != Path(__file__).resolve().parents[1]:
        return raw
    contract = json.loads(root.joinpath('tools/acceptance-contract.json').read_bytes())
    ext = contract['hosting_purchase_inbox']
    delta = ext['source_delta'].get(path)
    return inverse(raw, delta) if delta is not None else raw


def run(a, root, tmp, check, deny, named, contract):
    ext = contract['hosting_purchase_inbox']
    require(ext['screenshots'] == [], 'Purchase inbox cannot replace captured-image requirements')
    require(set(ext['source_sha256']) == SOURCES and set(ext['source_delta']) == HISTORICAL
            and set(ext['unchanged_source_sha256']) == UNCHANGED, 'Purchase-inbox exact source boundary changed')
    for path, expected in ext['source_sha256'].items():
        a.safe_name(path)
        require(sha(root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')) == expected, 'Purchase-inbox current source differs: ' + path)
    check('Purchase inbox binds actual production, tests and wiring before historical projection', bool(ext['source_sha256']))
    for path, delta in ext['source_delta'].items():
        current = root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
        inverse(current, delta)
        try:
            inverse(current + b'\n# changed', delta)
        except ValueError:
            pass
        else:
            raise AssertionError('Altered purchase-inbox source passed: ' + path)
    check('Purchase inbox whole source inverses preserve predecessors and refuse altered bytes', bool(ext['source_delta']))
    prior = previous_contract(contract)
    check('Purchase inbox retains every original176 native command and whole0dd contract', len(prior['native_commands']) == 176)
    for label, change in [
        ('missing', lambda c:c['native_commands'].remove(COMMANDS[0])),
        ('duplicate', lambda c:c['native_commands'].append(COMMANDS[0])),
        ('changed original', lambda c:c['native_commands'].__setitem__(0, c['native_commands'][0] + ' altered')),
    ]:
        wrong = copy.deepcopy(contract);change(wrong)
        deny('Purchase inbox rejects ' + label + ' recipe', lambda c=wrong:previous_contract(c))
    full = root.joinpath('.github/workflows/application-tests.yml').read_text()
    native = full.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    check('Purchase inbox full workflow executes the exact additive recipe', [line.strip() for line in native.splitlines()] == contract['native_commands'])
    focused = root.joinpath('.github/workflows/catalog-storefront.yml').read_text()
    docker_commands = [command.replace('"$PHP_BINARY"', 'php').replace(' -d extension=pdo -d extension=tokenizer ', ' ').replace(' -d extension=tokenizer ', ' ') for command in COMMANDS]
    check('Purchase inbox focused matrix appends exact commands with supported minimal runtime',
          all(focused.count(command) == 1 for command in docker_commands)
          and '\n'.join('              ' + command for command in docker_commands) in focused
          and 'codex/twentyi-hosting-purchase-inbox' in focused
          and '-d extension=tokenizer' not in focused and '-d extension=pdo' not in focused)
    reports = a.load(root/'tools/acceptance-reports.json')
    prior_reports = previous_reports(reports, ext)
    check('Purchase inbox retains all197 browser and248 final reports before its additions', len(prior_reports['browser']) == 197 and len(prior_reports['final']) == 248)
    wrong = copy.deepcopy(reports);wrong['browser'].remove(REPORTS[0])
    deny('Purchase inbox cannot omit a required report', lambda:previous_reports(wrong, ext))
    wrong = copy.deepcopy(reports);wrong['final'][0] += '.changed'
    deny('Purchase inbox cannot replace a prior required report', lambda:previous_reports(wrong, ext))
    sample = tmp/'purchase-inbox-reports'
    for name in REPORTS:
        named(sample/'qa'/name, False)
        require(not a.reports(sample)[name]['passed'], 'Failed purchase-inbox report became successful')
        require(a.evidence_allowed('qa/'+name,'native-browser')
                and all(not a.evidence_allowed('qa/'+name,lane) for lane in ('build','recovery','apache')), 'Wrong purchase-inbox report partition')
        (sample/'qa'/name).unlink()
        require(name not in a.reports(sample), 'Missing purchase-inbox report appeared')
    check('Purchase inbox failed and missing reports remain failures and stay in their actual lane', True)
    check('Purchase inbox preserves original browser, transfer helper and captures',
          all(sha(root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')) == expected for path, expected in ext['unchanged_source_sha256'].items()))
    deny('Purchase inbox private runtime remains excluded from evidence', lambda:a.evidence_allowed('test-runtime/hosting-checkout-purchases-private/provider-master.key','native-browser'))
    return prior, prior_reports
