#!/usr/bin/env python3
"""Combine per-group diagnostic results into one report.

Usage: python tests/diagnostic_report.py <dir-with-diagnostic-*-results.json> <out-prefix>
Writes <out-prefix>.json and <out-prefix>.md. Exits non-zero when any unit
failed or was dependency-blocked — a diagnostic run with failures is never
reported as successful.
"""
import json, os, sys
from pathlib import Path

STATUS = ['passed', 'failed', 'blocked', 'skipped', 'absent']
BASELINE_ENVIRONMENT = ('8.3', 'mariadb-10.11')
TARGET_ENVIRONMENTS = {
    'target-billing-php84-mysql': ('billing', '8.4', 'mysql-8.4'),
}


def main():
    args = [a for a in sys.argv[1:] if not a.startswith('--expect=')]
    expected = {g for a in sys.argv[1:] if a.startswith('--expect=') for g in a[9:].split(',') if g}
    if len(args) != 2:
        raise SystemExit('usage: diagnostic_report.py [--expect=g1,g2] <results-dir> <out-prefix>')
    src, out = Path(args[0]), Path(args[1])
    files = sorted(src.glob('diagnostic-*-results.json'))
    produced = {f.name[len('diagnostic-'):-len('-results.json')] for f in files}
    missing = sorted(expected - produced)
    if not files and not missing:
        raise SystemExit('No diagnostic-*-results.json files under ' + str(src))
    units, not_reached, shas, trees, identities = [], [], set(), set(), []
    environments = set()
    for path in files:
        payload = json.loads(path.read_text(encoding='utf-8'))
        if payload.get('kind') != 'diagnostic-results':
            raise SystemExit(str(path) + ' is not a diagnostic results file')
        report_units = []
        for unit in payload['units']:
            unit = dict(unit); unit['result_file'] = path.name; report_units.append(unit)
        not_reached += payload.get('not_reached', [])
        shas.add(payload.get('source_sha') or 'unknown')
        trees.add(payload.get('source_tree') or 'unknown')
        identity = payload.get('identity')
        if not isinstance(identity, dict):
            raise SystemExit(str(path) + ' has no exact diagnostic identity')
        tag = path.name[len('diagnostic-'):-len('-results.json')]
        groups = payload.get('selection', {}).get('groups')
        environment = (str(identity.get('php_version', '')), str(identity.get('database', '')))
        target = TARGET_ENVIRONMENTS.get(tag)
        if target:
            if groups != [target[0]] or environment != target[1:]:
                raise SystemExit(str(path) + ' has the wrong named target group/environment')
        elif environment != BASELINE_ENVIRONMENT or groups != [tag]:
            raise SystemExit(str(path) + ' has an unreviewed baseline group/environment')
        environments.add(environment)
        identities.append(identity)
        for unit in report_units:
            unit['execution_environment'] = {
                'php_version': environment[0], 'database': environment[1]}
        units += report_units
    seen = {}
    for u in units:
        # Prerequisite modules are replayed as 'preparation' units inside
        # dependent groups, so one unit legitimately appears in several files.
        # Keep the worst status so a duplicate can never soften a result, and
        # retain every reporting file as provenance.
        worst = {'skipped': 0, 'passed': 1, 'absent': 2, 'blocked': 3, 'failed': 4}
        if u['key'] in seen:
            kept = seen[u['key']]
            kept['reported_in'].append(u['result_file'])
            kept['other_statuses'].append(u['status'])
            kept['reported_environments'].append(u['execution_environment'])
            if worst.get(kept['status'], 0) >= worst.get(u['status'], 0):
                continue
            u['reported_in'] = kept['reported_in']; u['other_statuses'] = kept['other_statuses']
            u['reported_environments'] = kept['reported_environments']
        else:
            u['reported_in'] = [u['result_file']]; u['other_statuses'] = []
            u['reported_environments'] = [u['execution_environment']]
        seen[u['key']] = u
    units = list(seen.values())
    for g in missing:
        units.append({'key': 'group:' + g, 'kind': 'group', 'group': g, 'status': 'blocked', 'checks': 0,
                      'failed_checks': [], 'error': {'class': 'GroupMissing',
                      'message': 'Group job produced no results (infrastructure failure before the runner completed)', 'site': None},
                      'elapsed_s': 0, 'after_failures': [], 'captures': [],
                      'assignment': 'runner infrastructure', 'result_file': None,
                      'reported_environments': []})
    counts = {s: 0 for s in STATUS}
    for u in units: counts[u['status']] += 1
    failures = [u for u in units if u['status'] in ('failed', 'absent')]
    blocked = [u for u in units if u['status'] == 'blocked']

    bound_fields = ('source_sha', 'source_tree', 'run_id', 'run_attempt', 'ref', 'event')
    for field in bound_fields:
        values = {str(identity.get(field, '')) for identity in identities}
        if len(values) > 1:
            raise SystemExit('Diagnostic group identity mismatch for ' + field + ': ' + repr(sorted(values)))
    expected = {
        'source_sha': os.environ.get('CWP_SOURCE_SHA'), 'source_tree': os.environ.get('CWP_SOURCE_TREE'),
        'run_id': os.environ.get('GITHUB_RUN_ID'), 'run_attempt': os.environ.get('GITHUB_RUN_ATTEMPT'),
        'ref': os.environ.get('GITHUB_REF'), 'event': os.environ.get('GITHUB_EVENT_NAME'),
    }
    for field, value in expected.items():
        if value and identities and str(identities[0].get(field, '')) != str(value):
            raise SystemExit('Diagnostic combined identity differs from expected ' + field)

    common_identity = ({field: identities[0].get(field) for field in bound_fields}
                       if identities else None)
    combined = {'kind': 'diagnostic-combined', 'source_sha': sorted(shas), 'source_tree': sorted(trees),
                'identity': common_identity,
                'execution_environments': [
                    {'php_version': php, 'database': database}
                    for php, database in sorted(environments)],
                'result_files': [f.name for f in files],
                'totals': dict(counts, units=len(units), checks=sum(u['checks'] for u in units),
                               failed_assertions=sum(len(u['failed_checks']) for u in units)),
                'units': units, 'not_reached': sorted(set(not_reached)),
                'scope': 'Diagnostic collection only — strict browser acceptance remains the release gate.'}
    out.parent.mkdir(parents=True, exist_ok=True)
    out.with_suffix('.json').write_text(json.dumps(combined, indent=2) + '\n', encoding='utf-8')

    lines = ['# Combined diagnostic report', '',
             'Diagnostic collection only — this is **not** release acceptance.', '',
             '## Totals', '',
             '| status | units |', '|---|---|'] + \
            ['| ' + s + ' | ' + str(counts[s]) + ' |' for s in STATUS] + \
            ['| total | ' + str(len(units)) + ' |', '',
             'Checks executed: ' + str(combined['totals']['checks']) + '  •  failed assertions: ' + str(combined['totals']['failed_assertions']), '']
    if failures or blocked:
        lines += ['## Failures, absent modules, and blocked units', '',
                  '| unit | group | status | failed assertions | error | repair lane |',
                  '|---|---|---|---|---|---|']
        for u in failures + blocked:
            err = (u['error']['class'] + ': ' + u['error']['message'][:120]) if u['error'] else ''
            lines.append('| ' + u['key'] + ' | ' + u['group'] + ' | ' + u['status'] + ' | ' +
                         '; '.join(u['failed_checks'])[:160] + ' | ' + err + ' | ' + u['assignment'] + ' |')
        lines.append('')
    if not_reached:
        lines += ['## Not reached', '', ', '.join(sorted(set(not_reached))), '']
    lines += ['## Per-unit detail', '',
              '| unit | group | status | checks | captures |', '|---|---|---|---|---|'] + \
             ['| ' + u['key'] + ' | ' + u['group'] + ' | ' + u['status'] + ' | ' + str(u['checks']) + ' | ' + ', '.join(u['captures'][:6]) + ' |' for u in units]
    out.with_suffix('.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
    print(json.dumps({'units': len(units), 'counts': counts, 'not_reached': len(set(not_reached))}))
    return 0 if counts['failed'] == 0 and counts['blocked'] == 0 and counts['absent'] == 0 else 1


if __name__ == '__main__':
    raise SystemExit(main())
