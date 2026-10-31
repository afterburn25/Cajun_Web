"""Retain all reviewed shipping paths except the exact focused-view correction."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
BASE='771556e701103a3db9f757da05da9b2357a4247b'
AREAS=['site','foundation','tools/structure','tools/build.py','VERSION','START-HERE.txt']
changed=set(subprocess.check_output(['git','diff','--name-only',BASE,'HEAD','--',*AREAS],cwd=ROOT,text=True).splitlines())
if changed!={'site/admin/views/providers.php','site/install/inventory.generated.php'}:raise SystemExit('Unreviewed shipping changes: '+repr(changed))
path='site/admin/views/providers.php';row=json.loads((ROOT/'docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json').read_text())['files'][path]
old=subprocess.check_output(['git','show',BASE+':'+path],cwd=ROOT)
if hashlib.sha256(old).hexdigest()!=row['before_sha256'] or hashlib.sha256((ROOT/path).read_bytes()).hexdigest()!=row['after_sha256']:raise SystemExit('Cloud focus source identity changed')
print('Every other combined shipping file retains its exact reviewed bytes.')
