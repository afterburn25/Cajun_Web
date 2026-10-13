#!/usr/bin/env python3
"""Deterministic complete/update archives. No deployment, secrets, or wrapper directory."""
from pathlib import Path
import argparse, hashlib, json, re, subprocess, zipfile
ROOT=Path(__file__).resolve().parents[1]
SITE=ROOT/'site'

def sha(data): return hashlib.sha256(data).hexdigest()
def inventory():
    paths=sorted(p for p in SITE.rglob('*') if p.is_file())
    if any(p.is_symlink() for p in SITE.rglob('*')): raise RuntimeError('Symlink in release source')
    if any(p.name in {'config.php','.installed.php','proxy-config.php'} or 'storage' in p.relative_to(SITE).parts for p in paths):
        raise RuntimeError('Private runtime data must not be packaged')
    return {p.relative_to(SITE).as_posix():sha(p.read_bytes()) for p in paths}
def main():
    ap=argparse.ArgumentParser();ap.add_argument('--refresh',action='store_true');ap.add_argument('--commit',default='local-uncommitted');args=ap.parse_args()
    inv=inventory();install=SITE/'install.php';s=install.read_text()
    expected={k:v for k,v in inv.items() if k!='install.php'}
    body='function setup_expected_files(): array { return [\n'+''.join(f"        '{k}' => '{v}',\n" for k,v in expected.items())+'    ]; }'
    pattern=r'function setup_expected_files\(\): array \{ return \[.*?\n    \]; \}'
    if args.refresh:
        s,n=re.subn(pattern,lambda _:body,s,count=1,flags=re.S)
        if n!=1:raise RuntimeError('Installer inventory block not found exactly once')
        install.write_text(s);inv=inventory()
    else:
        actual=re.search(pattern,s,re.S)
        if not actual or actual.group()!=body:raise RuntimeError('Installer hashes are stale; run build.py --refresh after changes')
    version=re.search(r"const CWP_VERSION\s*=\s*'([^']+)'",(SITE/'app/core.php').read_text()).group(1)
    if f"const CWP_INSTALLER_BUILD = '{version}'" not in s or "const CWP_INSTALLER_LAYOUT = 'one-page'" not in s:raise RuntimeError('Version/layout mismatch')
    if 'Unlock your installation.' in s or 'data-back=' in s:raise RuntimeError('Legacy installer gate or wizard returned')
    if s.count('id="install-form"')!=1:raise RuntimeError('Expected one visible installation form')
    php='php8.3' if __import__('shutil').which('php8.3') else 'php'
    for path in SITE.rglob('*.php'):subprocess.run([php,'-l',str(path)],check=True,capture_output=True)
    for path in SITE.rglob('*.js'):subprocess.run(['node','--check',str(path)],check=True,capture_output=True)
    for idx,script in enumerate(re.findall(r'<script[^\n]*>\n(.*?)</script>',s,re.S)):
        # Only a JSON boolean is interpolated; validate the known template explicitly.
        script=script.replace('<?=json_encode($secure)?>','true')
        if '<?' in script:raise RuntimeError('Unexpected PHP interpolation in embedded script')
        check=ROOT/f'.installer-script-{idx}.js';check.write_text(script)
        try:subprocess.run(['node','--check',str(check)],check=True,capture_output=True)
        finally:check.unlink()
    out=ROOT/'dist';out.mkdir(exist_ok=True)
    manifest={'version':version,'source_commit':args.commit,'layout':'flat','files':inv,'fresh_install':'install.php','existing_install':'Merge update files; sign into admin.php and use Version & database. Never rerun fresh setup.'}
    (out/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
    for kind in ['COMPLETE-UPLOAD','UPDATE']:
        target=out/f'CajunWebPro-{version}-{kind}.zip'
        with zipfile.ZipFile(target,'w',compression=zipfile.ZIP_STORED) as z:
            for name in inv:
                if kind=='UPDATE' and name=='install.php':continue
                i=zipfile.ZipInfo(name,date_time=(2026,10,12,0,0,0));i.create_system=3;i.external_attr=0o100644<<16;i.compress_type=zipfile.ZIP_STORED
                z.writestr(i,(SITE/name).read_bytes())
        with zipfile.ZipFile(target) as z:
            if z.testzip() is not None:raise RuntimeError('Archive CRC failure')
            for name in z.namelist():
                if sha(z.read(name))!=inv[name]:raise RuntimeError('Packaged bytes mismatch: '+name)
    files=[p for p in sorted(out.iterdir()) if p.suffix=='.zip' or p.name=='manifest.json']
    (out/'SHA256SUMS.txt').write_text(''.join(f'{sha(p.read_bytes())}  {p.name}\n' for p in files))
    print(json.dumps({'version':version,'shipping_files':len(inv),'php_files':len(list(SITE.rglob('*.php'))),'zip_bytes':{p.name:p.stat().st_size for p in files if p.suffix=='.zip'}},indent=2))
if __name__=='__main__':main()
