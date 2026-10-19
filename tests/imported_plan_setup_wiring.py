"""Closed additive imported-plan acceptance checks; all prior checks receive exact preimages."""
import ast
import copy
import hashlib
import json
from pathlib import Path
from hosting_purchase_inbox_wiring import previous_source as inbox_source, previous_contract as inbox_contract

COMMANDS = [
    '"$PHP_BINARY" tests/hosting_catalog_plan_setup_document.php qa/hosting-catalog-plan-setup-document-default.json',
    '"$PHP_BINARY" -n tests/hosting_catalog_plan_setup_document.php qa/hosting-catalog-plan-setup-document-minimal.json',
    '"$PHP_BINARY" tests/hosting_catalog_plan_setup_views.php > qa/hosting-catalog-plan-setup-views-default.json',
    '"$PHP_BINARY" -n -d extension=pdo -d extension=tokenizer tests/hosting_catalog_plan_setup_views.php > qa/hosting-catalog-plan-setup-views-minimal.json',
    'CWP_TEST_HOSTING_PLAN_SETUP=1 "$PHP_BINARY" tests/hosting_catalog_plan_setup_native.php',
]
REPORTS = [
    'hosting-catalog-plan-setup-document-default.json',
    'hosting-catalog-plan-setup-document-minimal.json',
    'hosting-catalog-plan-setup-views-default.json',
    'hosting-catalog-plan-setup-views-minimal.json',
    'hosting-catalog-plan-setup-native-results.json',
]
AFTER = 'CWP_TEST_HOSTING_CHECKOUT=1 "$PHP_BINARY" tests/hosting_checkout_native.php'
CAPTURES = ['Hosting-Plan-Setup-' + stem + '-' + size + '.png' for stem in ('Ready', 'Preview') for size in ('Desktop', 'Mobile')]
SOURCES = {
    'site/app/plans/records.php', 'site/app/providers/plan_catalog.php', 'site/app/hosting/catalog_offers_context.php',
    'site/app/hosting/catalog_offer_setup.php', 'site/app/hosting/catalog_offer_setup_document.php',
    'site/app/hosting/catalog_offer_setup_views.php', 'site/app/hosting/catalog_offer_views.php',
    'site/admin/controllers/hosting_catalog.php', 'site/admin/views/hosting_catalog.php',
    'tests/hosting_catalog_plan_setup_document.php', 'tests/hosting_catalog_plan_setup_views.php', 'tests/hosting_catalog_plan_setup_native.php',
    'tests/hosting_catalog_plan_setup_cases.py', 'tests/catalog_storefront_checkout_cases.py',
    'tests/hosting_checkout_http_browser.py', 'tests/hosting_checkout_http_fixture.php',
    'tests/acceptance_artifacts_test.py', 'tests/imported_plan_setup_wiring.py',
    '.github/workflows/application-tests.yml', '.github/workflows/catalog-storefront.yml', 'tools/acceptance-reports.json',
}
HISTORICAL = SOURCES - {
    'site/app/hosting/catalog_offer_setup.php', 'site/app/hosting/catalog_offer_setup_document.php',
    'site/app/hosting/catalog_offer_setup_views.php', 'tests/hosting_catalog_plan_setup_document.php',
    'tests/hosting_catalog_plan_setup_views.php', 'tests/hosting_catalog_plan_setup_native.php', 'tests/imported_plan_setup_wiring.py',
    'tests/hosting_catalog_plan_setup_cases.py',
}


def require(ok, message):
    if not ok:
        raise ValueError(message)


def sha(value):
    return hashlib.sha256(value).hexdigest()


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()


def inverse(raw, delta):
    require(set(delta) == {'predecessor_sha256', 'current_sha256', 'replacements'}, 'Unexpected imported-plan inverse fields')
    require(sha(raw) == delta['current_sha256'], 'Imported-plan current source changed')
    for edit in reversed(delta['replacements']):
        require(set(edit) == {'before', 'after'} and edit['before'] and edit['after'], 'Invalid imported-plan replacement')
        before, after = edit['before'].encode(), edit['after'].encode()
        require(raw.count(after) == 1, 'Imported-plan exact inverse not unique')
        raw = raw.replace(after, before, 1)
    require(sha(raw) == delta['predecessor_sha256'], 'Imported-plan predecessor source changed')
    return raw


def historical_bytes(root, path):
    """Only the declared finite paths are inverted, after validating their current whole bytes."""
    raw = inbox_source(root, path)
    # Existing helper tests use independent temporary source trees with their own pins.
    if root.resolve() != Path(__file__).resolve().parents[1]:
        return raw
    extension = inbox_contract(json.loads(root.joinpath('tools/acceptance-contract.json').read_bytes()))['imported_plan_setup']
    delta = extension['historical_delta'].get(path)
    return inverse(raw, delta) if delta is not None else raw


def previous_contract(contract):
    ext = contract['imported_plan_setup']
    require(ext['additions'] == COMMANDS and ext['additions_after'] == AFTER, 'Imported-plan commands changed')
    commands = contract['native_commands']
    position = commands.index(AFTER) + 1
    require(len(commands) == 184 and commands[position:position+5] == COMMANDS and all(commands.count(c) == 1 for c in COMMANDS), 'Imported-plan exact recipe missing or reordered')
    prior = copy.deepcopy(contract)
    del prior['imported_plan_setup']
    prior['native_commands'] = commands[:position] + commands[position+5:]
    require(len(prior['native_commands']) == 179, 'Imported-plan predecessor recipe changed')
    for pin in ext['updated_pins']:
        location = prior
        for key in pin['location']:
            location = location[key]
        require(location[pin['path']] == pin['current_sha256'], 'Imported-plan current pin differs')
        location[pin['path']] = pin['predecessor_sha256']
    require(sha(canonical(prior)) == ext['predecessor_contract_sha256'], 'Original460 acceptance contract changed')
    return prior


def previous_reports(reports, extension):
    require(extension['reports'] == REPORTS, 'Imported-plan required reports changed')
    prior = copy.deepcopy(reports)
    for lane, count in [('browser', 200), ('final', 251)]:
        values = reports[lane]
        require(len(values) == len(set(values)) == count and values[-5:] == REPORTS and all(values.count(name) == 1 for name in REPORTS), 'Imported-plan required report union differs')
        prior[lane] = [name for name in values if name not in REPORTS]
    require(sha(canonical(prior)) == extension['predecessor_reports_sha256'], 'Original460 required reports changed')
    return prior


def browser_checks(a, root, tmp, check, deny, extension):
    """Execute only current runner output guards against temporary files; never start the runner."""
    require(extension['screenshots'] == CAPTURES and extension['http_report'] == 'hosting-checkout-http-results.json', 'Imported-plan browser output boundary changed')
    def constant(path, name):
        nodes = [node for node in ast.parse(inbox_source(root, path).decode('utf-8')).body
                 if isinstance(node, ast.Assign) and any(isinstance(target, ast.Name) and target.id == name for target in node.targets)]
        require(len(nodes) == 1, 'One exact capture constant required')
        values = {};exec(compile(ast.Module(body=nodes, type_ignores=[]), path, 'exec'), values)
        return values[name]
    actual_captures = constant('tests/hosting_catalog_plan_setup_cases.py', 'CAPTURES')
    check('Imported plan callback declares exactly the four required captures', actual_captures == tuple(CAPTURES))
    runner_path = root/'tests/hosting_checkout_http_browser.py'
    source = inbox_source(root, 'tests/hosting_checkout_http_browser.py').decode('utf-8')
    tree = ast.parse(source)
    names = {'require', 'output_paths', 'prepare_outputs'}
    definitions = [node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name in names]
    require(len(definitions) == 3, 'Actual runner output functions changed')
    temporary = tmp/'imported-plan-capture-guards';temporary.mkdir()
    def forbidden_runtime(path):
        raise AssertionError('Collision guard reached runtime creation')
    env = {'ROOT': temporary, 'WEB': temporary/'test-runtime/web', 'SITE': temporary/'test-runtime/web/site',
           'SESSIONS': temporary/'test-runtime/sessions', 'ALL_CAPTURES': actual_captures, 'fresh_directory': forbidden_runtime}
    exec(compile(ast.Module(body=definitions, type_ignores=[]), str(runner_path), 'exec'), env)
    qa = temporary/'qa';qa.mkdir()
    def collision_refuses():
        try:
            env['prepare_outputs'](True)
        except RuntimeError as error:
            require(str(error) == 'Preserve prior checkout HTTP outputs', 'Unexpected runner collision failure')
            return True
        return False
    for name in CAPTURES:
        target = qa/name;target.write_bytes(b'existing original capture')
        check('Imported plan actual runner refuses existing capture '+name, collision_refuses())
        check('Imported plan collision preserves original capture '+name, target.read_bytes() == b'existing original capture' and not env['WEB'].exists())
        target.unlink()
        check('Imported plan capture belongs only to native-browser '+name,
              a.evidence_allowed('qa/'+name,'native-browser') and all(not a.evidence_allowed('qa/'+name,lane) for lane in ('build','recovery','apache')))
    predicate_label = 'Installed imported product completes plan setup and separate publication preview'
    calls = [node for node in ast.walk(tree) if isinstance(node, ast.Expr) and isinstance(node.value, ast.Call)
             and isinstance(node.value.func, ast.Name) and node.value.func.id == 'check'
             and node.value.args and isinstance(node.value.args[0], ast.Constant) and node.value.args[0].value == predicate_label]
    require(len(calls) == 1, 'Actual runner must check the imported-plan result exactly once')
    result = {'catalog_storefront': {'plan_setup': {'passed': True, 'captures': [{'name': name} for name in CAPTURES]}}}
    for name in CAPTURES:(qa/name).write_bytes(b'synthetic output guard only')
    def exact_check(name, condition):
        require(name == predicate_label and condition is True, 'Actual imported-plan result predicate refused')
    code = compile(ast.Module(body=calls, type_ignores=[]), str(runner_path), 'exec')
    def verify(value):
        exec(code, {'result': value, 'qa': qa, 'PLAN_SETUP_CAPTURES': tuple(CAPTURES), 'check': exact_check})
    verify(result)
    check('Imported plan actual runner accepts complete synthetic nested result only as an output guard', True)
    for label, edit in [('failed', lambda r:r['catalog_storefront']['plan_setup'].update(passed=False)),
                        ('truthy', lambda r:r['catalog_storefront']['plan_setup'].update(passed=1)),
                        ('missing capture', lambda r:r['catalog_storefront']['plan_setup']['captures'].pop()),
                        ('reordered captures', lambda r:r['catalog_storefront']['plan_setup']['captures'].reverse())]:
        wrong = copy.deepcopy(result);edit(wrong)
        deny('Imported plan actual runner refuses '+label+' result', lambda r=wrong:verify(r))
    verify(result)
    (qa/CAPTURES[0]).unlink()
    deny('Imported plan actual runner refuses missing physical capture', lambda:verify(result))
    (qa/CAPTURES[0]).write_bytes(b'')
    deny('Imported plan actual runner refuses empty physical capture', lambda:verify(result))
    (qa/CAPTURES[0]).write_bytes(b'restored synthetic output guard only');verify(result)
    check('Imported plan runner collision and report ownership cover all captures',
          'ALL_CAPTURES = CAPTURES + STOREFRONT_CAPTURES + PLAN_SETUP_CAPTURES' in source
          and "[qa/n for n in ALL_CAPTURES]" in source)
    check('Imported plan browser continuation preserves original callback bytes before its append',
          inbox_source(root, 'tests/catalog_storefront_checkout_cases.py').startswith(historical_bytes(root,'tests/catalog_storefront_checkout_cases.py')))


def run(a, root, tmp, check, deny, named, contract, reports=None):
    ext = contract['imported_plan_setup']
    require(ext['base'] == '460eebfb5d8d87c3816576aeb7289bfa90478668', 'Imported-plan predecessor identity changed')
    require(set(ext['source_sha256']) == SOURCES and set(ext['historical_delta']) == HISTORICAL, 'Imported-plan exact source boundary changed')
    for path, expected in ext['source_sha256'].items():
        a.safe_name(path)
        require(sha(inbox_source(root, path)) == expected, 'Imported-plan current source differs: ' + path)
    check('Imported plan setup binds every current production test and wiring source', bool(ext['source_sha256']))
    for path, delta in ext['historical_delta'].items():
        current = inbox_source(root, path)
        check('Imported plan setup retains complete predecessor ' + path, bool(inverse(current, delta)))
        deny('Imported plan setup refuses changed inverse input ' + path, lambda b=current, d=delta: inverse(b+b'\n# changed', d))
    for pin in ext['updated_pins']:
        require(sha(inbox_source(root, pin['path'])) == pin['current_sha256'], 'Serialized imported-plan source pin is stale')
    prior = previous_contract(contract)
    check('Imported plan setup preserves exact179 commands and complete460 contract', len(prior['native_commands']) == 179)
    for label, commands in [('missing', contract['native_commands'][:-1]), ('duplicate', contract['native_commands']+[COMMANDS[0]]), ('reordered', contract['native_commands'][1:]+contract['native_commands'][:1])]:
        wrong = copy.deepcopy(contract);wrong['native_commands'] = commands
        deny('Imported plan setup rejects '+label+' recipe', lambda c=wrong: previous_contract(c))
    wrong = copy.deepcopy(contract);wrong['native_commands'][0] += ' altered'
    deny('Imported plan setup cannot alter an original command', lambda: previous_contract(wrong))
    workflow = inbox_source(root, '.github/workflows/application-tests.yml').decode()
    native = workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    check('Imported plan full workflow executes the exact181-command contract', [line.strip() for line in native.splitlines()] == contract['native_commands'])
    focused = inbox_source(root, '.github/workflows/catalog-storefront.yml').decode()
    focused_commands = [command.replace('"$PHP_BINARY"','php').replace(' -d extension=pdo -d extension=tokenizer ', ' ') for command in COMMANDS]
    check('Imported plan focused four-lane workflow adds each command exactly once', all(focused.count(command)==1 for command in focused_commands) and '\n'.join('              '+c for c in focused_commands) in focused and 'codex/twentyi-imported-plan-setup' in focused)
    check('Imported plan minimal view test loads distro PDO and tokenizer but uses Docker builtins',
          '-n -d extension=pdo -d extension=tokenizer tests/hosting_catalog_plan_setup_views.php' in workflow
          and 'php -n tests/hosting_catalog_plan_setup_views.php' in focused
          and '-d extension=tokenizer' not in focused and '-d extension=pdo' not in focused)
    prior_workflow = historical_bytes(root, '.github/workflows/application-tests.yml').decode()
    reports = a.load(root/'tools/acceptance-reports.json') if reports is None else reports
    prior_reports = previous_reports(reports, ext)
    check('Imported plan setup adds five required reports without dropping predecessors', len(prior_reports['browser'])==195 and len(prior_reports['final'])==246)
    for label in ['missing', 'duplicate', 'changed predecessor']:
        wrong = copy.deepcopy(reports)
        if label == 'missing':wrong['browser'].remove(REPORTS[0])
        elif label == 'duplicate':wrong['final'].append(REPORTS[0])
        else:wrong['final'][0] += '.changed'
        deny('Imported plan report union refuses '+label, lambda r=wrong: previous_reports(r,ext))
    sample = tmp/'imported-plan-reports'
    for name in REPORTS:
        named(sample/'qa'/name)
        check('Imported plan report owns native-browser lane '+name, a.evidence_allowed('qa/'+name,'native-browser') and all(not a.evidence_allowed('qa/'+name,lane) for lane in ('build','recovery','apache')))
        named(sample/'qa'/name,False)
        check('Imported plan report failure remains failure '+name, not a.reports(sample)[name]['passed'])
        (sample/'qa'/name).unlink()
        check('Imported plan missing report remains missing '+name, name not in a.reports(sample))
    check('Imported plan setup leaves transfer helper and original browser unchanged', all(sha(inbox_source(root, p))==h for p,h in ext['unchanged_source_sha256'].items()))
    deny('Imported plan private key remains refused', lambda:a.evidence_allowed('test-runtime/hosting-catalog-plan-setup-private/provider-master.key','native-browser'))
    browser_checks(a, root, tmp, check, deny, ext)
    return prior, prior_workflow, prior_reports
