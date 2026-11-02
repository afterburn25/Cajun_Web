"""Validate the exact six-file account layer above the tested notification integration."""
from pathlib import Path
import hashlib,json,subprocess
from notification_settings_freshness_provenance import source_revision, predecessor_bytes
BASE='3e66cb490bb8c6a93119a73209434535be365f44'
ACCOUNT='7d43444cf74ac30fd851b3b2c439606582667ca3'
PATHS={'site/app/clients/account_security.php','site/portal/views/account.php','site/portal/controllers/router.php','site/portal/controllers/actions.php','site/portal/views/workspace.php','site/install/inventory.generated.php'}
AREAS=['site','foundation','tools/structure','tools/build.py','VERSION','START-HERE.txt']
def validate(root):
    revision=source_revision(root)
    receipt=json.loads((root/'docs/ACCOUNT_NOTIFICATION_COMBINATION.json').read_text())
    if receipt['base']!=BASE or receipt['account_source']!=ACCOUNT or set(receipt['shipping'])!=PATHS:raise AssertionError('Unexpected combined source or scope')
    actual=set(subprocess.check_output(['git','diff','--name-only',BASE,revision,'--',*AREAS],cwd=root,text=True).splitlines())
    if actual!=PATHS:raise AssertionError('Unreviewed combined shipping change: '+repr(actual.symmetric_difference(PATHS)))
    for path,row in receipt['shipping'].items():
        target=root/path
        if target.is_symlink() or hashlib.sha256(predecessor_bytes(root,path,target.read_bytes())).hexdigest()!=row['after']:raise AssertionError('Combined output changed: '+path)
        before=subprocess.run(['git','show',BASE+':'+path],cwd=root,capture_output=True)
        if row['before'] is None:
            if before.returncode==0:raise AssertionError('Unexpected preexisting account addition')
        elif before.returncode or hashlib.sha256(before.stdout).hexdigest()!=row['before']:raise AssertionError('Combined predecessor changed: '+path)
    for path in ['site/app/clients/account_security.php','site/portal/views/account.php']:
        if (root/path).read_bytes()!=subprocess.check_output(['git','show',ACCOUNT+':'+path],cwd=root):raise AssertionError('Previously tested account implementation changed')
    return BASE
if __name__=='__main__':validate(Path(__file__).resolve().parents[1]);print('Account and notification integration preserves both exact predecessor layers.')
