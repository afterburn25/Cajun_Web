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
    contract = browser_contract(contract)
    ext = contract['hosting_purchase_inbox']
    require(ext['base'] == BASE and ext['additions'] == COMMANDS and ext['additions_after'] == AFTER, 'Purchase-inbox command contract changed')
    commands = contract['native_commands']
    position = commands.index(AFTER) + 1
    require(len(commands) == 184 + len(COMMANDS) and commands[position:position+len(COMMANDS)] == COMMANDS
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
    for lane, before in [('browser', 201), ('final', 252)]:
        values = reports[lane]
        require(len(values) == len(set(values)) == before + len(REPORTS)
                and values[-len(REPORTS):] == REPORTS
                and all(values.count(name) == 1 for name in REPORTS), 'Purchase-inbox required reports changed')
        prior[lane] = values[:-len(REPORTS)]
    require(sha(canonical(prior)) == ext['predecessor_reports_sha256'], 'Original0dd report inventory changed')
    return prior


def previous_source(root, path):
    """Invert only declared current source, never independent synthetic test trees."""
    raw = browser_source(root, path)
    if root.resolve() != Path(__file__).resolve().parents[1]:
        return raw
    contract = browser_contract(json.loads(root.joinpath('tools/acceptance-contract.json').read_bytes()))
    ext = contract['hosting_purchase_inbox']
    delta = ext['source_delta'].get(path)
    return inverse(raw, delta) if delta is not None else raw


def _run_inbox_predecessor(a, root, tmp, check, deny, named, contract):
    ext = contract['hosting_purchase_inbox']
    require(ext['screenshots'] == [], 'Purchase inbox cannot replace captured-image requirements')
    require(set(ext['source_sha256']) == SOURCES and set(ext['source_delta']) == HISTORICAL
            and set(ext['unchanged_source_sha256']) == UNCHANGED, 'Purchase-inbox exact source boundary changed')
    for path, expected in ext['source_sha256'].items():
        a.safe_name(path)
        require(sha(browser_source(root, path)) == expected, 'Purchase-inbox current source differs: ' + path)
    check('Purchase inbox binds actual production, tests and wiring before historical projection', bool(ext['source_sha256']))
    for path, delta in ext['source_delta'].items():
        current = browser_source(root, path)
        inverse(current, delta)
        try:
            inverse(current + b'\n# changed', delta)
        except ValueError:
            pass
        else:
            raise AssertionError('Altered purchase-inbox source passed: ' + path)
    check('Purchase inbox whole source inverses preserve predecessors and refuse altered bytes', bool(ext['source_delta']))
    prior = previous_contract(contract)
    check('Purchase inbox retains every original176 native command and whole0dd contract', len(prior['native_commands']) == 184)
    for label, change in [
        ('missing', lambda c:c['native_commands'].remove(COMMANDS[0])),
        ('duplicate', lambda c:c['native_commands'].append(COMMANDS[0])),
        ('changed original', lambda c:c['native_commands'].__setitem__(0, c['native_commands'][0] + ' altered')),
    ]:
        wrong = copy.deepcopy(contract);change(wrong)
        deny('Purchase inbox rejects ' + label + ' recipe', lambda c=wrong:previous_contract(c))
    full = browser_source(root, '.github/workflows/application-tests.yml').decode()
    native = full.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    check('Purchase inbox full workflow executes the exact additive recipe', [line.strip() for line in native.splitlines()] == contract['native_commands'])
    focused = browser_source(root, '.github/workflows/catalog-storefront.yml').decode()
    docker_commands = [command.replace('"$PHP_BINARY"', 'php').replace(' -d extension=pdo -d extension=tokenizer ', ' ').replace(' -d extension=tokenizer ', ' ') for command in COMMANDS]
    check('Purchase inbox focused matrix appends exact commands with supported minimal runtime',
          all(focused.count(command) == 1 for command in docker_commands)
          and '\n'.join('              ' + command for command in docker_commands) in focused
          and 'codex/twentyi-hosting-purchase-inbox' in focused
          and '-d extension=tokenizer' not in focused and '-d extension=pdo' not in focused)
    reports = a.load(root/'tools/acceptance-reports.json')
    prior_reports = previous_reports(reports, ext)
    check('Purchase inbox retains all201 browser and252 final reports before its additions', len(prior_reports['browser']) == 201 and len(prior_reports['final']) == 252)
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
          all(sha(browser_source(root, path)) == expected for path, expected in ext['unchanged_source_sha256'].items()))
    deny('Purchase inbox private runtime remains excluded from evidence', lambda:a.evidence_allowed('test-runtime/hosting-checkout-purchases-private/provider-master.key','native-browser'))
    return prior, prior_reports

# Bounded installed inbox continuation. All historical checks below receive exact
# predecessor bytes only after every current dependency and serialized pin passes.
BROWSER_BASE = '7535f861c2c3accf37482f5af94099780c7806a6'
BROWSER_BRANCH = 'codex/twentyi-purchase-inbox-browser'
BROWSER_CAPTURES = ['Hosting-Purchase-Inbox-' + stem + '-' + size + '.png'
                    for stem in ('Owner', 'Client') for size in ('Desktop', 'Mobile')]
BROWSER_SOURCES = {
    '.github/workflows/application-tests.yml', '.github/workflows/catalog-storefront.yml',
    'tests/hosting_purchase_inbox_wiring.py', 'tests/hosting_checkout_http_browser.py',
    'tests/hosting_purchase_inbox_cases.py', 'tests/hosting_purchases_views.php',
    'site/includes/layout/service_orders.php',
}
BROWSER_HISTORY = BROWSER_SOURCES - {'tests/hosting_purchase_inbox_cases.py'}
BROWSER_UNCHANGED = {
    'tools/acceptance-reports.json', 'tools/acceptance_artifacts.py', 'tests/browser_acceptance.py',
    'tests/acceptance_artifacts_test.py', 'tests/imported_plan_setup_wiring.py',
    'tests/hosting_checkout_http_fixture.php', 'tests/hosting_checkout_http_cases.py',
    'tests/catalog_storefront_checkout_cases.py', 'tests/hosting_catalog_plan_setup_cases.py',
    'tests/hosting_checkout_native.php', 'tests/hosting_checkout_purchases_native.php',
    '.github/workflows/hosting-catalog-offers.yml',
}


def browser_contract(contract):
    contract = policy_contract(contract)
    if 'hosting_purchase_inbox_browser' not in contract:
        return contract
    ext = contract['hosting_purchase_inbox_browser']
    require(ext['base'] == BROWSER_BASE and ext['branch'] == BROWSER_BRANCH
            and ext['http_report'] == 'hosting-checkout-http-results.json'
            and ext['screenshots'] == BROWSER_CAPTURES, 'Installed purchase inbox boundary changed')
    prior = copy.deepcopy(contract)
    del prior['hosting_purchase_inbox_browser']
    for pin in ext['updated_pins']:
        value = prior
        for key in pin['location']:
            value = value[key]
        require(value[pin['path']] == pin['current_sha256'], 'Installed inbox serialized current pin differs')
        value[pin['path']] = pin['predecessor_sha256']
    require(sha(canonical(prior)) == ext['predecessor_contract_sha256'], 'Original7535 acceptance contract changed')
    return prior


def browser_source(root, path):
    raw = policy_source(root, path)
    if root.resolve() != Path(__file__).resolve().parents[1]:
        return raw
    ext = policy_contract(json.loads(root.joinpath('tools/acceptance-contract.json').read_bytes()))['hosting_purchase_inbox_browser']
    delta = ext['source_delta'].get(path)
    return inverse(raw, delta) if delta is not None else raw


def browser_checks(a, root, tmp, check, deny, named, contract):
    import ast
    ext = contract['hosting_purchase_inbox_browser']
    require(set(ext['source_sha256']) == BROWSER_SOURCES and set(ext['source_delta']) == BROWSER_HISTORY
            and set(ext['unchanged_source_sha256']) == BROWSER_UNCHANGED, 'Installed inbox exact source set changed')
    for key in ('source_sha256', 'unchanged_source_sha256'):
        for path, expected in ext[key].items():
            a.safe_name(path)
            require(sha(policy_source(root, path)) == expected,
                    'Installed inbox current source differs: ' + path)
    check('Installed purchase inbox validates every current source before predecessor projection', True)
    for path, delta in ext['source_delta'].items():
        raw = policy_source(root, path)
        inverse(raw, delta)
        deny('Installed purchase inbox rejects changed source ' + path, lambda r=raw, d=delta:inverse(r + b'\n# altered', d))
    prior = browser_contract(contract)
    check('Installed purchase inbox preserves the complete7535 contract and189 native recipes', len(prior['native_commands']) == 189)
    wrong = copy.deepcopy(contract); wrong['native_commands'][0] += ' changed'
    deny('Installed purchase inbox rejects altered historical recipe', lambda:browser_contract(wrong))
    for pin in ext['updated_pins']:
        wrong = copy.deepcopy(contract); value = wrong
        for key in pin['location']:value = value[key]
        value[pin['path']] = '0' * 64
        deny('Installed purchase inbox rejects stale serialized pin ' + '/'.join(pin['location']) + '/' + pin['path'], lambda w=wrong:browser_contract(w))
    reports = a.load(root/'tools/acceptance-reports.json')
    check('Installed purchase inbox preserves all206 browser and257 final report requirements',
          len(reports['browser']) == 206 and len(reports['final']) == 257
          and sha(canonical(reports)) == ext['predecessor_reports_sha256'])
    for path in ('.github/workflows/application-tests.yml', '.github/workflows/catalog-storefront.yml'):
        current = root.joinpath(path).read_text(encoding='utf-8')
        previous = browser_source(root, path).decode('utf-8')
        line = next(line for line in previous.splitlines(True) if line.startswith('    branches: ['))
        check('Installed purchase inbox only appends its push branch in ' + path,
              current == previous.replace(line, line.replace(']\n', ', ' + BROWSER_BRANCH + ']\n'), 1))
    runner_path = root/'tests/hosting_checkout_http_browser.py'
    tree = ast.parse(runner_path.read_text(encoding='utf-8'))
    case_tree = ast.parse(root.joinpath('tests/hosting_purchase_inbox_cases.py').read_text(encoding='utf-8'))
    captures_node = [n for n in case_tree.body if isinstance(n, ast.Assign)
                     and any(isinstance(t, ast.Name) and t.id == 'CAPTURES' for t in n.targets)]
    require(len(captures_node) == 1, 'Exact installed inbox capture declaration required')
    constants = {}; exec(compile(ast.Module(body=captures_node, type_ignores=[]), 'capture-constant', 'exec'), constants)
    check('Installed purchase inbox declares exactly four new capture names', constants['CAPTURES'] == tuple(BROWSER_CAPTURES))
    predecessor_tree = ast.parse(browser_source(root, 'tests/hosting_checkout_http_browser.py').decode())
    old_checks = [ast.dump(n, include_attributes=False) for n in ast.walk(predecessor_tree)
                  if isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id == 'check']
    current_checks = [ast.dump(n, include_attributes=False) for n in ast.walk(tree)
                      if isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id == 'check']
    check('Installed purchase inbox retains every original runner check expression and order',
          [n for n in current_checks if n in set(old_checks)] == old_checks)
    # Execute current output-collision code only, with no web process or fixture.
    names = {'require', 'output_paths', 'prepare_outputs'}
    definitions = [n for n in tree.body if isinstance(n, ast.FunctionDef) and n.name in names]
    require(len(definitions) == 3, 'Exact runner output guards required')
    local = tmp/'purchase-inbox-output';local.mkdir();qa = local/'qa';qa.mkdir()
    def no_runtime(path):raise AssertionError('Collision guard reached runtime creation')
    env = {'ROOT':local, 'WEB':local/'web', 'SITE':local/'web/site', 'SESSIONS':local/'sessions',
           'ALL_CAPTURES':tuple(BROWSER_CAPTURES), 'fresh_directory':no_runtime}
    exec(compile(ast.Module(body=definitions, type_ignores=[]), str(runner_path), 'exec'), env)
    for name in BROWSER_CAPTURES:
        target = qa/name;target.write_bytes(b'original')
        try:env['prepare_outputs'](True)
        except RuntimeError as error:refused = str(error) == 'Preserve prior checkout HTTP outputs'
        else:refused = False
        check('Installed purchase inbox refuses capture overwrite ' + name, refused and target.read_bytes() == b'original' and not env['WEB'].exists())
        target.unlink()
        check('Installed purchase inbox capture stays in native-browser ' + name,
              a.evidence_allowed('qa/'+name,'native-browser') and all(not a.evidence_allowed('qa/'+name,lane) for lane in ('build','apache','recovery')))
    label = ext['result_check']
    calls = [n for n in ast.walk(tree) if isinstance(n, ast.Expr) and isinstance(n.value, ast.Call)
             and isinstance(n.value.func, ast.Name) and n.value.func.id == 'check' and n.value.args
             and isinstance(n.value.args[0], ast.Constant) and n.value.args[0].value == label]
    require(len(calls) == 1, 'Actual runner must require the inbox result exactly once')
    predicate = compile(ast.Module(body=calls, type_ignores=[]), str(runner_path), 'exec')
    result = {'purchase_inbox':{'passed':True,'captures':[{'name':n} for n in BROWSER_CAPTURES]}}
    for name in BROWSER_CAPTURES:(qa/name).write_bytes(b'synthetic guard only')
    def exact_check(name, condition):require(name == label and condition is True, 'Installed inbox actual result predicate refused')
    def verify(value):exec(predicate, {'result':value,'qa':qa,'INBOX_CAPTURES':tuple(BROWSER_CAPTURES),'check':exact_check})
    verify(result);check('Installed purchase inbox actual result predicate accepts exact bounded output',True)
    for title, mutate in [
        ('failed result',lambda r:r['purchase_inbox'].update(passed=False)),
        ('truthy nonboolean result',lambda r:r['purchase_inbox'].update(passed=1)),
        ('missing result',lambda r:r.pop('purchase_inbox')),
        ('missing capture',lambda r:r['purchase_inbox']['captures'].pop()),
        ('reordered captures',lambda r:r['purchase_inbox']['captures'].reverse()),
        ('duplicate capture',lambda r:r['purchase_inbox']['captures'].__setitem__(1,r['purchase_inbox']['captures'][0])),
    ]:
        bad = copy.deepcopy(result);mutate(bad)
        deny('Installed purchase inbox runner refuses '+title, lambda r=bad:verify(r))
    for mode in ('missing','empty'):
        path = qa/BROWSER_CAPTURES[0]
        if mode == 'missing':path.unlink()
        else:path.write_bytes(b'')
        deny('Installed purchase inbox runner refuses '+mode+' PNG',lambda:verify(result))
        path.write_bytes(b'synthetic guard only')
    check('Installed purchase inbox keeps business fixture and private-runtime transport unchanged',
          sha(root.joinpath('tests/hosting_checkout_http_fixture.php').read_bytes().replace(b'\r\n',b'\n')) == ext['unchanged_source_sha256']['tests/hosting_checkout_http_fixture.php'])
    check('Installed purchase inbox never exports private session state',
          a.evidence_allowed('test-runtime/hosting-checkout-browser-sessions/sess_private','native-browser') is False)
    return prior


def _run_policy_predecessor(a, root, tmp, check, deny, named, contract):
    prior = browser_checks(a, root, tmp, check, deny, named, contract)
    return _run_inbox_predecessor(a, root, tmp, check, deny, named, prior)

# Exact source correction only; recipes, reports, captures and all older guards stay intact.
POLICY_BASE = '3042f24eddc3587d67ac5d10e28df3b3294a3310'
POLICY_SECTION = 'hosting_checkout_manual_policy_ui'
POLICY_SOURCES = {
    'site/includes/layout/service_orders.php',
    'site/includes/layout/hosting_order_links.php',
    'site/includes/layout/hosting_fulfillment.php',
    'tests/hosting_purchases_views.php',
    'tests/hosting_purchase_inbox_cases.py',
    'tests/hosting_purchase_inbox_wiring.py',
}
POLICY_UNCHANGED = {
    '.github/workflows/application-tests.yml', '.github/workflows/catalog-storefront.yml',
    'tests/acceptance_artifacts_test.py', 'tests/hosting_checkout_http_browser.py',
    'tests/hosting_checkout_http_fixture.php', 'tests/hosting_checkout_http_cases.py',
    'tests/hosting_checkout_purchases_native.php', 'tools/acceptance-reports.json',
    'tools/acceptance_artifacts.py',
}


def policy_contract(contract):
    if POLICY_SECTION not in contract:
        return contract
    ext = contract[POLICY_SECTION]
    require(ext['base'] == POLICY_BASE and ext['added_commands'] == []
            and ext['added_reports'] == [] and ext['added_captures'] == [],
            'Checkout policy correction cannot change acceptance producers')
    prior = copy.deepcopy(contract)
    del prior[POLICY_SECTION]
    for pin in ext['updated_pins']:
        value = prior
        for part in pin['location']:
            value = value[part]
        require(value[pin['path']] == pin['current_sha256'], 'Checkout policy current serialized pin differs')
        value[pin['path']] = pin['predecessor_sha256']
    require(sha(canonical(prior)) == ext['predecessor_contract_sha256'], 'Original3042 acceptance contract changed')
    return prior


def policy_source(root, path):
    raw = root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
    if root.resolve() != Path(__file__).resolve().parents[1]:
        return raw
    contract = json.loads(root.joinpath('tools/acceptance-contract.json').read_bytes())
    ext = contract.get(POLICY_SECTION)
    if ext is None:
        return raw
    delta = ext['source_delta'].get(path)
    return inverse(raw, delta) if delta is not None else raw


def policy_checks(a, root, check, deny, contract):
    import ast
    ext = contract[POLICY_SECTION]
    require(set(ext['source_sha256']) == POLICY_SOURCES
            and set(ext['source_delta']) == POLICY_SOURCES
            and set(ext['unchanged_source_sha256']) == POLICY_UNCHANGED,
            'Checkout policy exact source boundary changed')
    before = {}
    for path, expected in ext['source_sha256'].items():
        a.safe_name(path)
        raw = root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
        require(sha(raw) == expected, 'Checkout policy current source differs: ' + path)
        before[path] = inverse(raw, ext['source_delta'][path])
        for changed, delta in [(raw + b'\n# changed', ext['source_delta'][path]),
                               (raw, dict(ext['source_delta'][path], predecessor_sha256='0' * 64))]:
            try:
                inverse(changed, delta)
            except ValueError:
                pass
            else:
                raise AssertionError('Checkout policy altered source or predecessor passed: ' + path)
    check('Checkout policy correction binds all current sources before exact historical projection', True)
    check('Checkout policy correction whole inverses reject changed source and predecessor hashes', True)
    prior = policy_contract(contract)
    check('Checkout policy correction preserves the complete3042 contract and189 native recipes', len(prior['native_commands']) == 189)
    for label, mutate in [
        ('removed original command', lambda value:value['native_commands'].pop()),
        ('altered original command', lambda value:value['native_commands'].__setitem__(0, 'changed')),
    ]:
        wrong = copy.deepcopy(contract); mutate(wrong)
        deny('Checkout policy correction refuses ' + label, lambda value=wrong:policy_contract(value))
    for pin in ext['updated_pins']:
        wrong = copy.deepcopy(contract); value = wrong
        for part in pin['location']:
            value = value[part]
        value[pin['path']] = '0' * 64
        try:
            policy_contract(wrong)
        except ValueError:
            pass
        else:
            raise AssertionError('Checkout policy stale current pin passed')
    check('Checkout policy correction refuses every stale current serialized pin', True)
    reports = a.load(root/'tools/acceptance-reports.json')
    check('Checkout policy correction preserves all206 browser and257 final reports',
          len(reports['browser']) == 206 and len(reports['final']) == 257
          and sha(canonical(reports)) == ext['predecessor_reports_sha256'])
    check('Checkout policy correction leaves both workflows and installed fixture unchanged',
          all(sha(root.joinpath(path).read_bytes().replace(b'\r\n', b'\n')) == expected
              for path, expected in ext['unchanged_source_sha256'].items()))
    path = 'tests/hosting_purchase_inbox_cases.py'
    old_tree = ast.parse(before[path].decode())
    current_tree = ast.parse(root.joinpath(path).read_text(encoding='utf-8'))
    def checks(tree):
        return [ast.dump(node, include_attributes=False) for node in ast.walk(tree)
                if isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id == 'check']
    old_checks = checks(old_tree); current_checks = checks(current_tree)
    check('Checkout policy correction retains every original installed inbox check expression and order',
          [item for item in current_checks if item in set(old_checks)] == old_checks)
    new_checks = [node for node in ast.walk(current_tree)
                  if isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id == 'check'
                  and ast.dump(node, include_attributes=False) not in set(old_checks)]
    check('Checkout policy correction adds only its installed verified-payment assertion',
          len(new_checks) == 1 and isinstance(new_checks[0].args[0], ast.Constant)
          and new_checks[0].args[0].value == 'Checkout owner uses verified-payment setup without legacy manual controls')
    def captures(tree):
        return [ast.dump(node, include_attributes=False) for node in tree.body
                if isinstance(node, ast.Assign) and any(isinstance(t, ast.Name) and t.id == 'CAPTURES' for t in node.targets)]
    check('Checkout policy correction preserves the four original capture declarations',
          len(captures(old_tree)) == 1 and captures(current_tree) == captures(old_tree))
    return prior


def run(a, root, tmp, check, deny, named, contract):
    prior = policy_checks(a, root, check, deny, contract)
    return _run_policy_predecessor(a, root, tmp, check, deny, named, prior)
