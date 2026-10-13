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
    # Canonical independent components are bundled verbatim; verification rejects divergent copies.
    for area in ['access','invitations']:
        for source in (ROOT/'foundation'/area).glob('*.php'):
            bundled=SITE/'app/foundation'/area/source.name
            if args.refresh:
                bundled.parent.mkdir(parents=True,exist_ok=True);bundled.write_bytes(source.read_bytes())
            elif not bundled.is_file() or bundled.read_bytes()!=source.read_bytes():
                raise RuntimeError('Stale bundled foundation: '+str(source))
    inv=inventory();install=SITE/'install.php';s=install.read_text(encoding='utf-8')
    core=(SITE/'app/core.php').read_text(encoding='utf-8').removeprefix('<?php\ndeclare(strict_types=1);\n').replace("const CWP_ROOT = __DIR__ . '/..';","const CWP_ROOT = __DIR__;")
    begin=s.index("defined('CWP_BOOT') || exit;");end=s.index('\nsecurity_headers();',begin)
    if args.refresh:s=s[:begin]+core+s[end:]
    elif s[begin:end]!=core:raise RuntimeError('Embedded installer bootstrap differs from application core')
    expected={k:v for k,v in inv.items() if k!='install.php'}
    body='function setup_expected_files(): array { return [\n'+''.join(f"        '{k}' => '{v}',\n" for k,v in expected.items())+'    ]; }'
    pattern=r'function setup_expected_files\(\): array \{ return \[.*?\n    \]; \}'
    if args.refresh:
        s,n=re.subn(pattern,lambda _:body,s,count=1,flags=re.S)
        if n!=1:raise RuntimeError('Installer inventory block not found exactly once')
        install.write_text(s,encoding='utf-8',newline='\n');inv=inventory()
    else:
        actual=re.search(pattern,s,re.S)
        if not actual or actual.group()!=body:raise RuntimeError('Installer hashes are stale; run build.py --refresh after changes')
    version=re.search(r"const CWP_VERSION\s*=\s*'([^']+)'",(SITE/'app/core.php').read_text(encoding='utf-8')).group(1)
    if f"const CWP_INSTALLER_BUILD = '{version}'" not in s or "const CWP_INSTALLER_LAYOUT = 'one-page'" not in s:raise RuntimeError('Version/layout mismatch')
    if 'Unlock your installation.' in s or 'data-back=' in s:raise RuntimeError('Legacy installer gate or wizard returned')
    if s.count('id="install-form"')!=1:raise RuntimeError('Expected one visible installation form')
    if args.commit!='local-uncommitted' and not args.refresh:
        args.commit=subprocess.check_output(['git','rev-parse',args.commit+'^{commit}'],cwd=ROOT,text=True).strip()
        tracked=subprocess.check_output(['git','ls-tree','-r','--name-only',args.commit,'--','site'],cwd=ROOT,text=True).splitlines()
        if {name.removeprefix('site/') for name in tracked}!=set(inv):raise RuntimeError('Shipping inventory differs from recorded source commit')
        for name,digest in inv.items():
            data=subprocess.check_output(['git','show',args.commit+':site/'+name],cwd=ROOT)
            if sha(data)!=digest:raise RuntimeError('Shipping bytes differ from recorded source commit: '+name)
    php='php8.3' if __import__('shutil').which('php8.3') else 'php'
    for path in SITE.rglob('*.php'):subprocess.run([php,'-l',str(path)],check=True,capture_output=True)
    for path in SITE.rglob('*.js'):subprocess.run(['node','--check',str(path)],check=True,capture_output=True)
    for idx,script in enumerate(re.findall(r'<script[^\n]*>\n(.*?)</script>',s,re.S)):
        # Only a JSON boolean is interpolated; validate the known template explicitly.
        script=script.replace('<?=json_encode($secure)?>','true')
        if '<?' in script:raise RuntimeError('Unexpected PHP interpolation in embedded script')
        check=ROOT/f'.installer-script-{idx}.js';check.write_text(script,encoding='utf-8',newline='\n')
        try:subprocess.run(['node','--check',str(check)],check=True,capture_output=True)
        finally:check.unlink()
    out=ROOT/'dist';out.mkdir(exist_ok=True)
    manifest={'version':version,'source_commit':args.commit,'layout':'flat','files':inv,'fresh_install':'install.php','existing_install':'Back up first. Merge UPDATE files; sign into admin.php. From 1.0.8 use Version & database first. If needed, open Administrators to select the initial owner and enable account access. Owners enable Client support explicitly and allow each client under their support agreement. Owners enable Service catalog explicitly for approved prices and existing-project invoice snapshots. Never rerun fresh setup.'}
    (out/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8',newline='\n')
    for kind in ['COMPLETE-UPLOAD','UPDATE']:
        target=out/f'CajunWebPro-{version}-{kind}.zip'
        with zipfile.ZipFile(target,'w',compression=zipfile.ZIP_STORED) as z:
            for name in inv:
                if kind=='UPDATE' and name=='install.php':continue
                i=zipfile.ZipInfo(name,date_time=(2026,10,12,0,0,0));i.create_system=3;i.external_attr=0o100644<<16;i.compress_type=zipfile.ZIP_STORED
                z.writestr(i,(SITE/name).read_bytes())
        with zipfile.ZipFile(target) as z:
            if z.testzip() is not None:raise RuntimeError('Archive CRC failure')
            wanted=set(inv)-({'install.php'} if kind=='UPDATE' else set())
            if len(z.namelist())!=len(wanted) or set(z.namelist())!=wanted:raise RuntimeError('Unexpected archive inventory')
            for name in z.namelist():
                if name.startswith('/') or '\\' in name or '..' in Path(name).parts:raise RuntimeError('Unsafe archive path')
                if sha(z.read(name))!=inv[name]:raise RuntimeError('Packaged bytes mismatch: '+name)
    files=[out/f'CajunWebPro-{version}-{kind}.zip' for kind in ['COMPLETE-UPLOAD','UPDATE']]+[out/'manifest.json']
    (out/'SHA256SUMS.txt').write_text(''.join(f'{sha(p.read_bytes())}  {p.name}\n' for p in files),encoding='utf-8',newline='\n')
    print(json.dumps({'version':version,'shipping_files':len(inv),'php_files':len(list(SITE.rglob('*.php'))),'zip_bytes':{p.name:p.stat().st_size for p in files if p.suffix=='.zip'}},indent=2))
if __name__=='__main__':main()
