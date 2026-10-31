"""Actual complete ZIP install and exact deployed RC financial upgrade under Apache.
Disposable loopback SQL only. No provider, SMTP or merchant calls. Financial
records are created with the original installed RC before applying the UPDATE.
"""
from pathlib import Path, PurePosixPath
from urllib.parse import urlsplit, urljoin
from collections import Counter
import getpass, grp, hashlib, json, os, re, shutil, socket, subprocess, sys, time, traceback, zipfile
import requests
from bs4 import BeautifulSoup
ROOT = Path.cwd().resolve()
OUT = ROOT / 'qa/owner-release'; WORK = ROOT / 'test-runtime/owner-release'
PHP = os.environ.get('CWP_APACHE_PHP', '8.3')
OWNER = 'Synthetic owner release passphrase'
checks, scopes = [], []
if os.geteuid()==0 or os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':
    raise SystemExit('Unprivileged disposable loopback fixture only')
if WORK.exists(): raise SystemExit('Refuse stale fixture data')
OUT.mkdir(parents=True, exist_ok=True); WORK.mkdir(parents=True)
VERSION=(ROOT/'VERSION').read_text().strip()
COMPLETE=ROOT/f'dist/CajunWebPro-{VERSION}-COMPLETE-UPLOAD.zip'
UPDATE=ROOT/f'dist/CajunWebPro-{VERSION}-UPDATE.zip'
RC=ROOT/'test-runtime/rc126-original'
if VERSION!='1.26.1' or not RC.is_dir(): raise SystemExit('Verified RC and 1.26.1 required')

def check(name, ok):
    checks.append({'name':name,'passed':bool(ok)})
    (OUT/'progress.json').write_text(json.dumps({'complete':False,'checks':checks},indent=2)+'\n')
    print(('PASS ' if ok else 'FAIL ')+name,flush=True)
    if not ok: raise AssertionError(name)

def unpack(archive, dest):
    with zipfile.ZipFile(archive) as z:
        names=z.namelist()
        assert len(names)==len(set(names))==len({n.casefold() for n in names}) and z.testzip() is None
        for item in z.infolist():
            name=PurePosixPath(item.filename)
            assert not name.is_absolute() and '..' not in name.parts and '\\' not in item.filename
            assert not item.filename.lower().endswith('.zip') and not item.flag_bits&1
        z.extractall(dest)

class LocalSession(requests.Session):
    def send(self, request, **kwargs):
        u=urlsplit(request.url)
        if u.scheme!='http' or u.hostname!='127.0.0.1': raise RuntimeError('Nonloopback HTTP refused')
        return super().send(request, **kwargs)

def fields(response, action):
    assert response.status_code==200, (action,response.status_code)
    forms=[f for f in BeautifulSoup(response.text,'html.parser').select('form')
           if any(e.get('value')==action for e in f.select('[name=action]'))]
    assert len(forms)==1, (action,'form count',len(forms))
    f=forms[0]; data={}
    for e in f.select('input[name],select[name],textarea[name]'):
        if e.has_attr('disabled') or e.get('type') in ('submit','button','file','password'): continue
        if e.get('type') in ('checkbox','radio') and not e.has_attr('checked'): continue
        if e.name=='textarea': value=e.text
        elif e.name=='select':
            option=e.select_one('option[selected]') or e.select_one('option'); value=option.get('value',option.text) if option else ''
        else: value=e.get('value','')
        data[e['name']]=value
    data['action']=action
    return urljoin(response.url,f.get('action') or response.url),data

def submit(session, url, action, values=None):
    target,data=fields(session.get(url,timeout=120),action); data.update(values or {})
    return session.post(target,data=data,timeout=180)

def good(response):
    return response.status_code==200 and not re.search(r'PHP (?:Fatal error|Warning|Notice)|We could not complete this request',response.text)

def retained(before,after):
    if before['config']!=after['config'] or before['lock']!=after['lock']: return False
    if any(after['private'].get(k)!=v for k,v in before['private'].items()): return False
    for name,rows in before['tables'].items():
        now=after['tables'].get(name)
        if now is None: return False
        if name in ('audit','settings'):
            if Counter(rows)-Counter(now): return False
        elif rows!=now: return False
    return True

def probe(root,mode):
    p=subprocess.run(['php'+PHP,str(ROOT/'tests/owner_release_finance_probe.php'),str(root),mode],cwd=ROOT,capture_output=True,text=True,timeout=120)
    if p.returncode or p.stderr: raise RuntimeError('Finance '+mode+': '+p.stdout+' '+p.stderr)
    return json.loads(p.stdout)

def install(session,url,prefix,parent):
    r=submit(session,url+'/install.php','install',{
        'db_host':'127.0.0.1','db_port':os.environ['CWP_TEST_PORT'],'db_name':'cwp_test',
        'db_user':os.environ['CWP_TEST_USER'],'db_password':os.environ['CWP_TEST_PASSWORD'],
        'db_prefix':prefix,'owner_name':'Release fixture owner','owner_email':'release@example.invalid',
        'owner_password':OWNER,'owner_confirm':OWNER,'site_url':url,'confirm_install':'1',
        'seed':'1','preview':'1','private_parent':str(parent)})
    data=r.json()
    check(prefix+' actual installer succeeds',r.status_code==200 and data.get('ok') is True)
    check(prefix+' installer reports all filesystem checks passing',all(c['ok'] for c in data['checks']))
    return data

def browse(session,url,label,rewrite):
    for page in ['home','about','services','hosting','products','work','process','contact','privacy','consultation','estimate','packages','faq','industries','why-custom','compare-wix','compare-squarespace','switch']:
        r=session.get(url+'/index.php',params={'page':page},timeout=30)
        check(label+' public '+page,good(r))
    for endpoint in ['robots.php','sitemap.php','api/robots.php','api/sitemap.php','portal/index.php?software=1']:
        check(label+' public endpoint '+endpoint,good(session.get(url+'/'+endpoint,timeout=30)))
    for private in ['config.php','.installed.php','app/licenses/service.php','admin/controllers/studio.php','database/schema/base.php','includes/bootstrap.php','storage/']:
        r=session.get(url+'/'+private,timeout=30)
        check(label+' internal protected '+private,r.status_code in (403,404) and '<?php' not in r.text)
    r=session.post(url+'/api/validate.php',json={'license_key':'short','domain':'example.com'},timeout=30)
    check(label+' validation rejects invalid key',r.status_code==400 and r.json().get('authorized') is False)
    if rewrite:
        r=session.post(url+'/api/validate',json={'license_key':'short','domain':'example.com'},timeout=30)
        check(label+' rewritten validation is same protected API',r.status_code==400 and r.json().get('authorized') is False)
        check(label+' rewritten robots endpoint',session.get(url+'/robots.txt',timeout=30).status_code==200)
    homepage=session.get(url+'/',timeout=30)
    doc=BeautifulSoup(homepage.text,'html.parser'); assets=set()
    for e in doc.select('img[src],link[rel=stylesheet][href],script[src]'):
        target=urljoin(url+'/',e.get('src') or e['href'])
        if urlsplit(target).hostname=='127.0.0.1':assets.add(target)
    check(label+' public assets present',bool(assets))
    for target in sorted(assets):check(label+' asset '+urlsplit(target).path,session.get(target,timeout=30).status_code==200)

complete=False
try:
    for rewrite in (True,False):
        for nested in (False,True):
            label=('rewrite' if rewrite else 'no-rewrite')+('-subfolder' if nested else '-root')
            work=WORK/label;web=work/'web';web.mkdir(parents=True)
            root=web/'studio' if nested else web;root.mkdir(exist_ok=True)
            shutil.copytree(RC,root,dirs_exist_ok=True)
            private=work/'private-parent';private.mkdir(mode=0o700)
            with socket.socket() as sock: sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
            url=f'http://127.0.0.1:{port}'+('/studio' if nested else '')
            prefix='own'+('r' if rewrite else 'n')+('s_' if nested else 'r_')
            modules=['mpm_prefork','authz_core','authz_host','dir','mime','alias','env','headers','autoindex']+(['rewrite'] if rewrite else [])
            conf=f'ServerRoot "{work}"\nServerName localhost\nListen 127.0.0.1:{port}\nPidFile "{work}/httpd.pid"\nErrorLog "{OUT}/{label}.log"\nLogLevel warn\nUser {getpass.getuser()}\nGroup {grp.getgrgid(os.getgid()).gr_name}\nDocumentRoot "{web}"\nTypesConfig /etc/mime.types\n'
            conf+=''.join(f'LoadModule {m}_module /usr/lib/apache2/modules/mod_{m}.so\n' for m in modules)
            conf+=f'LoadModule php_module /usr/lib/apache2/modules/libphp{PHP}.so\nPHPIniDir /etc/php/{PHP}/apache2\n<FilesMatch "\\.php$">\n SetHandler application/x-httpd-php\n</FilesMatch>\n<Directory "{web}">\n AllowOverride All\n Require all granted\n</Directory>\n'
            ini=work/'php-conf';ini.mkdir();(ini/'99-release.ini').write_text('opcache.enable=1\nopcache.validate_timestamps=0\nopcache.file_update_protection=0\nopcache.memory_consumption=256\nopcache.max_accelerated_files=30000\n')
            config=work/'httpd.conf';config.write_text(conf)
            env={**os.environ,'PHP_INI_SCAN_DIR':f'/etc/php/{PHP}/apache2/conf.d:'+str(ini)}
            stderr=(work/'process.log').open('w')
            proc=subprocess.Popen(['/usr/sbin/apache2','-f',str(config),'-DFOREGROUND'],env=env,stdout=stderr,stderr=stderr,start_new_session=True)
            start=len(checks)
            try:
                anon=LocalSession()
                for _ in range(100):
                    try:
                        r=anon.get(url+'/install.php',timeout=2)
                        if r.status_code==200:break
                    except requests.RequestException:pass
                    time.sleep(.1)
                else:raise RuntimeError('Apache unavailable: '+(work/'process.log').read_text())
                check(label+' actual Apache is serving PHP','Apache/' in r.headers.get('Server','') and 'install-form' in r.text)
                data=install(anon,url,prefix,private)
                check(label+' original installer is removed',not(root/'install.php').exists())
                check(label+' private store is outside document root',not Path(data['storage']).is_relative_to(web))
                seeded=probe(root,'seed');check(label+' original RC retains active suspended and paid-off states',seeded=={'seeded':True,'states':['active','suspended','paid_off']})
                before=probe(root,'snapshot');(OUT/(label+'-before.json')).write_text(json.dumps(before,indent=2)+'\n')
                owner=LocalSession();admin=url+'/admin/index.php'
                r=submit(owner,admin+'?view=login','login',{'email':'release@example.invalid','password':OWNER})
                check(label+' original owner signs in',good(r) and 'view=login' not in r.url)
                unpack(UPDATE,root);updater=url+f'/cwp-update-{VERSION}/owner-update.php'
                r=owner.get(updater,timeout=30)
                check(label+' staged updater trusts valid owner without password re-entry',good(r) and 'Signed in as' in r.text and 'name="owner_password"' not in r.text)
                target,post=fields(r,'apply')
                denied=owner.post(target,data={**post,'csrf':'invalid','backup_confirm':'1'},timeout=30)
                check(label+' bad CSRF refuses update and preserves financial data','Your session expired' in denied.text and retained(before,probe(root,'snapshot')) and probe(root,'snapshot')['version']=='1.26.0')
                r=submit(owner,updater,'apply');check(label+' backup confirmation required','Confirm a recoverable backup' in r.text)
                r=submit(owner,updater,'inspect',{'backup_confirm':'1'});check(label+' exact RC file identity is accepted',good(r) and 'Starting release: 1.26.0' in r.text and not BeautifulSoup(r.text,'html.parser').select('.error'))
                r=submit(owner,updater,'apply',{'backup_confirm':'1'});after=probe(root,'snapshot')
                check(label+' actual UPDATE succeeds with Apache opcode caching',good(r) and '<h2>Complete</h2>' in r.text and after['version']==VERSION)
                check(label+' every preexisting financial row and private file retained',retained(before,after))
                verified=probe(root,'verify');check(label+' paid-off deactivation refused and all three states retained',verified['permanent_unlock'] and verified['states']==['active','suspended','paid_off'])
                check(label+' update did not enable live payments',verified['payment_mode']=='disabled')
                (OUT/(label+'-after.json')).write_text(json.dumps(after,indent=2)+'\n')
                for view in ['dashboard','catalog','provider-settings','studio-licenses','studio-upgrade','support','recurring-billing','invoice-payments']:
                    r=owner.get(admin+'?view='+view,timeout=30)
                    check(label+' upgraded admin '+view,good(r) and 'view=login' not in r.url and not BeautifulSoup(r.text,'html.parser').select('input[name=current_password],input[name=owner_password]'))
                for section in ['overview','connection','services','cloud','domains','website','advanced']:
                    check(label+' 20i section '+section,good(owner.get(admin+'?view=provider-settings&section='+section,timeout=30)))
                r=owner.get(url+'/portal/index.php?master=1',timeout=30)
                check(label+' master portal with existing owner session',good(r) and 'Master client portal' in r.text and 'view=login' not in r.url)
                browse(anon,url,label+' upgraded',rewrite)
                fresh=web/'fresh';unpack(COMPLETE,fresh);freshurl=f'http://127.0.0.1:{port}/fresh'
                new=LocalSession();install(new,freshurl,'new'+('r' if rewrite else 'n')+('s_' if nested else 'r_'),private)
                check(label+' COMPLETE removes executable installer',not(fresh/'install.php').exists() and not list((fresh/'install').rglob('*.php')))
                freshowner=LocalSession();r=submit(freshowner,freshurl+'/admin/index.php?view=login','login',{'email':'release@example.invalid','password':OWNER})
                check(label+' COMPLETE owner can sign in',good(r) and 'view=login' not in r.url)
                r=freshowner.get(freshurl+'/admin/index.php?view=studio-licenses',timeout=30)
                check(label+' fresh licensing setup is visible and password-free',good(r) and 'Software licensing workspace' in r.text and 'name="current_password"' not in r.text)
                browse(new,freshurl,label+' fresh',rewrite)
                scopes.append({'scope':label,'checks':len(checks)-start,'financial_tables':len(before['tables']),'passed':True})
                for sess in (anon,owner,new,freshowner):sess.close()
            finally:
                proc.terminate()
                try:proc.wait(timeout=10)
                except subprocess.TimeoutExpired:proc.kill();proc.wait()
                stderr.close()
            log=(OUT/(label+'.log')).read_text()
            check(label+' no unexpected PHP or handled application errors',not any(x in log for x in ('PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP [')))
    complete=True
except Exception as error:
    checks.append({'name':type(error).__name__+': '+str(error),'passed':False})
    (OUT/'failure.txt').write_text(traceback.format_exc())
    print(traceback.format_exc(),flush=True)
finally:
    report={'passed':complete and len(scopes)==4 and all(c['passed'] for c in checks),'source_sha':os.environ.get('CWP_SOURCE_SHA'),'version':VERSION,'checks':checks,'scopes':scopes,'scope':'Actual Apache with rewrite on/off, root/subfolder exact deployed RC upgrades retaining signed active/suspended/paid-off financing and private files; complete ZIP fresh installation and public/admin smoke. Synthetic SQL, no merchant/provider/email activity.','packages':{p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in (COMPLETE,UPDATE)}}
    (OUT/'results.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({'passed':report['passed'],'checks':len(checks),'scopes':len(scopes)}))
raise SystemExit(0 if report['passed'] else 1)
