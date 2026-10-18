#!/usr/bin/env python3
"""Three deterministic, sanitized artifacts from one clean recorded commit."""
from pathlib import Path, PurePosixPath
import os,argparse,hashlib,json,re,subprocess,zipfile,shutil,tempfile
from contextlib import contextmanager
ROOT=Path(__file__).resolve().parents[1];SITE=ROOT/'site'
MAP=json.loads((ROOT/'tools/directory-map.json').read_text(encoding="utf-8"))
SCAFFOLDS={'storage/.htaccess','storage/index.php','upload/.htaccess','upload/index.php'}
ROOT_FILES={'.htaccess','favicon.ico','index.php','install.php','robots.php','sitemap.php'}
def sha(data):return hashlib.sha256(data).hexdigest()
def write(path,data):path.parent.mkdir(parents=True,exist_ok=True);path.write_text(data,encoding='utf-8',newline='\n')
def source_bytes(commit,name):
    # cat-file treats this as an object expression, never a possible filesystem
    # path. `git show` otherwise stats SHA:path and can exceed Windows MAX_PATH
    # even when the real checked-out dependency file is readable.
    return subprocess.check_output(['git','cat-file','blob',commit+':'+name],cwd=ROOT)

@contextmanager
def source_blobs(commit,names):
    # Resolve paths once against the recorded tree. Only fixed object IDs enter
    # the binary batch protocol; path lengths, quoting and newlines cannot alter it.
    tree=subprocess.check_output(['git','ls-tree','-r','-z','--full-tree',commit],cwd=ROOT)
    objects={}
    for entry in tree.split(b'\0'):
        if not entry:continue
        metadata,path=entry.split(b'\t',1);mode,kind,oid=metadata.split(b' ')
        name=path.decode('utf-8','surrogateescape');safe(name)
        if mode not in {b'100644',b'100755'} or kind!=b'blob':raise RuntimeError('Non-regular Git source: '+name)
        if not re.fullmatch(rb'(?:[0-9a-f]{40}|[0-9a-f]{64})',oid) or name in objects:raise RuntimeError('Invalid Git source tree')
        objects[name]=oid
    if len(names)!=len(set(names)) or set(objects)!=set(names):raise RuntimeError('Git blob snapshot differs from tracked source')
    # Drain stderr to a temporary file instead of an unread pipe. Requests are
    # sent one at a time, so retained memory stays full-project bytes plus one blob.
    with tempfile.TemporaryFile() as errors:
        process=subprocess.Popen(['git','cat-file','--batch'],cwd=ROOT,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=errors)
        try:
            def read_source(name):
                oid=objects[name]
                process.stdin.write(oid+b'\n');process.stdin.flush()
                header=process.stdout.readline(128)
                match=re.fullmatch(rb'([0-9a-f]{40}|[0-9a-f]{64}) blob (0|[1-9][0-9]*)\n',header)
                if match is None or match[1]!=oid:raise RuntimeError('Invalid Git batch blob header: '+name)
                size=int(match[2]);data=process.stdout.read(size)
                if len(data)!=size or process.stdout.read(1)!=b'\n':raise RuntimeError('Truncated Git batch blob: '+name)
                digest=(hashlib.sha1 if len(oid)==40 else hashlib.sha256)()
                digest.update(b'blob '+str(size).encode('ascii')+b'\0');digest.update(data)
                if digest.hexdigest().encode('ascii')!=oid:raise RuntimeError('Git batch blob identity differs: '+name)
                return data
            yield read_source
            process.stdin.close()
            if process.stdout.read(1):raise RuntimeError('Unexpected trailing Git batch output')
            if process.wait():
                errors.seek(0)
                raise RuntimeError('Git batch blob reader failed: '+errors.read(8192).decode('utf-8','replace'))
        finally:
            if process.poll() is None:process.kill()
            process.wait()
            try:process.stdin.close()
            finally:process.stdout.close()

def safe(name):
    p=PurePosixPath(name)
    if p.is_absolute() or '..' in p.parts or '\\' in name or ':' in name or str(p)!=name:raise RuntimeError('Unsafe path: '+name)
def inventory():
    result={}
    for p in sorted(SITE.rglob('*'),key=lambda path:path.relative_to(SITE).as_posix()):
        if p.is_symlink():raise RuntimeError('Symlink in shipping source')
        if not p.is_file():continue
        n=p.relative_to(SITE).as_posix();safe(n)
        if n.split('/')[0] in {'storage','upload'} and n not in SCAFFOLDS:raise RuntimeError('Runtime storage in package: '+n)
        if n in SCAFFOLDS and p.read_text(encoding="utf-8")!=('Require all denied\n' if p.name=='.htaccess' else '<?php http_response_code(404); exit;\n'):raise RuntimeError('Unexpected runtime scaffold metadata: '+n)
        if n.startswith('assets/') and not (n.startswith('assets/css/') and p.suffix=='.css' or n.startswith('assets/js/') and p.suffix=='.js'):raise RuntimeError('Mixed public assets: '+n)
        if p.name in {'config.php','.installed.php','proxy-config.php','payment-master.key','mail-master.key','provider-master.key','.installation-mutex.php'} or p.name.startswith('config-pending-'):raise RuntimeError('Private configuration in package: '+n)
        result[n]=sha(p.read_bytes())
    if {n for n in result if '/' not in n}!=ROOT_FILES:raise RuntimeError('Unexpected production root files')
    for directory in ['admin','portal','pages','includes','images','storage','upload','app','api','webhooks','media','install','database','config','assets/css','assets/js']:
        if not any(n.startswith(directory+'/') for n in result):raise RuntimeError('Missing functional directory: '+directory)
    if len((SITE/'admin/index.php').read_bytes())>1000 or len((SITE/'index.php').read_bytes())>1000:raise RuntimeError('Business logic in a root entry')
    for src,dst in MAP['paths'].items():
        if not (SITE/dst).is_file():raise RuntimeError('Missing mapped destination: '+dst)
        if src!=dst and src not in ROOT_FILES and (SITE/src).is_file():raise RuntimeError('Stale moved source: '+src)
    for directory in MAP['protected_directories']:
        if (SITE/directory/'.htaccess').read_text(encoding="utf-8")!='Require all denied\n':raise RuntimeError('Missing internal denial: '+directory)
    for path in SITE.rglob('*.php'):
        if path.name.endswith('.generated.php'):continue
        # Maintained charset tables contain intentional non-UTF-8 PHP string bytes.
        # Preserve them exactly; ASCII dependency scanning still works. Own source stays strict UTF-8.
        vendor=SITE/'includes/foundation/mail-receiving/vendor'
        text=path.read_text(encoding="utf-8",errors='surrogateescape' if path.is_relative_to(vendor) else 'strict')
        for dependency in re.findall(r"CWP_CODE_ROOT\s*\.\s*'(/[^']+\.php)'",text):
            if not (SITE/dependency.lstrip('/')).is_file():raise RuntimeError('Stale code dependency in '+str(path)+': '+dependency)
        for asset in re.findall(r'src=[\"\']((?:assets|images)/[^\"\'<>?]+)[\"\']',text):
            if not (SITE/asset).is_file():raise RuntimeError('Missing literal public asset: '+asset)
        for old in MAP['routes']:
            if re.search(r"site_url\('"+re.escape(old)+r"[?'\"]",text):raise RuntimeError('Legacy URL generated by canonical code: '+str(path))
    for path in (SITE/'assets/css').glob('*.css'):
        for url in re.findall(r'url\([\'"]?([^\)\'"\s]+)',path.read_text(encoding="utf-8")):
            if not url.startswith(('data:','https:','#')) and (not (path.parent/url).is_file() or not (path.parent/url).resolve().is_relative_to(SITE.resolve())):raise RuntimeError('Missing or outside CSS asset: '+url)
    return result

def generated(refresh):
    from check_mail_vendor import check as check_mail_vendor, check_mirror
    check_mail_vendor(check_runtime=False)
    receiving=ROOT/'foundation/mail-receiving'
    for source in receiving.rglob('*'):
        if source.is_symlink():raise RuntimeError('Symlink in receiving source')
        if source.is_file():
            target=SITE/'includes/foundation/mail-receiving'/source.relative_to(receiving)
            if refresh:
                target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(source.read_bytes())
    check_mirror(required=True)
    from check_mail_submission_vendor import check as check_submission_vendor, check_mirror as check_submission_mirror
    check_submission_vendor()
    submission=ROOT/'foundation/mail-submission'
    for source in submission.rglob('*'):
        if source.is_symlink():raise RuntimeError('Symlink in submission source')
        if source.is_file() and refresh:
            target=SITE/'includes/foundation/mail-submission'/source.relative_to(submission)
            target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(source.read_bytes())
    check_submission_mirror(required=True)
    for area in ['notifications','billing-calendar','recurring-billing','service-plans','hosting','access','invitations','portfolio','estimator','email-templates','webmail','review-requests','scheduling','mail-transport','provider','twentyi','twentyi-inventory','twentyi-inventory-reader','twentyi-operations','twentyi-document-roots','twentyi-directory-listing','twentyi-support-handoff', 'twentyi-cloud-assignment','mail-compose','twentyi-domain-registration','twentyi-stackcp-access']:
        for source in (ROOT/'foundation'/area).glob('*.php'):
            target=SITE/'includes/foundation'/area/source.name
            if refresh:
                target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(source.read_bytes())
            elif source.read_bytes()!=target.read_bytes():raise RuntimeError('Divergent canonical component: '+str(source))
    # One maintainable implementation. Installer's self-contained copy is generated, never edited.
    bootstrap=(SITE/'includes/bootstrap.php').read_text(encoding="utf-8")
    modules=['config/defaults.php']+list(MAP['core_modules'])+['includes/filesystem/uploads.php']
    if re.findall(r"require_once CWP_CODE_ROOT\.'/([^']+)';",bootstrap)!=modules:raise RuntimeError('Canonical bootstrap module list differs from generated setup contract')
    guard="<?php\ndeclare(strict_types=1);\nif (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }\n"
    embedded=guard+"// GENERATED from canonical bootstrap modules by tools/build.py.\n"
    for name in modules:
        text=(SITE/name).read_text(encoding="utf-8");text=re.sub(r'^<\?php\ndeclare\(strict_types=1\);\nif \(!defined\(\'CWP_BOOT\'\)\) \{ http_response_code\(404\); exit; \}\n','',text)
        if text.startswith('<?php'):raise RuntimeError('Unexpected bootstrap module preamble: '+name)
        embedded+='\n// '+name+'\n'+text
    path=SITE/'install/bootstrap.generated.php'
    if refresh:write(path,embedded)
    elif not path.is_file() or path.read_text(encoding="utf-8")!=embedded:raise RuntimeError('Generated installer bootstrap is stale')
    inv=inventory();expected={n:h for n,h in inv.items() if n!='install/inventory.generated.php'}
    body=guard+'function setup_expected_files(): array { return [\n'+''.join("    '"+n+"'=>'"+h+"',\n" for n,h in expected.items())+']; }\n'
    path=SITE/'install/inventory.generated.php'
    if refresh:write(path,body)
    elif not path.is_file() or path.read_text(encoding="utf-8")!=body:raise RuntimeError('Installer dependency hashes are stale')
    return inventory()

def archive(out,name,files,commit,version):
    seen=set()
    for n in files:
        safe(n)
        if n.casefold() in seen:raise RuntimeError('Duplicate or case-colliding ZIP member: '+n)
        seen.add(n.casefold())
    target=out/name
    with zipfile.ZipFile(target,'w',compression=zipfile.ZIP_STORED) as z:
        for n,data in sorted(files.items()):
            i=zipfile.ZipInfo(n,date_time=(2026,10,13,0,0,0));i.create_system=3;i.external_attr=0o100644<<16;z.writestr(i,data)
    with zipfile.ZipFile(target) as z:
        if z.testzip() is not None or len(z.namelist())!=len(files):raise RuntimeError('ZIP verification failed')
        for n,data in files.items():
            if z.read(n)!=data:raise RuntimeError('ZIP bytes differ: '+n)
    directories=sorted({str(parent)+'/' for n in files for parent in PurePosixPath(n).parents if str(parent)!='.'})
    manifest={'version':version,'source_commit':commit,'artifact':name,'sha256':sha(target.read_bytes()),'directories':directories,'files':{n:sha(data) for n,data in sorted(files.items())}}
    prefix=name.removesuffix('.zip')
    write(out/(prefix+'.manifest.json'),json.dumps(manifest,indent=2)+'\n')
    write(out/(prefix+'.directories.txt'),'\n'.join(directories)+'\n')
    return manifest

def release_version(root,site):
    runtime=re.search(r"const CWP_VERSION = '([^']+)'",(site/'config/defaults.php').read_text(encoding='utf-8')).group(1)
    if not re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+',runtime):raise RuntimeError('Invalid release version')
    if (root/'VERSION').read_text(encoding='utf-8')!=runtime+'\n':raise RuntimeError('Root VERSION mismatch')
    if "const CWP_INSTALLER_BUILD = '"+runtime+"'" not in (site/'install.php').read_text(encoding='utf-8'):raise RuntimeError('Installer version mismatch')
    return runtime

def main():
    ap=argparse.ArgumentParser();ap.add_argument('--refresh',action='store_true');ap.add_argument('--commit');args=ap.parse_args()
    inv=generated(args.refresh)
    version=release_version(ROOT,SITE)
    view=(SITE/'install/views/form.php').read_text(encoding="utf-8")
    if view.count('id="install-form"')!=1 or 'data-back=' in view or 'Unlock your installation.' in view:raise RuntimeError('One-page installation contract broken')
    php=os.environ.get('PHP_BINARY') or shutil.which('php8.3') or shutil.which('php')
    for path in SITE.rglob('*.php'):
        p=subprocess.run([php,'-l',str(path)],capture_output=True,text=True)
        if p.returncode:raise RuntimeError(p.stdout+p.stderr)
    for path in (SITE/'assets/js').glob('*.js'):subprocess.run(['node','--check',str(path)],check=True,capture_output=True)
    script=(SITE/'install/views/script.php').read_text(encoding="utf-8").split('?>\n',1)[1].replace('<?=json_encode($secure)?>','true').replace('<?=json_encode(CWP_INSTALLER_BUILD)?>',json.dumps(version))
    if '<?' in script:raise RuntimeError('Unexpected installer JavaScript interpolation')
    subprocess.run(['node','--check'],input=script,text=True,encoding='utf-8',check=True,capture_output=True)
    if args.refresh:
        print('Refreshed and checked canonical installer; no release built from uncommitted files.');return
    if not args.commit:raise RuntimeError('A clean recorded --commit is required to build archives')
    commit=subprocess.check_output(['git','rev-parse',args.commit+'^{commit}'],cwd=ROOT,text=True).strip()
    if subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()!=commit:raise RuntimeError('Build checkout must be the recorded commit')
    if subprocess.check_output(['git','status','--porcelain','--untracked-files=all'],cwd=ROOT,text=True).strip():raise RuntimeError('Release source must be clean, including untracked files')
    tracked=subprocess.check_output(['git','ls-tree','-r','--name-only',commit],cwd=ROOT,text=True).splitlines()
    modes=subprocess.check_output(['git','ls-tree','-r',commit],cwd=ROOT,text=True).splitlines()
    if any(line.startswith('120000 ') for line in modes):raise RuntimeError('Git symlinks cannot be shipped, including on Windows checkouts')
    if {n[5:] for n in tracked if n.startswith('site/')}!=set(inv):raise RuntimeError('Shipping inventory differs from source commit')
    full={};allowed={'.github','docs','tests','tools','brand','foundation','site','ops'}
    with source_blobs(commit,tracked) as read_source:
        for n in tracked:
            safe(n);p=ROOT/n
            if p.is_symlink():raise RuntimeError('Project contains symlink')
            if '/' in n and n.split('/')[0] not in allowed:raise RuntimeError('Unapproved full-project area: '+n)
            if p.name in {'config.php','proxy-config.php','.installed.php','payment-master.key'} or p.name.startswith('.env') or p.suffix in {'.pem','.key','.p12','.sql'} or any(part in {'.git','node_modules','__pycache__','qa','dist','test-runtime'} for part in PurePosixPath(n).parts):raise RuntimeError('Unsanitized full-project source: '+n)
            data=read_source(n)
            if p.read_bytes()!=data:
                # Git's declared text normalization is allowed for development docs/tools.
                # Shipping bytes remain strict because their installer hashes are byte-based.
                normalized=subprocess.check_output(['git','hash-object','--path='+n,n],cwd=ROOT,text=True).strip()
                recorded=subprocess.check_output(['git','rev-parse',commit+':'+n],cwd=ROOT,text=True).strip()
                if n.startswith('site/') or normalized!=recorded:raise RuntimeError('Working bytes differ from recorded source: '+n)
            full[n]=data
    site={n:(SITE/n).read_bytes() for n in inv}
    payload={n:data for n,data in site.items() if n!='install.php' and not n.startswith(('install/','storage/','upload/'))}
    shims={old:"<?php\n/** Upgrade compatibility: directly preserves method, query, body and cookies. */\ndeclare(strict_types=1);\nrequire __DIR__.'/"+new+"';\n" for old,new in MAP['routes'].items() if old not in {'robots.php','sitemap.php'}}
    manifest={'version':version,'source_commit':commit,'files':{n:sha(data) for n,data in payload.items()},'shims':shims,'history_sha256':sha(full['tools/legacy-release-files.json'])}
    # JSON is encoded as a PHP string through base64, avoiding PHP string-escape ambiguities.
    import base64
    encoded=base64.b64encode(json.dumps(manifest).encode()).decode()
    stage=f'cwp-update-{version}/'
    update={stage+'site/'+n:data for n,data in payload.items()}
    update[stage+'owner-update.php']=full['tools/update/owner-update.php']
    update[stage+'manifest.php']=("<?php\nif (!defined('CWP_BOOT')) { http_response_code(404); exit; }\nreturn json_decode(base64_decode('"+encoded+"'),true,64,JSON_THROW_ON_ERROR);\n").encode()
    update[stage+'legacy-release-files.json']=full['tools/legacy-release-files.json']
    update[stage+'.htaccess']=b'Options -Indexes\nRequire all denied\n<Files "owner-update.php">\nRequire all granted\n</Files>\n'
    out=ROOT/'dist';out.mkdir(exist_ok=True)
    reports=[archive(out,f'CajunWebPro-{version}-{kind}.zip',files,commit,version) for kind,files in [('FULL-PROJECT',full),('COMPLETE-UPLOAD',site),('UPDATE',update)]]
    write(out/'manifest.json',json.dumps({'version':version,'source_commit':commit,'layout':'organized','files':inv,'artifacts':[{k:v for k,v in m.items() if k!='files'} for m in reports]},indent=2)+'\n')
    (out/'START-HERE.txt').write_bytes(full['START-HERE.txt'])
    files=sorted([out/'manifest.json',out/'START-HERE.txt']+[out/m['artifact'] for m in reports]+[out/(m['artifact'].removesuffix('.zip')+ext) for m in reports for ext in ['.manifest.json','.directories.txt']],key=lambda p:p.name)
    write(out/'SHA256SUMS.txt',''.join(sha(p.read_bytes())+'  '+p.name+'\n' for p in files))
    print(json.dumps({'version':version,'source_commit':commit,'shipping_files':len(inv),'artifacts':{m['artifact']:len(m['files']) for m in reports}},indent=2))
if __name__=='__main__':main()
