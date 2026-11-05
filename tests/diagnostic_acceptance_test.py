#!/usr/bin/env python3
"""Offline contract checks for the diagnostic runner and registry.

These checks run without a database, server or browser: they validate the
registry mirrors the strict module sequence, unit boundaries record and
contain failures, dependency blocking propagates, and the combined report
never reports a failed or blocked run as successful.
"""
import json, os, sys, tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tests'))

results = []


def ok(name, condition):
    results.append((name, bool(condition)))
    if not condition:
        raise AssertionError(name)


def main():
    import diagnostic_registry as reg
    import diagnostic_acceptance as diag

    # Registry is discovered from the actual strict non-focused AST branch.
    strict = reg.discover_strict_modules()
    registry = [m[1] for m in reg.MODULES]
    ok('Registry lists every strict *_cases module in order', registry == strict)
    ok('Registry covers all 58 current strict modules', len(registry) == 58)
    ok('Late finance/contact modules cannot be omitted', registry[-3:] == [
        'software_commerce_cases', 'license_financing_cases', 'contacts_cases'])

    keys = [m[0] for m in reg.MODULES]
    ok('Module keys are unique', len(set(keys)) == len(keys))
    known = set(keys)
    for m in reg.MODULES:
        ok('Module %s requires resolve to module keys' % m[0], all(r in known for r in m[4]))
        ok('Module %s group exists' % m[0], m[3] in reg.GROUPS)
    for s in reg.STAGES:
        ok('Stage %s group exists' % s[0], s[2] in reg.GROUPS)
        ok('Stage %s requires resolve to stages' % s[0], all(r in dict((x[0], x) for x in reg.STAGES) for r in s[3]))
    for st in reg.MODULE_STAGE_REQUIRES:
        ok('Module stage requirement %s exists' % st, st in {s[0] for s in reg.STAGES})
    ok('Release browser requires the fresh installer stage',
       reg.module_stage_requires('release-browser') == ('installer', 'release', 'browser'))
    ok('Unrelated modules keep the shared release/browser prerequisites',
       reg.module_stage_requires('catalog') == reg.MODULE_STAGE_REQUIRES)
    ok('Every group has a repair assignment', all(g in reg.ASSIGNMENTS for g in reg.GROUPS))
    required_groups = {'website', 'installer', 'portal', 'billing', 'mail', 'stackcp', 'security'}
    ok('Required categories are covered', required_groups <= set(reg.GROUPS))
    expected_counts = {'website': 2, 'installer': 1, 'portal': 3, 'billing': 9, 'mail': 9,
                       'twentyi-inventory': 9, 'twentyi-domains': 9,
                       'twentyi-packages': 10, 'stackcp': 2, 'security': 4}
    ok('Exact current group partition is retained',
       {g: sum(1 for m in reg.MODULES if m[3] == g) for g in reg.GROUPS} == expected_counts)
    ok('Group resolver allowlists all', reg.resolve_groups('all') == sorted(reg.GROUPS))
    for malicious in ('billing;echo-owned', 'billing,$(id)', 'billing,,mail', '../billing', 'all,billing'):
        try:
            reg.resolve_groups(malicious)
        except ValueError:
            refused = True
        else:
            refused = False
        ok('Group resolver refuses ' + malicious, refused)
    ok('Target lane is separately named and configured', reg.lanes_for(['billing'])[-1] == {
        'group': 'billing', 'php_version': '8.4', 'database': 'mysql-8.4',
        'tag': 'target-billing-php84-mysql', 'artifact': 'diag-target-billing-php84-mysql',
        'role': 'target'})

    # Unit boundary: a failing unit is recorded and does not raise out.
    diag.results.clear(); diag.checks.clear(); diag.status_of.clear()
    diag.current_unit = 'setup'
    status = diag.run_unit('t-fail', 'module', 'website', lambda: diag.check('t-fail check', False))
    ok('run_unit returns failed without raising', status == 'failed')
    ok('Failed unit records assertion name', diag.results[-1]['failed_checks'] == ['t-fail check'])
    ok('Failed unit captures error class', diag.results[-1]['error']['class'] == 'AssertionError')
    status = diag.run_unit('t-pass', 'module', 'website', lambda: diag.check('t-pass check', True))
    ok('Later unit still runs after a failure', status == 'passed')
    ok('Passed unit sees prior failure annotation', diag.results[-1]['after_failures'] == ['t-fail'])
    ok('Checks carry the owning unit', all('unit' in c for c in diag.checks))

    def raises():
        raise RuntimeError('boom')
    diag.run_unit('t-error', 'module', 'mail', raises)
    ok('Arbitrary exception becomes a failed unit', diag.results[-1]['error']['class'] == 'RuntimeError')

    # Report: failed/blocked runs are never successful; JSON+MD written.
    with tempfile.TemporaryDirectory() as tmp:
        diag.QA = Path(tmp)
        counts = diag.write_reports('unit-test', [m for m in reg.MODULES[:1]])
        ok('Counts record failures', counts['failed'] == 2 and counts['passed'] == 1)
        payload = json.loads((Path(tmp) / 'diagnostic-unit-test-results.json').read_text())
        ok('JSON report preserves all units', len(payload['units']) == 3)
        ok('JSON report marks diagnostic scope', 'not release acceptance' in payload['scope'])
        ok('Human-readable report written', (Path(tmp) / 'diagnostic-unit-test-report.md').is_file())
    diag.QA = ROOT / 'qa'

    # Combined report: a group job that produced no results is recorded as
    # blocked infrastructure failure, and duplicate units keep the worst status.
    import subprocess
    report_env = os.environ.copy()
    for key in ('CWP_SOURCE_SHA', 'CWP_SOURCE_TREE', 'GITHUB_RUN_ID',
                'GITHUB_RUN_ATTEMPT', 'GITHUB_REF', 'GITHUB_EVENT_NAME'):
        report_env.pop(key, None)
    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        identity = {'source_sha': 'a' * 40, 'source_tree': 'b' * 40, 'run_id': '1',
                    'run_attempt': '1', 'ref': 'refs/heads/test', 'event': 'push',
                    'php_version': '8.3', 'database': 'mariadb-10.11'}
        (tmp / 'diagnostic-website-results.json').write_text(json.dumps({
            'kind': 'diagnostic-results', 'source_sha': 'a' * 40,
            'source_tree': 'b' * 40, 'identity': identity,
            'selection': {'groups': ['website'], 'modules': ['portfolio']},
            'totals': {}, 'not_reached': ['reviews'],
            'units': [{'key': 'portfolio', 'kind': 'module', 'group': 'website', 'status': 'passed',
                       'checks': 3, 'failed_checks': [], 'error': None, 'elapsed_s': 1,
                       'after_failures': [], 'captures': [], 'assignment': 'site-www lane'}]}))
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                               '--expect=website,mail', str(tmp), str(tmp / 'combined')],
                              capture_output=True, text=True, env=report_env)
        ok('Missing expected group fails the combined report', code.returncode == 1)
        merged = json.loads((tmp / 'combined.json').read_text())
        group_units = [u for u in merged['units'] if u['key'] == 'group:mail']
        ok('Missing group recorded as blocked', group_units and group_units[0]['status'] == 'blocked')
        ok('Synthesized blocked group appears in markdown', 'group:mail' in (tmp / 'combined.md').read_text())
        ok('Not-reached list preserved', merged['not_reached'] == ['reviews'])
        ok('Combined markdown written', (tmp / 'combined.md').is_file())
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                               '--expect=website', str(tmp), str(tmp / 'combined2')],
                              capture_output=True, text=True, env=report_env)
        ok('All-groups-present clean run exits zero', code.returncode == 0)

    # Module selection: --modules semantics and dependency expansion.
    ok('Selection covers all groups by default', len({m[3] for m in reg.MODULES}) == len(reg.GROUPS))

    # Prerequisite closure: verified readiness edges make group runs
    # self-sufficient on a fresh ci_ schema.
    ok('catalog closure is staff only', diag.closure({'catalog'}) == {'staff'})
    ok('billing module payments pulls staff', 'staff' in diag.closure({'payments'}))
    ok('Domain module pulls provider-settings chain',
       {'provider-settings', 'payments', 'staff'} <= diag.closure({'domain-search'}))
    ok('StackCP access pulls the whole provider chain',
       {'stackcp-prerequisites', 'provider-settings', 'payments', 'staff'} <= diag.closure({'stackcp-access'}))
    ok('Closure excludes the selected key itself', 'catalog' not in diag.closure({'catalog'}))
    # No requirement cycles (topological check over module edges).
    req = {m[0]: list(m[4]) for m in reg.MODULES}
    state = {}
    def acyclic(k):
        if state.get(k) == 1:
            return False
        if state.get(k) == 2:
            return True
        state[k] = 1
        ok_flag = all(acyclic(d) for d in req.get(k, []))
        state[k] = 2
        return ok_flag
    ok('Module prerequisites are acyclic', all(acyclic(k) for k in req))

    # Preparation placeholder marks cross-group replayed units.
    diag.HOME_KEYS = {'catalog'}
    rec = diag.placeholder(('staff', 'staff_cases', 'run_browser', 'portal', []), 'blocked', 'PrerequisiteBlocked', 'Requires: x')
    ok('Placeholder marks non-home unit as preparation', rec['role'] == 'preparation')
    ok('Placeholder marks home unit as home',
       diag.placeholder(('catalog', 'catalog_cases', 'run_browser', 'billing', []), 'blocked', 'x', 'y')['role'] == 'home')
    diag.HOME_KEYS = set()

    # Combined report keeps the worst status when a prerequisite is replayed
    # in several group reports, and retains provenance.
    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        identity = {'source_sha': 'b' * 40, 'source_tree': 'c' * 40, 'run_id': '2',
                    'run_attempt': '1', 'ref': 'refs/heads/test', 'event': 'push',
                    'php_version': '8.3', 'database': 'mariadb-10.11'}
        base = {'kind': 'diagnostic-results', 'source_sha': 'b' * 40, 'source_tree': 'c' * 40,
                'identity': identity, 'totals': {}, 'not_reached': []}
        unit = {'key': 'staff', 'kind': 'module', 'group': 'portal', 'checks': 5,
                'failed_checks': [], 'error': None, 'elapsed_s': 1,
                'after_failures': [], 'captures': [], 'assignment': 'studio/portal lane'}
        a = dict(base, selection={'groups': ['portal'], 'modules': ['staff']},
                 units=[dict(unit, status='passed', role='home')])
        b = dict(base, selection={'groups': ['mail'], 'modules': ['notifications'], 'preparation': ['staff']},
                 units=[dict(unit, status='failed', role='preparation',
                             error={'class': 'AssertionError', 'message': 'm', 'site': None})])
        (tmp / 'diagnostic-portal-results.json').write_text(json.dumps(a))
        (tmp / 'diagnostic-mail-results.json').write_text(json.dumps(b))
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                                '--expect=portal,mail', str(tmp), str(tmp / 'dup')],
                              capture_output=True, text=True, env=report_env)
        merged = json.loads((tmp / 'dup.json').read_text())
        kept = [u for u in merged['units'] if u['key'] == 'staff']
        ok('Replayed prerequisite keeps worst status', len(kept) == 1 and kept[0]['status'] == 'failed')
        ok('Provenance records both group reports',
           sorted(kept[0]['reported_in']) == ['diagnostic-mail-results.json', 'diagnostic-portal-results.json'])
        ok('Baseline provenance retains its execution environment',
           kept[0]['reported_environments'] == [
               {'php_version': '8.3', 'database': 'mariadb-10.11'},
               {'php_version': '8.3', 'database': 'mariadb-10.11'}])
        ok('Duplicate-status run still exits non-zero', code.returncode == 1)
        b['units'] = [dict(unit, status='skipped', role='preparation')]
        (tmp / 'diagnostic-mail-results.json').write_text(json.dumps(b))
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                                '--expect=portal,mail', str(tmp), str(tmp / 'dup-passed')],
                              capture_output=True, text=True, env=report_env)
        merged = json.loads((tmp / 'dup-passed.json').read_text())
        ok('Passed home result outranks another group skipped placeholder',
           code.returncode == 0 and
           [u['status'] for u in merged['units'] if u['key'] == 'staff'] == ['passed'])

    # The exact named billing target is additional evidence from the same
    # source/run, not a replacement for the baseline billing worker.
    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        common = {'source_sha': 'f' * 40, 'source_tree': '1' * 40, 'run_id': '4',
                  'run_attempt': '1', 'ref': 'refs/heads/test', 'event': 'push'}
        unit = {'key': 'catalog', 'kind': 'module', 'group': 'billing', 'status': 'passed',
                'checks': 2, 'failed_checks': [], 'error': None, 'elapsed_s': 1,
                'after_failures': [], 'captures': [], 'assignment': 'billing lane'}
        for tag, php, database in [('billing', '8.3', 'mariadb-10.11'),
                                   ('target-billing-php84-mysql', '8.4', 'mysql-8.4')]:
            identity = dict(common, php_version=php, database=database)
            (tmp / ('diagnostic-' + tag + '-results.json')).write_text(json.dumps({
                'kind': 'diagnostic-results', 'source_sha': common['source_sha'],
                'source_tree': common['source_tree'], 'identity': identity,
                'selection': {'groups': ['billing'], 'modules': ['catalog']},
                'totals': {}, 'not_reached': [], 'units': [unit]}))
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                               '--expect=billing', str(tmp), str(tmp / 'target')],
                              capture_output=True, text=True, env=report_env)
        merged = json.loads((tmp / 'target.json').read_text())
        ok('Exact named PHP8.4/MySQL billing target combines with its baseline', code.returncode == 0)
        ok('Combined identity keeps common run fields and both execution environments',
           merged['identity'] == common and merged['execution_environments'] == [
               {'php_version': '8.3', 'database': 'mariadb-10.11'},
               {'php_version': '8.4', 'database': 'mysql-8.4'}])
        target_units = [u for u in merged['units'] if u['key'] == 'catalog']
        ok('Baseline and target unit results remain separate',
           len(target_units) == 2 and
           {tuple(sorted(u['execution_environment'].items())) for u in target_units} == {
               (('database', 'mariadb-10.11'), ('php_version', '8.3')),
               (('database', 'mysql-8.4'), ('php_version', '8.4'))})
        ok('Combined totals include checks from both billing environments',
           merged['totals']['checks'] == 4)
        baseline_path = tmp / 'diagnostic-billing-results.json'
        target_path = tmp / 'diagnostic-target-billing-php84-mysql-results.json'
        baseline_original = baseline_path.read_text()
        target_original = target_path.read_text()
        target_path.unlink()
        missing_target = subprocess.run([
            sys.executable, str(ROOT / 'tests/diagnostic_report.py'), '--expect=billing',
            str(tmp), str(tmp / 'missing-target')], capture_output=True, text=True, env=report_env)
        missing_payload = json.loads((tmp / 'missing-target.json').read_text())
        missing_units = [u for u in missing_payload['units']
                         if u['key'] == 'target:target-billing-php84-mysql']
        ok('Missing named target is synthesized as blocked and fails combine',
           missing_target.returncode == 1 and len(missing_units) == 1 and
           missing_units[0]['status'] == 'blocked' and
           missing_units[0]['error']['class'] == 'TargetMissing')
        target_path.write_text(target_original)

        baseline_payload = json.loads(baseline_path.read_text())
        target_payload = json.loads(target_path.read_text())
        for path, payload, error, checks in [
                (baseline_path, baseline_payload, 'BaselineFailure', 3),
                (target_path, target_payload, 'TargetFailure', 4)]:
            payload['units'][0].update(status='failed', checks=checks,
                                       failed_checks=[error],
                                       error={'class': error, 'message': error, 'site': None})
            path.write_text(json.dumps(payload))
        failed_both = subprocess.run([
            sys.executable, str(ROOT / 'tests/diagnostic_report.py'), '--expect=billing',
            str(tmp), str(tmp / 'failed-both')], capture_output=True, text=True, env=report_env)
        failed_payload = json.loads((tmp / 'failed-both.json').read_text())
        failed_units = [u for u in failed_payload['units'] if u['key'] == 'catalog']
        ok('Baseline and target failures retain both errors and check counts',
           failed_both.returncode == 1 and len(failed_units) == 2 and
           {u['error']['class'] for u in failed_units} == {'BaselineFailure', 'TargetFailure'} and
           failed_payload['totals']['checks'] == 7)

        baseline_path.write_text(baseline_original)
        target_path.write_text(target_original)
        changed = json.loads(target_path.read_text())
        changed['identity']['database'] = 'mariadb-10.11'
        target_path.write_text(json.dumps(changed))
        refused = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                                  '--expect=billing', str(tmp), str(tmp / 'refused')],
                                 capture_output=True, text=True, env=report_env)
        ok('Named target refuses a different runtime pair',
           refused.returncode != 0 and 'wrong named target group/environment' in refused.stderr)

    # A selected module that disappears is an explicit failing outcome.
    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        identity = {'source_sha': 'd' * 40, 'source_tree': 'e' * 40, 'run_id': '3',
                    'run_attempt': '1', 'ref': 'refs/heads/test', 'event': 'push',
                    'php_version': '8.3', 'database': 'mariadb-10.11'}
        absent = {'key': 'contacts', 'kind': 'module', 'group': 'portal', 'status': 'absent',
                  'checks': 0, 'failed_checks': [], 'error': {'class': 'ModuleAbsent',
                  'message': 'contacts_cases.py not present', 'site': None}, 'elapsed_s': 0,
                  'after_failures': [], 'captures': [], 'assignment': 'studio/portal lane'}
        (tmp / 'diagnostic-portal-results.json').write_text(json.dumps({
            'kind': 'diagnostic-results', 'source_sha': 'd' * 40, 'source_tree': 'e' * 40,
            'identity': identity, 'selection': {'groups': ['portal'], 'modules': ['contacts']},
            'totals': {}, 'not_reached': [], 'units': [absent]}))
        skipped = dict(absent, status='skipped', error=None, assignment='billing lane')
        (tmp / 'diagnostic-billing-results.json').write_text(json.dumps({
            'kind': 'diagnostic-results', 'source_sha': 'd' * 40, 'source_tree': 'e' * 40,
            'identity': identity, 'selection': {'groups': ['billing'], 'modules': ['payments']},
            'totals': {}, 'not_reached': [], 'units': [skipped]}))
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                               '--expect=portal,billing', str(tmp), str(tmp / 'absent')],
                              capture_output=True, text=True, env=report_env)
        ok('Selected absent module fails combined report', code.returncode == 1)
        combined_absent = json.loads((tmp / 'absent.json').read_text())
        ok('Selected absent module outranks another group skipped placeholder',
           [u['status'] for u in combined_absent['units'] if u['key'] == 'contacts'] == ['absent'])
        ok('Absent module appears in human report', 'contacts' in (tmp / 'absent.md').read_text())

    strict_source = (ROOT / 'tests/browser_acceptance.py').read_text(encoding='utf-8')
    diagnostic_source = (ROOT / 'tests/diagnostic_acceptance.py').read_text(encoding='utf-8')
    for assertion in ('Cross-client message POST denied', 'Cross-client message not saved',
                      'Real GD image upload returns successfully'):
        ok('Diagnostic prelude retains strict assertion: ' + assertion,
           assertion in strict_source and assertion in diagnostic_source)

    workflow = (ROOT / '.github/workflows/diagnostic-tests.yml').read_text(encoding='utf-8')
    push = workflow.split('  workflow_dispatch:\n', 1)[0]
    ok('Every main commit receives exact-source diagnostic evidence',
       "branches: [main, 'codex/diagnostic-acceptance-*']" in push and '\n    paths:' not in push)
    plan = workflow.split('\n  plan:\n', 1)[1].split('\n  diagnose:\n', 1)[0]
    combine = workflow.split('\n  combine:\n', 1)[1]
    ok('Diagnostic plan still runs when build fails', '\n    needs: build\n' not in plan)
    ok('Combined report survives missing group artifacts',
       'continue-on-error: true' in combine and
       '- name: Build combined report\n        if: always()' in combine)
    ok('Combined report downloads both baseline and exact named target artifacts',
       'pattern: diag-*-${{ github.run_id }}-${{ github.run_attempt }}-${{ github.sha }}' in combine and
       'pattern: diag-group-*-' not in combine)
    ok('Historical fixture downloads retain bounded retry evidence',
       'for attempt in 1 2 3; do' in workflow and
       'tee -a qa/legacy-preparation.txt' in workflow and
       'sleep "$((attempt * 10))"' in workflow)
    ok('Workflow never interpolates group input into Python source', "raw = '${{ inputs.groups" not in workflow)
    ok('Workflow retains always-run diagnostic mutation guard',
       'if: always()' in workflow and 'tools/acceptance_artifacts.py guard' in workflow)
    receive = workflow.split('- name: Verify and materialize exact same-attempt inputs', 1)[1].split('- name: Seed the isolated acceptance fixture database', 1)[0]
    ok('Reusable input envelope is verified under its exact build producer runtime',
       "CWP_PHP_VERSION: '8.3'" in receive and
       'CWP_DATABASE: mariadb-10.11' in receive and
       'acceptance_artifacts.py receive --lane native-browser' in receive)

    print(json.dumps({'checks': len(results), 'passed': sum(1 for _, p in results if p)}))
    return 0


if __name__ == '__main__':
    try:
        code = main()
    except AssertionError as exc:
        for name, passed in results:
            print(('PASS ' if passed else 'FAIL ') + name)
        print('FAIL ' + str(exc))
        raise SystemExit(1)
    for name, passed in results:
        print(('PASS ' if passed else 'FAIL ') + name)
    raise SystemExit(code)
