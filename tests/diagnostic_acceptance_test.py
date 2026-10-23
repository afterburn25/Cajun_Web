#!/usr/bin/env python3
"""Offline contract checks for the diagnostic runner and registry.

These checks run without a database, server or browser: they validate the
registry mirrors the strict module sequence, unit boundaries record and
contain failures, dependency blocking propagates, and the combined report
never reports a failed or blocked run as successful.
"""
import json, re, sys, tempfile
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

    # Registry mirrors the pinned strict module order exactly.
    src = (ROOT / 'tests/browser_acceptance.py').read_text(encoding='utf-8')
    strict = ['release_cases'] + [s + '_cases' for s in re.findall(r'from (\w+)_cases import run_browser', src)]
    registry = [m[1] for m in reg.MODULES]
    ok('Registry lists every strict *_cases module in order', registry == strict)

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
    ok('Every group has a repair assignment', all(g in reg.ASSIGNMENTS for g in reg.GROUPS))
    required_groups = {'website', 'installer', 'portal', 'billing', 'mail', 'stackcp', 'security'}
    ok('Required categories are covered', required_groups <= set(reg.GROUPS))

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
    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        (tmp / 'diagnostic-website-results.json').write_text(json.dumps({
            'kind': 'diagnostic-results', 'source_sha': 'a' * 40,
            'selection': {'groups': ['website'], 'modules': ['portfolio']},
            'totals': {}, 'not_reached': ['reviews'],
            'units': [{'key': 'portfolio', 'kind': 'module', 'group': 'website', 'status': 'passed',
                       'checks': 3, 'failed_checks': [], 'error': None, 'elapsed_s': 1,
                       'after_failures': [], 'captures': [], 'assignment': 'site-www lane'}]}))
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                               '--expect=website,mail', str(tmp), str(tmp / 'combined')],
                              capture_output=True, text=True)
        ok('Missing expected group fails the combined report', code.returncode == 1)
        merged = json.loads((tmp / 'combined.json').read_text())
        group_units = [u for u in merged['units'] if u['key'] == 'group:mail']
        ok('Missing group recorded as blocked', group_units and group_units[0]['status'] == 'blocked')
        ok('Not-reached list preserved', merged['not_reached'] == ['reviews'])
        ok('Combined markdown written', (tmp / 'combined.md').is_file())
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                               '--expect=website', str(tmp), str(tmp / 'combined2')],
                              capture_output=True, text=True)
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
        base = {'kind': 'diagnostic-results', 'source_sha': 'b' * 40, 'totals': {}, 'not_reached': []}
        unit = {'key': 'staff', 'kind': 'module', 'group': 'portal', 'checks': 5,
                'failed_checks': [], 'error': None, 'elapsed_s': 1,
                'after_failures': [], 'captures': [], 'assignment': 'studio/portal lane'}
        a = dict(base, selection={'groups': ['portal'], 'modules': ['staff']},
                 units=[dict(unit, status='passed', role='home')])
        b = dict(base, selection={'groups': ['billing'], 'modules': ['catalog'], 'preparation': ['staff']},
                 units=[dict(unit, status='failed', role='preparation',
                             error={'class': 'AssertionError', 'message': 'm', 'site': None})])
        (tmp / 'diagnostic-portal-results.json').write_text(json.dumps(a))
        (tmp / 'diagnostic-billing-results.json').write_text(json.dumps(b))
        code = subprocess.run([sys.executable, str(ROOT / 'tests/diagnostic_report.py'),
                               '--expect=portal,billing', str(tmp), str(tmp / 'dup')],
                              capture_output=True, text=True)
        merged = json.loads((tmp / 'dup.json').read_text())
        kept = [u for u in merged['units'] if u['key'] == 'staff']
        ok('Replayed prerequisite keeps worst status', len(kept) == 1 and kept[0]['status'] == 'failed')
        ok('Provenance records both group reports',
           sorted(kept[0]['reported_in']) == ['diagnostic-billing-results.json', 'diagnostic-portal-results.json'])
        ok('Duplicate-status run still exits non-zero', code.returncode == 1)

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
