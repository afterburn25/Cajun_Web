"""Portable source/output guards. No database, HTTP, provider or application execution."""
from pathlib import Path
import argparse
import ast
from copy import deepcopy
import hashlib
import json
import tempfile

OLD_CASE_ASSERTIONS = '2985e4f69163a68b7a450078adec9842d60764039e6206889903c2fdface4460'
OLD_DRIVER_ASSERTIONS = '3ecd3a139828017932f3561c2f6f3ee7145a06c608586c498610b5f5de6bae67'


def assertion_hash(source, function):
    tree = ast.parse(source)
    calls = [n for n in ast.walk(tree) if isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id == function]
    calls.sort(key=lambda n: (n.lineno, n.col_offset))
    return hashlib.sha256(json.dumps([ast.dump(n, include_attributes=False) for n in calls], separators=(',', ':')).encode()).hexdigest()


def main(root, report):
    checks = []
    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        assert condition, name
    def deny(name, call):
        try: call()
        except AssertionError: check(name, True)
        else: check(name, False)
    tests = root / 'tests'
    cases = (tests / 'release_126_upgrade_cases.py').read_text(encoding='utf-8')
    driver = (tests / 'release_126_upgrade_acceptance.py').read_text(encoding='utf-8')
    records = (tests / 'release_126_hosting_records.py').read_text(encoding='utf-8')
    fixture = (tests / 'release_126_hosting_fixture.php').read_text(encoding='utf-8')
    provider = (tests / 'release_126_provider_fixture.php').read_text(encoding='utf-8')
    normalized = driver.replace("prefix + ' final snapshot is from updated' + candidate.removesuffix('.0')", "prefix + ' final snapshot is from updated1.26'").replace("obj['final']['version'] == candidate", "obj['final']['version'] == '1.26.0'")
    check('Every original upgrade case predicate remains unchanged', assertion_hash(cases, 'ok') == OLD_CASE_ASSERTIONS)
    check('Original Apache predicates change only explicit candidate-version operands', assertion_hash(normalized, 'check') == OLD_DRIVER_ASSERTIONS)
    check('Immutable published1.25 source and archive remain bound', "BASELINE_SOURCE = 'b79c67110829126916e3a65e616191542448bccc'" in cases
          and "BASELINE_SHA = 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39'" in cases)
    check('Candidate version is explicit while original provider actions keep their three-argument contract',
          'arguments.append(candidate_version)' in cases and provider.count('if(count($argv)!==3)exit(2);') == 2
          and "CWP_VERSION!==$argv[3]" in provider and "trim(file_get_contents(__DIR__.'/../VERSION'))!==$argv[3]" in provider)
    version_tree = ast.parse(cases)
    version_writes = [n for n in ast.walk(version_tree) if isinstance(n, ast.Name) and isinstance(n.ctx, ast.Store) and n.id == 'candidate_version']
    check('Candidate version cannot be overwritten by the original installer URL target', len(version_writes) == 1
          and 'candidate_version = target_version(repo)' in cases and 'target, data = fields(' in cases
          and "updater = base + '/cwp-update-' + candidate_version + '/owner-update.php'" in cases
          and 'php, candidate_version, owner' in cases)
    check('Setup-fee refusal requires its exact alert and no enabled publication form',
          "refusal = 'Choose a priced monthly or annual hosting draft without a separate setup fee.'" in records
          and "rejected_page.select('[role=\"alert\"]')" in records
          and "and not rejected_page.select('form [name=\"action\"][value=\"hosting_catalog_publish\"]:not([disabled])')" in records)
    check('No-schema hook follows actual first update and original retention',
          cases.index('    before_setup(') > cases.index("ok('First update retains every original table row column and private file'"))
    check('Hosting continuation follows the sealed historical final snapshot', cases.index('    continue_after_retention(') > cases.index("'-upgrade-retention.json'"))
    check('Original sixteen-member report remains separate', "len(evidence) == 16" in driver
          and "'checks': checks, 'evidence': evidence" in driver and 'hosting_evidence=hosting' in driver)
    check('Hosting report is written on failure and required on success', 'hosting_passed = finish(' in driver
          and "assert hosting_passed, 'All four supplemental hosting upgrade scopes are required'" in driver)
    check('No pre-existing owned output is overwritten', "Never overwrite earlier hosting upgrade evidence" in driver
          and 'Never overwrite hosting evidence' in records)
    tree = ast.parse(cases)
    old_updates = [n for n in ast.walk(tree) if isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id == 'update']
    additions = [n for n in ast.walk(ast.parse(records)) if isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id in ('update', 'refuse_recovery')]
    check('Six historical and four supplemental updater attempts preserve the real rate limit', len(old_updates) == 6 and len(additions) == 4)
    check('Fixture never resets authentication or rate state', all(s not in fixture for s in ("query('DELETE", "query('UPDATE", "query('INSERT", 'db()->exec(')) and 'rate_limits' not in records)
    check('Read helper admits only fixed installed prefixes and modes', all(p in fixture for p in ('r126_ron_', 'r126_son_', 'r126_roff_', 'r126_soff_'))
          and "['state','plan-original','plan-positive']" in fixture and "config()['db']['name']!=='cwp_test'" in fixture)
    check('Read helper checks installed candidate code before boot', fixture.index("hash_equals(hash_file('sha256'") < fixture.index("require $root.'/includes/bootstrap.php'"))
    check('Real wrappers create plans mappings and no hosting history directly', all(s in fixture for s in ('service_plans_create(', 'service_plans_change(', 'provider_plan_catalog_prepare(', 'provider_plan_catalog_save('))
          and 'hosting_catalog_publish(' not in fixture and 'hosting_catalog_convert(' not in fixture)
    check('History and subscription reads hold the owning transaction', '$result=recurring_billing_locked(' in fixture
          and fixture.index('$result=recurring_billing_locked(') < fixture.index('$ready=hosting_catalog_ready();'))
    check('Both original setup-fee refusal and actual new draft edit remain', 'without a separate setup fee' in records
          and "'catalog_save'" in records and "'setup': '0.00'" in records and "'catalog_import_image'" in records)
    check('Publication request conversion consent and withdrawal use actual forms', all("'" + s + "'" in records for s in
          ('hosting_catalog_preview', 'hosting_catalog_publish', 'hosting_catalog_request', 'hosting_catalog_convert', 'service_agreements_consent', 'hosting_catalog_withdraw')))
    check('No synthetic transport or billing is added to the upgrade flow', all(s not in records + fixture for s in
          ('HttpTransport', 'LD_PRELOAD', 'FAKETIME', 'recurring_billing_issue', 'hosting_fulfillment_execute')))
    check('Recovery refusals compare exact database and installed journal backup state', 'before == after and files == after_files' in records)
    check('Original snapshot image and import provenance remain checked', "hashlib.sha256(old_retention.read_bytes()).hexdigest() == sealed_old" in records
          and "current_original == [catalog['original']]" in records and 'count_transition(original, final)' in records)
    check('Private helper location is never copied to QA', "['private_path']" not in records and "'private_path'=>$private" in fixture)
    # Execute only exact extracted pure filesystem/output functions, with inert input fixtures.
    module = ast.parse(records); pure = {'names', 'new_evidence', '_tree', '_refusal_files', 'finish'}
    nodes = [n for n in module.body if isinstance(n, ast.FunctionDef) and n.name in pure]
    constants = {n.targets[0].id: ast.literal_eval(n.value) for n in module.body if isinstance(n, ast.Assign) and isinstance(n.targets[0], ast.Name) and n.targets[0].id in ('PREFIXES', 'TABLES', 'REPORT')}
    scope = {'Path': Path, 'hashlib': hashlib, 'json': json, **constants}
    exec(compile(ast.Module(body=nodes, type_ignores=[]), '<exact-pure-output-functions>', 'exec'), scope)
    owned = [n for prefix in constants['PREFIXES'] for n in [scope['names'](prefix)['retention'], *scope['names'](prefix)['images']]]
    check('Exact source-owned output union has four diagnostics and eight images', len(owned) == len(set(owned)) == 12
          and sum(n.endswith('.png') for n in owned) == 8 and all('/' not in n and '\\' not in n for n in owned))
    deny('Unknown fixture prefix cannot allocate output names', lambda: scope['names']('../foreign'))
    with tempfile.TemporaryDirectory(prefix='hosting-upgrade-guards-') as temporary:
        base = Path(temporary); installed = base / 'installed'; private = base / 'private'; qa = base / 'qa'
        installed.mkdir(); private.mkdir(); qa.mkdir(); journal = private / 'structure-1.26.0'; journal.mkdir()
        (installed / '.htaccess').write_bytes(b'original policy'); (journal / 'journal.json').write_bytes(b'original journal')
        (journal / 'backup').mkdir(); (journal / 'backup/original.php').write_bytes(b'original PHP')
        initial = scope['_refusal_files'](installed, private, '1.26.0')
        for label, path in [('policy', installed / '.htaccess'), ('journal', journal / 'journal.json'), ('backup', journal / 'backup/original.php')]:
            old = path.read_bytes(); path.write_bytes(old + b'changed')
            check('Refusal digest detects ' + label + ' mutation', scope['_refusal_files'](installed, private, '1.26.0') != initial)
            path.write_bytes(old)
        (journal / 'new-empty-directory').mkdir()
        check('Refusal digest detects directory changes', scope['_refusal_files'](installed, private, '1.26.0') != initial)
        evidence = scope['new_evidence'](); evidence['checks'] = [{'name': 'Synthetic output predicate', 'passed': True}]
        evidence['scopes'] = [{'prefix': p, 'passed': True} for p in constants['PREFIXES']]
        for name in owned:
            body = b'\x89PNG\r\n\x1a\nsynthetic-output-guard' if name.endswith('.png') else b'{}\n'
            (qa / name).write_bytes(body); evidence['evidence'][name] = {'bytes': len(body), 'sha256': hashlib.sha256(body).hexdigest()}
        finish = lambda value, failure=None: scope['finish'](value, qa, failure, '1.26.0', {}, {}, 's' * 40, 'd' * 64)
        check('Exact complete synthetic output shape is admitted by the producer', finish(evidence))
        for label, mutate in [
            ('missing scope', lambda d: d['scopes'].pop()),
            ('wrong scope order', lambda d: d['scopes'].reverse()),
            ('nonboolean scope', lambda d: d['scopes'][0].update(passed=1)),
            ('missing output descriptor', lambda d: d['evidence'].pop(owned[0])),
            ('foreign output descriptor', lambda d: d['evidence'].update({'foreign.json': {'bytes': 0, 'sha256': '0' * 64}})),
            ('wrong output digest', lambda d: d['evidence'][owned[0]].update(sha256='0' * 64)),
            ('failed predicate', lambda d: d['checks'][0].update(passed=False)),
            ('nonboolean predicate', lambda d: d['checks'][0].update(passed=1)),
            ('missing predicates', lambda d: d['checks'].clear()),
            ('duplicate predicate', lambda d: d['checks'].append(d['checks'][0].copy())),
        ]:
            changed = deepcopy(evidence); mutate(changed)
            check('Producer refuses ' + label, finish(changed) is False)
        check('Producer preserves an original failure despite complete output files', finish(evidence, {'type': 'SyntheticFailure'}) is False)
        image = next(n for n in owned if n.endswith('.png')); saved = (qa / image).read_bytes(); (qa / image).unlink()
        check('Producer refuses missing actual capture', finish(evidence) is False)
        (qa / image).write_bytes(b'not a PNG'); changed = deepcopy(evidence)
        changed['evidence'][image] = {'bytes': 9, 'sha256': hashlib.sha256(b'not a PNG').hexdigest()}
        check('Producer refuses a digest-matching non-PNG capture', finish(changed) is False)
        (qa / image).write_bytes(saved)
    report.parent.mkdir(parents=True, exist_ok=True)
    report.write_text(json.dumps({'passed': True, 'scope': 'Portable source invariants and synthetic output/filesystem guards only; no SQL, HTTP, image review or upgrade acceptance', 'checks': checks}, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'hosting upgrade portable guards')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args(); main(args.root, args.report)
