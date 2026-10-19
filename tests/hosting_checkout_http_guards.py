"""Portable report/input/output guards only; no SQL, browser, transport, or application execution."""
import copy
import json
from pathlib import Path
import tempfile

import hosting_checkout_http_browser as runner


def main():
    checks = []
    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        if not condition: raise AssertionError(name)
    def deny(name, fn):
        try: fn()
        except (RuntimeError, ValueError, KeyError, TypeError): check(name, True); return
        check(name, False)
    # Installed source is provided by the normal tests path, not copied report strings.
    producer = (runner.ROOT/'tests/hosting_checkout_native.php').read_text(encoding='utf-8')
    labels = runner.native_labels(producer)
    report = {'passed': True, 'checks': [{'name': n, 'passed': True} for n in labels], 'runtime': {'php': '8.3.30', 'database': 'synthetic guard value'}}
    def encoded(value): return json.dumps(value).encode()
    check('Exact 79 native producer labels are retained', len(labels) == 79 and len(set(labels)) == 79)
    check('Complete synthetic report shape is accepted only as a guard example', runner.validate_native(encoded(report), producer, '8.3') == report)
    for name, mutate in [
        ('False outer pass refuses', lambda x: x.update(passed=False)),
        ('Truthy integer outer pass refuses', lambda x: x.update(passed=1)),
        ('False named outcome refuses', lambda x: x['checks'][0].update(passed=False)),
        ('Truthy named integer refuses', lambda x: x['checks'][0].update(passed=1)),
        ('Missing original assertion refuses', lambda x: x['checks'].pop()),
        ('Duplicate original assertion refuses', lambda x: x['checks'].__setitem__(1, x['checks'][0])),
        ('Reordered original assertion refuses', lambda x: x['checks'].reverse()),
        ('Error-bearing report refuses', lambda x: x.update(error={'class': 'RuntimeException'})),
        ('Different actual PHP family refuses', lambda x: x['runtime'].update(php='8.4.1')),
        ('Missing actual database runtime refuses', lambda x: x['runtime'].pop('database')),
    ]:
        changed = copy.deepcopy(report); mutate(changed); deny(name, lambda: runner.validate_native(encoded(changed), producer, '8.3'))
    deny('Duplicate JSON keys refuse', lambda: runner.validate_native(b'{"passed":false,' + encoded(report)[1:], producer, '8.3'))
    deny('Oversized original report refuses', lambda: runner.validate_native(b' '*(256*1024+1), producer, '8.3'))
    deny('Altered producer label count refuses', lambda: runner.native_labels(producer + "\nhcc_ok('unexpected',true);"))
    check('Twelve exact unique capture names retained', len(runner.CAPTURES) == 12 and len(set(runner.CAPTURES)) == 12)
    old_root, old_web, old_site, old_sessions = runner.ROOT, runner.WEB, runner.SITE, runner.SESSIONS
    try:
        with tempfile.TemporaryDirectory(prefix='cwp-checkout-output-') as temp:
            root = Path(temp).resolve(); runner.ROOT = root
            def paths(tag):
                runner.WEB = root/'test-runtime'/('web-'+tag); runner.SITE = runner.WEB/'hosting-checkout'; runner.SESSIONS = root/'test-runtime'/('sessions-'+tag)
            qa, outputs = runner.output_paths(True)
            check('Full outputs are the exact flat report names', qa == root/'qa' and {p.name for p in outputs.values()} == {'hosting-checkout-http-results.json','hosting-checkout-http-progress.txt','hosting-checkout-http-commands.json','hosting-checkout-http-server.log'})
            focused, focused_outputs = runner.output_paths(False)
            check('Focused outputs retain the separate nested directory', focused == root/'qa/hosting-checkout-http' and {p.name for p in focused_outputs.values()} == {'results.json','progress.txt','commands.json','server.log'})
            qa.mkdir(); (qa/'unrelated-results.json').write_bytes(b'preserve')
            paths('ok'); runner.prepare_outputs(True)
            check('Flat setup preserves unrelated evidence', (qa/'unrelated-results.json').read_bytes() == b'preserve' and runner.SITE.is_dir() and runner.SESSIONS.is_dir())
            for index, target in enumerate([*outputs.values(), *(qa/n for n in runner.CAPTURES)]):
                paths(str(index)); target.write_bytes(b'original')
                deny('Existing owned output refuses '+target.name, lambda: runner.prepare_outputs(True))
                check('Collision preserves original '+target.name, target.read_bytes() == b'original' and not runner.WEB.exists())
                target.unlink()
            paths('existing-runtime'); runner.WEB.mkdir(parents=True)
            deny('Existing web runtime refuses reset', lambda: runner.prepare_outputs(True))
    finally: runner.ROOT, runner.WEB, runner.SITE, runner.SESSIONS = old_root, old_web, old_site, old_sessions
    print(json.dumps({'passed': True, 'scope': 'Portable guards, synthetic report mutations and temporary output paths only', 'checks': checks, 'count': len(checks), 'native_or_http_executed': False}, indent=2))


if __name__ == '__main__': main()
