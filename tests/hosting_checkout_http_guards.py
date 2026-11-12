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
    workflow = (runner.ROOT/'.github/workflows/consolidation-after-backups.yml').read_text(encoding='utf-8')
    apt_mirrors = r'''          sudo python3 - <<'CWP_UBUNTU_MIRRORS'
          from pathlib import Path

          source = Path('/etc/apt/apt-mirrors.txt')
          if source.is_symlink() or not source.is_file() or source.stat().st_size > 4096:
              raise SystemExit('Expected regular Ubuntu mirror list is unavailable')
          original = source.read_bytes()
          expected = (
              b'http://azure.archive.ubuntu.com/ubuntu/\tpriority:1\n'
              b'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
              b'https://security.ubuntu.com/ubuntu/\tpriority:3\n'
          )
          if original != expected:
              raise SystemExit('Unexpected Ubuntu mirror list')
          selected = (
              b'https://archive.ubuntu.com/ubuntu/\tpriority:1\n'
              b'https://security.ubuntu.com/ubuntu/\tpriority:2\n'
              b'http://azure.archive.ubuntu.com/ubuntu/\tpriority:3\n'
          )
          source.write_bytes(selected)
          if source.read_bytes() != selected:
              raise SystemExit('Ubuntu mirror selection was not retained')
          print('Canonical Ubuntu archives selected before Azure fallback')
          CWP_UBUNTU_MIRRORS
'''
    apt_wrapper = '''          cwp_apt_get() {
            local cwp_apt_attempt
            for cwp_apt_attempt in 1 2 3; do
              if sudo apt-get "$@"; then
                return 0
              fi
              if [ "$cwp_apt_attempt" -eq 3 ]; then
                return 1
              fi
              echo "::warning::apt-get attempt ${cwp_apt_attempt}/3 failed; retrying on a fresh connection" >&2
              sleep 5
            done
          }
'''
    check('Checkout apt calls preserve mirror failover and hard-fail bounded reconnects',
          workflow.count(apt_mirrors) == 1
          and workflow.count(apt_wrapper) == 1
          and workflow.count('cwp_apt_get -o Acquire::Retries=1') == 4
          and 'sudo apt-get -o Acquire::Retries' not in workflow
          and 'Acquire::Retries=5' not in workflow)
    report = {'passed': True, 'checks': [{'name': n, 'passed': True} for n in labels], 'runtime': {'php': '8.3.30', 'database': 'synthetic guard value'}}
    def encoded(value): return json.dumps(value).encode()
    check('Exact 85 native producer labels are retained', len(labels) == 85 and len(set(labels)) == 85)
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
    source = {path: (runner.ROOT/path).read_text(encoding='utf-8') for path in (
        'site/admin/controllers/actions.php', 'site/admin/controllers/hosting_purchases.php',
        'site/admin/controllers/router.php',
        'site/app/hosting/checkout_purchases_views.php', 'site/app/billing/ledger.php',
        'site/database/migrations/hosting_payments_migrate.php',
        'site/database/schema/gateway_receipts_schema.php', 'site/database/schema/hosting_payments_schema.php',
        'tests/hosting_checkout_http_fixture.php')}
    check('Owner payment reconciliation is a shipping CSRF-routed confirmed action',
          "str_starts_with($action,'hosting_payment_')" in source['site/admin/controllers/actions.php']
          and "function hosting_purchases_admin_action" in source['site/admin/controllers/hosting_purchases.php']
          and "posted('confirm',1)!=='1'" in source['site/admin/controllers/hosting_purchases.php']
          and "hosting_payment_reconcile(" in source['site/admin/controllers/hosting_purchases.php']
          and "data-hosting-payment-reconcile" in source['site/app/hosting/checkout_purchases_views.php'])
    check('Failed owner reconciliation returns escaped visible feedback',
          "hosting_purchases_admin_render($error)" in source['site/admin/controllers/router.php']
          and "function hosting_purchases_admin_render(string $error='')" in source['site/admin/controllers/hosting_purchases.php']
          and "role=\"alert\">'.esc($error)" in source['site/admin/controllers/hosting_purchases.php'])
    check('Paid reversal review never presents initial-capture reconciliation',
          "$row['invoice']['status']??null)==='issued'" in source['site/app/hosting/checkout_purchases_views.php'])
    check('Every ledger writer serializes against unresolved hosting captures',
          'function ledger_hosting_attempt_assert_clear' in source['site/app/billing/ledger.php']
          and 'ledger_hosting_attempt_assert_clear((int)$i[\'id\'],$hostingAttempt);' in source['site/app/billing/ledger.php'])
    migration = source['site/database/migrations/hosting_payments_migrate.php']
    check('Hosting payment setup validates checkout readiness under its owning lock and transaction',
          'staff_locked(function()use($password):void {' in migration
          and '$checkoutReady=staff_transaction(static fn():bool=>hosting_checkout_ready());' in migration
          and migration.index('staff_locked(function()use($password):void {')
              < migration.index('$checkoutReady=staff_transaction(static fn():bool=>hosting_checkout_ready());')
              < migration.index("if(!$checkoutReady||setting('gateway_receipts_schema_version')!=='1')"))
    payment_fixture = source['tests/hosting_checkout_http_fixture.php']
    check('Payment continuation reads its publication under the checkout lock',
          payment_fixture.count("hosting_catalog_publication($publicationRow['id'])") == 1
          and "$publication=hosting_checkout_locked(static fn()=>hosting_catalog_publication($publicationRow['id']));"
              in payment_fixture)
    for name in ('gateway_receipts_schema.php', 'hosting_payments_schema.php'):
        schema = source['site/database/schema/' + name]
        check(name + ' requires same-database foreign-key targets',
              'k.REFERENCED_TABLE_SCHEMA' in schema
              and "$relation['REFERENCED_TABLE_SCHEMA']!==config()['db']['name']" in schema)
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
