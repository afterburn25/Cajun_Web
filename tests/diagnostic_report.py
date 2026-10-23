#!/usr/bin/env python3
"""Combine per-group diagnostic results into one report.

Usage: python tests/diagnostic_report.py <dir-with-diagnostic-*-results.json> <out-prefix>
Writes <out-prefix>.json and <out-prefix>.md. Exits non-zero when any unit
failed or was dependency-blocked — a diagnostic run with failures is never
reported as successful.
"""
import json, sys
from pathlib import Path

STATUS = ['passed', 'failed', 'blocked', 'skipped', 'absent']


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
    units, not_reached, shas = [], [], set()
    for path in files:
        payload = json.loads(path.read_text(encoding='utf-8'))
        if payload.get('kind') != 'diagnostic-results':
            raise SystemExit(str(path) + ' is not a diagnostic results file')
        for unit in payload['units']:
            unit = dict(unit); unit['result_file'] = path.name; units.append(unit)
        not_reached += payload.get('not_reached', [])
        shas.add(payload.get('source_sha') or 'unknown')
    seen = {}
    for u in units:
        # A unit may appear in more than one group file only if groups overlap;
        # keep the worst status so duplicates cannot soften the result.
        worst = {'passed': 0, 'skipped': 1, 'absent': 1, 'blocked': 2, 'failed': 3}
        if u['key'] in seen and worst.get(seen[u['key']]['status'], 0) >= worst.get(u['status'], 0):
            continue
        seen[u['key']] = u
    units = list(seen.values())
    counts = {s: 0 for s in STATUS}
    for u in units: counts[u['status']] += 1
    failures = [u for u in units if u['status'] == 'failed']
    blocked = [u for u in units if u['status'] == 'blocked']
    for g in missing:
        units.append({'key': 'group:' + g, 'kind': 'group', 'group': g, 'status': 'blocked', 'checks': 0,
                      'failed_checks': [], 'error': {'class': 'GroupMissing',
                      'message': 'Group job produced no results (infrastructure failure before the runner completed)', 'site': None},
                      'elapsed_s': 0, 'after_failures': [], 'captures': [],
                      'assignment': 'runner infrastructure', 'result_file': None})
        counts['blocked'] += 1
    combined = {'kind': 'diagnostic-combined', 'source_sha': sorted(shas), 'result_files': [f.name for f in files],
                'totals': dict(counts, units=len(units), checks=sum(u['checks'] for u in units),
                               failed_assertions=sum(len(u['failed_checks']) for u in units)),
                'units': units, 'not_reached': sorted(set(not_reached)),
                'scope': 'Diagnostic collection only — strict browser acceptance remains the release gate.'}
    out.with_suffix('.json').write_text(json.dumps(combined, indent=2) + '\n', encoding='utf-8')

    lines = ['# Combined diagnostic report', '',
             'Diagnostic collection only — this is **not** release acceptance.', '',
             '## Totals', '',
             '| status | units |', '|---|---|'] + \
            ['| ' + s + ' | ' + str(counts[s]) + ' |' for s in STATUS] + \
            ['| total | ' + str(len(units)) + ' |', '',
             'Checks executed: ' + str(combined['totals']['checks']) + '  •  failed assertions: ' + str(combined['totals']['failed_assertions']), '']
    if failures or blocked:
        lines += ['## Failures and blocked units', '',
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
    return 0 if counts['failed'] == 0 and counts['blocked'] == 0 else 1


if __name__ == '__main__':
    raise SystemExit(main())
