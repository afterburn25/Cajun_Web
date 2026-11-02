from pathlib import Path
import datetime, hashlib, json, os, shutil, subprocess, sys, time

root = Path('/workspace/scratch/3c40afcc1eee/cajun-finance')
runtime = Path('/workspace/scratch/3c40afcc1eee/finance-native-runtime')
stage = sys.argv[1]
destination = runtime / 'evidence' / stage
destination.mkdir(parents=True, exist_ok=False)
paths = ['tests/recurring_billing_native.php', 'tests/license_financing_native_cases.php',
         'site/app/billing/finance_subscription.php', 'site/app/billing/finance_recovery.php',
         'site/app/billing/finance_authorize_net.php', 'site/app/billing/payments.php',
         'site/app/licenses/financing.php', 'site/app/billing/ledger.php',
         'site/database/migrations/license_finance_gateway_migrate.php',
         'site/database/migrations/license_finance_recovery_migrate.php',
         'site/database/schema/license_finance_recovery_schema.php']
sha = lambda b: hashlib.sha256(b).hexdigest()
source = {path: sha((root/path).read_bytes()) for path in paths}
namespace = 'rbill_' + sha(stage.encode())[:7]
environment = dict(os.environ, CWP_TEST_DATABASE='cwp_test', CWP_TEST_HOST='127.0.0.1',
                   CWP_TEST_PORT='33329', CWP_TEST_USER='cwp_test',
                   CWP_TEST_PASSWORD='local-ci-only', CWP_RECURRING_TEST_PREFIX=namespace)
command = ['php', '-d', 'disable_functions=curl_exec', 'tests/recurring_billing_native.php']
started = datetime.datetime.now(datetime.timezone.utc).isoformat()
begin = time.monotonic()
with (destination/'stdout.log').open('wb') as out, (destination/'stderr.log').open('wb') as err:
    result = subprocess.run(command, cwd=root, env=environment, stdout=out, stderr=err)
report = root/'qa/recurring-billing-native-results.json'
if report.exists():
    shutil.copyfile(report, destination/'native.json')
shutil.copyfile(root/'tests/license_financing_native_cases.php', destination/'fixture.php')
execution = {'stage': stage, 'started_at': started,
             'finished_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
             'elapsed_seconds': round(time.monotonic()-begin, 3), 'command': command,
             'exit_code': result.returncode, 'base_head': subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip(),
             'base_site_tree': subprocess.check_output(['git','rev-parse','HEAD:site'],cwd=root,text=True).strip(),
             'shipping_diff': subprocess.check_output(['git','diff','--name-only','HEAD','--','site','foundation'],cwd=root,text=True).splitlines(),
             'source_sha256': source, 'php': subprocess.check_output(['php','-r','echo PHP_VERSION;'],text=True),
             'database': 'MariaDB 10.11.14; disposable cwp_test on loopback port 33329',
             'namespace': namespace, 'network_scope': 'curl_exec disabled in this local supplement; no gateway transport is required by this storage fixture.',
             'files': {path.name: sha(path.read_bytes()) for path in destination.iterdir() if path.is_file()}}
(destination/'execution.json').write_text(json.dumps(execution,indent=2)+'\n')
payload = json.loads((destination/'native.json').read_text()) if (destination/'native.json').exists() else {}
print(json.dumps({'stage':stage,'exit_code':result.returncode,'checks':len(payload.get('checks',[])),
                  'passed':payload.get('passed'),'elapsed_seconds':execution['elapsed_seconds'],
                  'failures':[item for item in payload.get('checks',[]) if not item.get('passed')]}))
sys.exit(result.returncode)
