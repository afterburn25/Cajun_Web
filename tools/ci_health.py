"""Read-only CI queue health report for afterburn25/Cajun_Web.

Queries the GitHub Actions API through the `gh` CLI and prints a
Markdown report covering: queued/running run counts, oldest pending run,
per-branch and per-workflow backlog distribution, long-running legs,
recent superseded cancellations, and effective observed concurrency.

Usage: python tools/ci_health.py [--pages N] [--out FILE]
Exit code is always 0 on a completed report; API failures are reported
in-line rather than raised so the report still explains what is missing.
"""
import json
import subprocess
import sys
from collections import Counter
from datetime import datetime, timezone

REPO = 'afterburn25/Cajun_Web'


def gh_api(path):
    out = subprocess.run(['gh', 'api', path], capture_output=True, text=True, timeout=120)
    if out.returncode != 0:
        raise RuntimeError(out.stderr.strip()[:300] or 'gh api failed')
    return json.loads(out.stdout)


def age_minutes(iso):
    return max(0, int((datetime.now(timezone.utc) - datetime.fromisoformat(iso.replace('Z', '+00:00')).replace(tzinfo=timezone.utc)).total_seconds() // 60))


def collect(status, pages):
    runs = []
    for page in range(1, pages + 1):
        batch = gh_api(f'repos/{REPO}/actions/runs?status={status}&per_page=100&page={page}')['workflow_runs']
        runs.extend(batch)
        if len(batch) < 100:
            break
    return runs


def main():
    pages = 12
    out_path = None
    args = sys.argv[1:]
    while args:
        if args[0] == '--pages':
            pages = int(args[1]); args = args[2:]
        elif args[0] == '--out':
            out_path = args[1]; args = args[2:]
        else:
            raise SystemExit('unknown argument ' + args[0])

    lines = ['# CI queue health — ' + datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC'), '']
    errors = []

    def section(fn):
        try:
            fn()
        except Exception as e:
            errors.append(str(e)[:200])
            lines.append('- _unavailable: ' + str(e)[:160] + '_')

    queued = running = completed = []

    def load():
        nonlocal queued, running, completed
        queued = collect('queued', pages)
        running = collect('in_progress', 3)
        completed = collect('completed', 2)
    section(load)

    lines.append('## Totals')
    lines.append(f'- queued runs observed: {len(queued)}' + (' (page cap reached — deeper backlog exists)' if len(queued) == pages * 100 else ''))
    lines.append(f'- in-progress runs: {len(running)}')
    if running:
        lines.append(f'- effective runner occupancy: {len(running)} concurrent run(s) — the observed account-level cap is roughly this ceiling when a backlog exists')
    lines.append('')

    if queued:
        oldest = min(r['created_at'] for r in queued)
        lines.append(f'## Backlog (oldest queued run: {age_minutes(oldest)} minutes old)')
        for branch, n in Counter(r['head_branch'] for r in queued).most_common(10):
            lines.append(f'- {branch}: {n} queued')
        lines.append('')
        lines.append('### Queued by workflow (top 15)')
        for wf, n in Counter(r['name'] for r in queued).most_common(15):
            lines.append(f'- {wf}: {n}')
        lines.append('')

    if running:
        lines.append('## In progress')
        for r in sorted(running, key=lambda r: r['run_started_at'] or r['created_at']):
            started = r.get('run_started_at') or r['created_at']
            lines.append(f"- {r['name']} on {r['head_branch']} — running {age_minutes(started)} min")
        lines.append('')

    if completed:
        slow = [r for r in completed if r.get('run_started_at') and
                (datetime.fromisoformat(r['updated_at'].replace('Z', '+00:00')) - datetime.fromisoformat(r['run_started_at'].replace('Z', '+00:00'))).total_seconds() > 3600]
        lines.append('## Recent completions (last ~200 runs)')
        lines.append(f"- canceled: {sum(1 for r in completed if r['conclusion'] == 'cancelled')}")
        lines.append(f"- failed: {sum(1 for r in completed if r['conclusion'] == 'failure')}")
        lines.append(f"- succeeded: {sum(1 for r in completed if r['conclusion'] == 'success')}")
        if slow:
            lines.append('- >60-minute runs:')
            for r in slow[:15]:
                mins = int((datetime.fromisoformat(r['updated_at'].replace('Z', '+00:00')) - datetime.fromisoformat(r['run_started_at'].replace('Z', '+00:00'))).total_seconds() // 60)
                lines.append(f"  - {r['name']} on {r['head_branch']}: {mins} min ({r['conclusion']})")
        lines.append('')

    lines.append('## Diagnosis hints')
    lines.append('- queued >> running with jobs awaiting `runner_name` for >30 min: backlog ahead of capacity; check the oldest-queued age above.')
    lines.append('- one branch dominating the queue: a high-push-frequency lane; confirm its check workflows carry per-PR/per-branch `cancel-in-progress: true` concurrency.')
    lines.append('- queued with zero in-progress and GitHub status green: account concurrency cap or billing limit — check Settings > Billing > Actions on the account.')
    lines.append('- canceled spikes in recent completions: expected when superseded-run cancellation is working; investigate only if the newest run per branch also canceled.')
    if errors:
        lines.append('')
        lines.append('## Query errors')
        lines.extend('- ' + e for e in errors)

    report = '\n'.join(lines) + '\n'
    if out_path:
        open(out_path, 'w', encoding='utf-8', newline='').write(report)
    print(report)


if __name__ == '__main__':
    main()
