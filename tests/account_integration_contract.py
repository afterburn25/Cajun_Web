"""Verify the bounded account-security layer, retaining earlier ticket/proposal evidence."""
from pathlib import Path
import hashlib,json,subprocess
BASE='a827fca40f5c056d249293b11f07e425f0b71600'
PATHS={'site/app/clients/account_security.php','site/portal/views/account.php','site/portal/controllers/router.php','site/portal/controllers/actions.php','site/portal/views/workspace.php','site/install/inventory.generated.php'}
AREAS=['site','foundation','tools/structure','tools/build.py','VERSION','START-HERE.txt']
def validate(root):
    receipt=json.loads((root/'docs/ACCOUNT_SECURITY_SOURCE.json').read_text())
    if receipt['base']!=BASE or set(receipt['shipping'])!=PATHS:raise AssertionError('Unknown account integration source/scope')
    changed=set(subprocess.check_output(['git','diff','--name-only',BASE,'HEAD','--',*AREAS],cwd=root,text=True).splitlines())
    if changed!=PATHS:raise AssertionError('Unreviewed account shipping difference: '+repr(changed.symmetric_difference(PATHS)))
    for path,row in receipt['shipping'].items():
        p=root/path
        if p.is_symlink() or hashlib.sha256(p.read_bytes()).hexdigest()!=row['after']:raise AssertionError('Account output changed: '+path)
        old=subprocess.run(['git','show',BASE+':'+path],cwd=root,capture_output=True)
        if row['before'] is None:
            if old.returncode==0:raise AssertionError('Unexpected preexisting account path: '+path)
        elif old.returncode or hashlib.sha256(old.stdout).hexdigest()!=row['before']:raise AssertionError('Account predecessor differs: '+path)
    return BASE
if __name__=='__main__':validate(Path(__file__).resolve().parents[1]);print('Exact six-path account integration verified; previous ticket and proposal layers retained.')
