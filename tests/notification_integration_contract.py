"""Validate the exact additive notification layer without rewriting older feature evidence."""
from pathlib import Path
import hashlib,json,subprocess
BASE='366d53b1ed6b367b32664f2b91ee073fc6813544'
AREAS=['site','foundation','tools/structure','tools/build.py','VERSION','START-HERE.txt']
def validate(root):
    revision='HEAD'
    if (root/'docs/ACCOUNT_NOTIFICATION_COMBINATION.json').exists():
        from account_notification_contract import validate as combination
        revision=combination(root)
    def contents(path):
        p=root/path
        if p.is_symlink():raise AssertionError('Symlink notification output: '+path)
        return p.read_bytes() if revision=='HEAD' else subprocess.check_output(['git','show',revision+':'+path],cwd=root)
    receipt=json.loads((root/'docs/NOTIFICATION_CENTER_SOURCE.json').read_text())
    if receipt['base']!=BASE or receipt['reviewed_original']!='04457d25a6b577edadcb993f62986fd568033d15':raise AssertionError('Unexpected notification source identities')
    paths=receipt['shipping']
    changed=set(subprocess.check_output(['git','diff','--name-only',BASE,revision,'--',*AREAS],cwd=root,text=True).splitlines())
    if changed!=set(paths):raise AssertionError('Unreviewed notification shipping difference: '+repr(changed.symmetric_difference(paths)))
    for path,row in paths.items():
        p=Path(path)
        if p.is_absolute() or '..' in p.parts:raise AssertionError('Unsafe notification source path')
        target=root/p
        if target.is_symlink() or hashlib.sha256(contents(path)).hexdigest()!=row['after']:raise AssertionError('Changed notification output: '+path)
        old=subprocess.run(['git','show',BASE+':'+path],cwd=root,capture_output=True)
        if row['before'] is None:
            if old.returncode==0:raise AssertionError('Unexpected notification addition: '+path)
        elif old.returncode or hashlib.sha256(old.stdout).hexdigest()!=row['before']:raise AssertionError('Changed notification predecessor: '+path)
    if contents('VERSION')!=subprocess.check_output(['git','show',BASE+':VERSION'],cwd=root):raise AssertionError('Delivered version identity must remain unchanged before release')
    return BASE
if __name__=='__main__':validate(Path(__file__).resolve().parents[1]);print('Exact notification integration and original shipping scope verified.')
