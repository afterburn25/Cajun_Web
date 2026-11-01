"""Exact shipping-file reconciliation for proposals layered on the verified combined source."""
from pathlib import Path
import hashlib,json,subprocess
BASE='3930ad96504006c5adcd511f9061da2fc8dec180'
AREAS=['site','foundation','tools/structure','tools/build.py','VERSION','START-HERE.txt']
def validate(root):
    receipt=json.loads((root/'docs/PROPOSALS_COMBINED_SOURCE.json').read_text())
    if receipt['base']!=BASE or receipt['proposal_source']!='052c42fee5c2e3eb79ee8fa3fcf0bd9350be3aab':raise AssertionError('Unexpected proposal integration ancestors')
    changed=set(subprocess.check_output(['git','diff','--name-only',BASE,'HEAD','--',*AREAS],cwd=root,text=True).splitlines())
    if changed!=set(receipt['shipping']):raise AssertionError('Unreviewed combined shipping difference: '+repr(changed.symmetric_difference(receipt['shipping'])))
    for path,row in receipt['shipping'].items():
        p=root/path
        if p.is_symlink() or hashlib.sha256(p.read_bytes()).hexdigest()!=row['after']:raise AssertionError('Changed proposal shipping identity: '+path)
        old=subprocess.run(['git','show',BASE+':'+path],cwd=root,capture_output=True)
        if row['before'] is None:
            if old.returncode==0:raise AssertionError('Unexpected existing proposal path: '+path)
        elif old.returncode or hashlib.sha256(old.stdout).hexdigest()!=row['before']:raise AssertionError('Incorrect proposal predecessor: '+path)
    if (root/'VERSION').read_text()!=subprocess.check_output(['git','show',BASE+':VERSION'],cwd=root,text=True):raise AssertionError('Do not silently replace the delivered release version')
    return BASE
if __name__=='__main__':
    root=Path(__file__).resolve().parents[1];validate(root);print('All proposal shipping differences match the reviewed reconciliation; original combined files retained.')
