"""Portable contracts for the installed billing continuation; no SQL or browser run.

Execute the actual runner's result/output guards and the case module's pure state
checks. The installed journey remains a separately required application producer.
"""
import argparse
import ast
import calendar
from collections import Counter
import copy
from datetime import date
import hashlib
import json
from pathlib import Path
import re
import tempfile


ROOT = Path(__file__).resolve().parents[1]
BASE = '826eb5dd6807eb8424efc677706d21d07b6748c3'
RUNNER = 'tests/hosting_checkout_http_browser.py'
BRIDGE = 'tests/hosting_checkout_http_fixture.php'
CASES = 'tests/hosting_billing_options_cases.py'
RESULT_CHECK = 'Installed monthly and annual billing options preserve exact invoices and prior history'
CAPTURES = tuple('Hosting-Billing-Options-' + stage + '-' + size + '.png'
                 for stage in ('Owner-Annual', 'Public-Monthly', 'Public-Annual', 'Client-Annual')
                 for size in ('Desktop', 'Mobile'))
PREDECESSORS = {
    'tests/hosting_checkout_http_cases.py': '454d03ace515f3f584d14ee6b171bd964259e81c074e0ce5f86a8554cdf01ff0',
    'tests/catalog_storefront_checkout_cases.py': '71239df5a4ec2afe12058c8a8288c35928fd2fc3d687d626c6ca655cb7270ff8',
    'tests/hosting_catalog_plan_setup_cases.py': '7ac91e818644e98cf5ea800aa3e2db32c99179281e2ff8a5e01a27d02e87c0b9',
    'tests/hosting_purchase_inbox_cases.py': 'ad714d79c1dce6ba21f243c4d81fbe0e2349ba52375cda8a8a1ac75331d57600',
}
OLD_STATE_SHA256 = 'a1e221ac3994efb86f856ebbb04efd42dd1493a3ffa294375a4b936d1deabe1f'
OLD_CHECKS_SHA256 = '474c0c99479d2bb645807c702435b1cdda5ace350e0e57566a855455d529c6c3'
OLD_BRIDGE_SHA256 = 'fc0080f85ee81d07a3f4dfa16d6163b9d73ccec4e0f99cfa49bd2583ea89c3d1'


def require(value, message):
    if not value:
        raise AssertionError(message)


def source(root, path):
    return (root / path).read_bytes().replace(b'\r\n', b'\n')


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def definitions(tree, names, environment):
    nodes = [node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name in names]
    require(len(nodes) == len(names) and {node.name for node in nodes} == set(names),
            'Exact production function definitions required')
    exec(compile(ast.Module(body=nodes, type_ignores=[]), '<actual-source-contract>', 'exec'), environment)
    return environment


def constant(tree, name, environment=None):
    nodes = [node for node in tree.body if isinstance(node, ast.Assign)
             and any(isinstance(target, ast.Name) and target.id == name for target in node.targets)]
    require(len(nodes) == 1, 'Exactly one constant declaration required: ' + name)
    values = {} if environment is None else dict(environment)
    exec(compile(ast.Module(body=nodes, type_ignores=[]), '<actual-source-constant>', 'exec'), values)
    return values[name]


def run(root, work, check, deny):
    runner_raw = source(root, RUNNER)
    runner = ast.parse(runner_raw)
    case_raw = source(root, CASES)
    cases = ast.parse(case_raw)
    bridge = source(root, BRIDGE)
    for path, expected in PREDECESSORS.items():
        check('Original installed callback remains byte-identical: ' + Path(path).name,
              digest(source(root, path)) == expected)
    old_tail = bridge[bridge.rindex(b'\n$result=hosting_checkout_locked(function():array{') + 1:]
    check('Original state operation retains its exact complete response and validation',
          digest(old_tail) == OLD_STATE_SHA256)
    begin = bridge.index(b"if($operation==='billing-state'){")
    end = bridge.rindex(b'\n$result=hosting_checkout_locked(function():array{') + 1
    prior_bridge = bridge[:begin] + bridge[end:]
    added_operation = b"['prepare','state','contact-context','billing-state']"
    require(prior_bridge.count(added_operation) == 1, 'Closed additive fixture operation required')
    prior_bridge = prior_bridge.replace(added_operation, b"['prepare','state','contact-context']", 1)
    check('Removing only the new read branch and operation recovers the whole original fixture',
          digest(prior_bridge) == OLD_BRIDGE_SHA256)
    body = bridge[begin:end].decode()
    check('New read branch keeps original lock and authenticated checkout history entry',
          '$result=hosting_checkout_locked(function():array{' in body
          and 'hosting_checkout_require();' in body and 'hosting_checkout_result_row($row)' in body
          and 'hosting_checkout_review_row($row)' in body and '$publication->historicalProjection()' in body)
    check('New read branch exports bounded projections and explicit optional-table presence',
          "['provider_operations','provider_operation_grants']" in body
          and "['exists'=>false,'rows'=>[]]" in body and "['exists'=>true,'rows'=>$hashes]" in body
          and "throw new RuntimeException('Original provider read history missing')" in body
          and 'strlen($encoded)>262144' in body and '$total>1500' in body)
    old_checks = [ast.dump(node, include_attributes=False) for node in ast.walk(runner)
                  if isinstance(node, ast.Call) and isinstance(node.func, ast.Name)
                  and node.func.id == 'check' and not (node.args and isinstance(node.args[0], ast.Constant)
                                                      and node.args[0].value == RESULT_CHECK)]
    check('All twelve predecessor runner check expressions retain exact order and bodies',
          len(old_checks) == 12 and digest(json.dumps(old_checks, separators=(',', ':')).encode()) == OLD_CHECKS_SHA256)

    checkout_tree = ast.parse(source(root, 'tests/hosting_checkout_http_cases.py'))
    stems = constant(checkout_tree, 'CAPTURE_STEMS')
    old_captures = tuple('Hosting-Checkout-' + stem + '-' + size + '.png'
                         for stem in stems for size in ('Desktop', 'Mobile'))
    capture_values = {'CAPTURES': old_captures}
    for path, alias in [('tests/catalog_storefront_checkout_cases.py', 'STOREFRONT_CAPTURES'),
                        ('tests/hosting_catalog_plan_setup_cases.py', 'PLAN_SETUP_CAPTURES'),
                        ('tests/hosting_purchase_inbox_cases.py', 'INBOX_CAPTURES')]:
        value = constant(ast.parse(source(root, path)), 'CAPTURES')
        capture_values[alias] = value
        old_captures += value
    capture_values['BILLING_OPTIONS_CAPTURES'] = CAPTURES
    check('New callback declares exactly its eight reviewed capture names', constant(cases, 'CAPTURES') == CAPTURES)
    combined = constant(runner, 'ALL_CAPTURES', capture_values)
    check('Runner preserves all twenty-four original captures before eight additions',
          len(old_captures) == 24 and combined == old_captures + CAPTURES and len(set(combined)) == 32)
    paths = constant(runner, 'SOURCE_PATHS')
    check('Runner records exact callback and bridge sources without duplicates',
          paths.count(CASES) == 1 and paths.count(BRIDGE) == 1 and paths.count(RUNNER) == 1
          and len(paths) == len(set(paths)))

    imports = [node for node in runner.body if isinstance(node, ast.ImportFrom)
               and node.module == 'hosting_billing_options_cases']
    require(len(imports) == 1, 'One billing callback import required')
    aliases = {alias.name: alias.asname or alias.name for alias in imports[0].names}
    check('Runner imports actual callback and exact capture declaration',
          set(aliases) == {'run_browser', 'CAPTURES'} and aliases['CAPTURES'] == 'BILLING_OPTIONS_CAPTURES')
    calls = sorted([node for node in ast.walk(runner) if isinstance(node, ast.Call)
                    and isinstance(node.func, ast.Name) and node.func.id in
                    {'run_browser', 'storefront_browser', 'inbox_browser', aliases['run_browser']}], key=lambda node: node.lineno)
    check('Installed billing callback runs once after all three original journeys',
          [node.func.id for node in calls] == ['run_browser', 'storefront_browser', 'inbox_browser', aliases['run_browser']])
    gates = [node for node in ast.walk(runner) if isinstance(node, ast.Call)
             and isinstance(node.func, ast.Name) and node.func.id == 'check' and node.args
             and isinstance(node.args[0], ast.Constant) and node.args[0].value == RESULT_CHECK]
    check('One actual billing completion gate runs after its callback', len(gates) == 1 and gates[0].lineno > calls[-1].lineno)
    check('Runner calls its tested completion guard on the nested billing result',
          len(gates[0].args) == 2 and isinstance(gates[0].args[1], ast.Call)
          and ast.unparse(gates[0].args[1]) == "billing_options_complete(result['billing_options'], qa)")

    qa = work / 'qa'
    qa.mkdir()
    environment = {'BILLING_OPTIONS_CAPTURES': CAPTURES}
    definitions(runner, {'billing_options_complete'}, environment)
    complete = environment['billing_options_complete']
    good = {'passed': True, 'checks': [{'name': 'Synthetic output contract only', 'passed': True}],
            'captures': [{'name': name} for name in CAPTURES]}
    for name in CAPTURES:
        (qa / name).write_bytes(b'portable file-contract witness; not an application image')
    check('Actual result guard accepts strict complete output with all files', complete(good, qa) is True)
    mutations = [
        ('failed result', lambda value: value.update(passed=False)),
        ('truthy integer result', lambda value: value.update(passed=1)),
        ('missing passed value', lambda value: value.pop('passed')),
        ('missing check list', lambda value: value.pop('checks')),
        ('empty check list', lambda value: value.update(checks=[])),
        ('failed named check', lambda value: value['checks'][0].update(passed=False)),
        ('truthy integer named check', lambda value: value['checks'][0].update(passed=1)),
        ('empty check name', lambda value: value['checks'][0].update(name='')),
        ('non-string check name', lambda value: value['checks'][0].update(name=3)),
        ('extra check field', lambda value: value['checks'][0].update(extra=True)),
        ('duplicate named check', lambda value: value['checks'].append(dict(value['checks'][0]))),
        ('missing captures', lambda value: value.pop('captures')),
        ('missing capture', lambda value: value['captures'].pop()),
        ('reordered captures', lambda value: value['captures'].reverse()),
        ('duplicate capture', lambda value: value['captures'].__setitem__(1, value['captures'][0])),
        ('foreign capture name', lambda value: value['captures'][0].update(name='../private.png')),
    ]
    def accepted(value, directory=qa):
        require(complete(value, directory) is True, 'Actual completion guard refused')
    for name, mutate in mutations:
        bad = copy.deepcopy(good)
        mutate(bad)
        deny('Actual result guard refuses ' + name, lambda value=bad: accepted(value))
    for value in (None, [], True):
        deny('Actual result guard refuses non-object ' + type(value).__name__, lambda value=value: accepted(value))
    first = qa / CAPTURES[0]
    original = first.read_bytes()
    first.unlink()
    deny('Actual result guard refuses missing capture file', lambda: accepted(good))
    first.write_bytes(b'')
    deny('Actual result guard refuses empty capture file', lambda: accepted(good))
    first.unlink()
    first.mkdir()
    deny('Actual result guard refuses a directory as capture', lambda: accepted(good))
    first.rmdir()
    first.write_bytes(original)

    # This controlled path object checks the actual guard's symlink decision on
    # platforms that cannot create filesystem symlinks without extra privilege.
    class LinkedCapture:
        def is_symlink(self): return True
        def is_file(self): return True
        def stat(self): return first.stat()
    class LinkedDirectory:
        def __truediv__(self, name): return LinkedCapture() if name == CAPTURES[0] else qa / name
    deny('Actual result guard refuses a capture marked as a symlink', lambda: accepted(good, LinkedDirectory()))

    for name in CAPTURES:
        (qa / name).unlink()
    reached_runtime = []
    def no_runtime(path):
        reached_runtime.append(str(path))
        raise AssertionError('Output refusal must precede runtime creation')
    definitions(runner, {'require', 'output_paths', 'prepare_outputs'}, environment := {
        'ROOT': work, 'WEB': work / 'web', 'SITE': work / 'web/site',
        'SESSIONS': work / 'sessions', 'ALL_CAPTURES': CAPTURES,
        'fresh_directory': no_runtime,
    })
    for name in CAPTURES:
        (qa / name).write_bytes(original)
        try:
            environment['prepare_outputs'](True)
        except RuntimeError as error:
            refused = str(error) == 'Preserve prior checkout HTTP outputs'
        else:
            refused = False
        check('Actual output guard preserves existing billing capture ' + name,
              refused and not reached_runtime)
        check('Existing billing capture bytes stay intact ' + name, (qa / name).read_bytes() == original)
        (qa / name).unlink()

    # Execute the same pure validators used by the installed callback. These
    # synthetic dictionaries exercise its evidence contract, not a SQL fixture.
    values = definitions(cases, {'billing_snapshot', 'retained_rows_preserved', 'billing_period_matches'}, {
        'COUNT_KEYS': constant(cases, 'COUNT_KEYS'), 'Counter': Counter,
        're': re, 'date': date, 'calendar': calendar,
    })
    snapshot, retained, period_matches = (values[name] for name in
        ('billing_snapshot', 'retained_rows_preserved', 'billing_period_matches'))
    publication_id, request_id = 'a' * 32, 'b' * 32
    counts = {name: 0 for name in values['COUNT_KEYS']}
    counts.update(products=1, publications=1, reviews=1, results=1, invoices=1, receipts=1)
    before = {
        'counts': counts, 'variants_ready': False,
        'products': {'1': {'id': 1, 'revision': 1, 'canonical_import_id': 1, 'canonical_product_id': 1,
                           'term': 'month', 'amount_cents': 2700, 'media_revision': 0,
                           'variant_id': None, 'media_id': None}},
        'publications': {publication_id: {'publication_id': publication_id, 'publication_sha256': 'c' * 64,
                                         'amount_cents': 2700, 'cadence': 'monthly'}},
        'reviews': {request_id: {'request_id': request_id}},
        'results': {request_id: {'request_id': request_id, 'publication_id': publication_id,
                                'publication_sha256': 'c' * 64, 'invoice': {'id': 1, 'amount_cents': 2700}}},
        'retained_rows': {'studio_invoices': ['1' * 64, '1' * 64], 'business_audits': ['2' * 64]},
        'provider_io_sha256': 'd' * 64,
    }
    check('Actual state validator accepts a complete pre-variant projection', snapshot(before) is before)
    draft = copy.deepcopy(before)
    draft['variants_ready'] = True
    draft['products']['1']['amount_cents'] = None
    check('Actual state validator retains unpriced drafts without converting null to zero', snapshot(draft) is draft)
    state_mutations = [
        ('missing provider digest', lambda value: value.pop('provider_io_sha256')),
        ('unapproved top-level data', lambda value: value.update(private=True)),
        ('truthy preparation flag', lambda value: value.update(variants_ready=1)),
        ('missing counter', lambda value: value['counts'].pop('invoices')),
        ('unknown counter', lambda value: value['counts'].update(extra=0)),
        ('boolean counter', lambda value: value['counts'].update(invoices=True)),
        ('negative counter', lambda value: value['counts'].update(invoices=-1)),
        ('over-bound counter', lambda value: value['counts'].update(invoices=129)),
        ('invalid provider digest', lambda value: value.update(provider_io_sha256='not-a-digest')),
        ('empty product history', lambda value: value.update(products={})),
        ('empty publication history', lambda value: value.update(publications={})),
        ('empty review history', lambda value: value.update(reviews={})),
        ('empty result history', lambda value: value.update(results={})),
        ('empty retained history', lambda value: value.update(retained_rows={})),
        ('mismatched product identity', lambda value: value['products']['1'].update(id=2)),
        ('boolean product revision', lambda value: value['products']['1'].update(revision=True)),
        ('missing canonical product', lambda value: value['products']['1'].pop('canonical_product_id')),
        ('unsupported product cadence', lambda value: value['products']['1'].update(term='week')),
        ('boolean product amount', lambda value: value['products']['1'].update(amount_cents=True)),
        ('negative product amount', lambda value: value['products']['1'].update(amount_cents=-1)),
        ('boolean image revision', lambda value: value['products']['1'].update(media_revision=False)),
        ('invalid variant identity', lambda value: value['products']['1'].update(variant_id='invalid')),
        ('invalid image identity', lambda value: value['products']['1'].update(media_id='invalid')),
        ('mismatched publication identity', lambda value: value['publications'][publication_id].update(publication_id='e' * 32)),
        ('mismatched review identity', lambda value: value['reviews'][request_id].update(request_id='e' * 32)),
        ('mismatched result identity', lambda value: value['results'][request_id].update(request_id='e' * 32)),
        ('publication count mismatch', lambda value: value['counts'].update(publications=2)),
        ('review count mismatch', lambda value: value['counts'].update(reviews=2)),
        ('result count mismatch', lambda value: value['counts'].update(results=2)),
        ('invalid retained table name', lambda value: value['retained_rows'].update({'../private': []})),
        ('invalid retained digest', lambda value: value['retained_rows'].update(studio_invoices=['invalid'])),
        ('unsorted retained multiset', lambda value: value['retained_rows'].update(studio_invoices=['2' * 64, '1' * 64])),
        ('over-bound retained table', lambda value: value['retained_rows'].update(studio_invoices=['1' * 64] * 129)),
    ]
    for name, mutate in state_mutations:
        bad = copy.deepcopy(before)
        mutate(bad)
        deny('Actual state validator refuses ' + name, lambda value=bad: snapshot(value))

    after = copy.deepcopy(before)
    after['variants_ready'] = True
    after['counts'].update(publications=2, reviews=2, results=2, invoices=2)
    after['publications']['e' * 32] = {'publication_id': 'e' * 32, 'amount_cents': 27000, 'cadence': 'annually'}
    after['reviews']['f' * 32] = {'request_id': 'f' * 32}
    after['results']['f' * 32] = {'request_id': 'f' * 32, 'invoice': {'id': 2, 'amount_cents': 27000}}
    after['retained_rows']['studio_invoices'].append('3' * 64)
    after['retained_rows']['business_audits'].append('4' * 64)
    check('Actual retention allows additional invoices and publications with unchanged originals', retained(before, after) is True)
    retention_mutations = [
        ('lost original table', lambda value: value['retained_rows'].pop('studio_invoices')),
        ('lost repeated original row', lambda value: value['retained_rows']['studio_invoices'].pop(0)),
        ('changed original row', lambda value: value['retained_rows'].update(studio_invoices=['1' * 64, '3' * 64, '5' * 64])),
        ('lost business audit', lambda value: value['retained_rows'].update(business_audits=['4' * 64])),
        ('changed original invoice projection', lambda value: value['results'][request_id]['invoice'].update(amount_cents=27000)),
        ('changed original publication fingerprint', lambda value: value['publications'][publication_id].update(publication_sha256='0' * 64)),
        ('changed provider IO history', lambda value: value.update(provider_io_sha256='0' * 64)),
        ('additional payment receipt', lambda value: value['counts'].update(receipts=2)),
        ('additional payment attempt', lambda value: value['counts'].update(attempts=1)),
    ]
    for name, mutate in retention_mutations:
        bad = copy.deepcopy(after)
        mutate(bad)
        check('Actual retention refuses ' + name, retained(before, bad) is False)

    def billing(cadence, amount, start, end):
        return {'cadence': cadence, 'amount_cents': amount, 'currency': 'USD', 'timezone': 'UTC',
                'alignment': 'day-of-month', 'setup_cents': 0, 'due_after_days': 0,
                'collection': 'invoice-only', 'anchor': start,
                'first_period': {'index': 0, 'start': start, 'end': end, 'due': start, 'next_start': end}}
    calendar_cases = [
        ('monthly non-leap short month', 'monthly', 2700, '2025-01-31', '2025-02-28'),
        ('monthly leap short month', 'monthly', 2700, '2024-01-31', '2024-02-29'),
        ('monthly year boundary', 'monthly', 2700, '2025-12-31', '2026-01-31'),
        ('annual leap-day clamp', 'annually', 27000, '2024-02-29', '2025-02-28'),
        ('annual ordinary date', 'annually', 27000, '2025-10-19', '2026-10-19'),
    ]
    for name, cadence, amount, start, end in calendar_cases:
        check('Actual period assertion accepts ' + name,
              period_matches(billing(cadence, amount, start, end), cadence, amount) is True)
    annual = billing('annually', 27000, '2024-02-29', '2025-02-28')
    calendar_mutations = [
        ('divided monthly equivalent instead of full annual charge', lambda value: value.update(amount_cents=2250)),
        ('monthly cadence on annual selection', lambda value: value.update(cadence='monthly')),
        ('wrong currency', lambda value: value.update(currency='EUR')),
        ('wrong timezone', lambda value: value.update(timezone='America/Chicago')),
        ('wrong alignment', lambda value: value.update(alignment='calendar-month')),
        ('boolean amount', lambda value: value.update(amount_cents=True)),
        ('additional setup charge', lambda value: value.update(setup_cents=100)),
        ('boolean zero setup', lambda value: value.update(setup_cents=False)),
        ('different due offset', lambda value: value.update(due_after_days=1)),
        ('automatic collection', lambda value: value.update(collection='automatic')),
        ('impossible anchor', lambda value: value.update(anchor='2025-02-29')),
        ('missing anchor', lambda value: value.pop('anchor')),
        ('missing period', lambda value: value.pop('first_period')),
        ('boolean period index', lambda value: value['first_period'].update(index=False)),
        ('later period index', lambda value: value['first_period'].update(index=1)),
        ('wrong period start', lambda value: value['first_period'].update(start='2024-03-01')),
        ('wrong period end', lambda value: value['first_period'].update(end='2025-03-01')),
        ('wrong payment due date', lambda value: value['first_period'].update(due='2024-03-01')),
        ('wrong next-period boundary', lambda value: value['first_period'].update(next_start='2025-03-01')),
        ('unapproved period field', lambda value: value['first_period'].update(extra=True)),
    ]
    for name, mutate in calendar_mutations:
        bad = copy.deepcopy(annual)
        mutate(bad)
        check('Actual period assertion refuses ' + name, period_matches(bad, 'annually', 27000) is False)
    check('Actual period assertion refuses an unsupported requested cadence', period_matches(annual, 'weekly', 27000) is False)
    check('Actual period assertion refuses a boolean requested amount', period_matches(annual, 'annually', True) is False)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    if args.report is not None:
        require(not args.report.exists() and not args.report.is_symlink(), 'Preserve prior portable report')
        args.report.parent.mkdir(parents=True, exist_ok=True)
    checks = []
    names = set()
    def check(name, passed):
        require(isinstance(name, str) and name and name not in names, 'Unique named outcome required')
        names.add(name)
        checks.append({'name': name, 'passed': passed is True})
        require(passed is True, name)
    def deny(name, callback):
        try:
            callback()
        except (AssertionError, KeyError, TypeError, ValueError, RuntimeError, AttributeError):
            check(name, True)
        else:
            check(name, False)
    failure = None
    try:
        with tempfile.TemporaryDirectory(prefix='hosting-billing-options-wiring-') as temporary:
            run(ROOT, Path(temporary), check, deny)
    except Exception as error:
        failure = {'class': type(error).__name__}
    report = {'passed': failure is None and bool(checks) and all(row['passed'] is True for row in checks),
              'scope': 'Portable actual-source output and preservation contracts only; no SQL, HTTP, Chromium or application screenshots.',
              'base': BASE, 'checks': checks}
    if failure is not None:
        report['error'] = failure
    raw = json.dumps(report, indent=2) + '\n'
    if args.report is not None:
        with args.report.open('x', encoding='utf-8', newline='\n') as stream:
            stream.write(raw)
    print(raw, end='')
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
