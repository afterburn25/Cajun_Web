"""Apache 2.4 + mod_php, unprivileged worker, actual COMPLETE ZIP, rewrite on/off."""
from pathlib import Path
import getpass,grp,json,os,shutil,subprocess,time,zipfile
import requests
from bs4 import BeautifulSoup
ROOT=Path(__file__).resolve().parents[1];QA=ROOT/'qa';RUNTIME=ROOT/'test-runtime/apache';checks=[]
def check(name,value):
    checks.append({'name':name,'passed':bool(value)})
    if not value:raise AssertionError(name)
def csrf(r):return BeautifulSoup(r.text,'html.parser').select_one('meta[name=csrf-token],input[name=csrf]').get('content') or BeautifulSoup(r.text,'html.parser').select_one('input[name=csrf]')['value']
def wait(base):
    for _ in range(60):
        try:return requests.get(base+'/install.php',timeout=1)
        except requests.RequestException:time.sleep(.1)
    raise RuntimeError('Apache did not start')
try:
    check('Apache launcher and PHP worker use an unprivileged account',os.geteuid()!=0)
    package=next((ROOT/'dist').glob('*-COMPLETE-UPLOAD.zip'))
    for rewrite,port in [(True,8094),(False,8095)]:
        label='rewrite' if rewrite else 'no-rewrite';work=RUNTIME/label;web=work/'web';work.mkdir(parents=True,exist_ok=True)
        with zipfile.ZipFile(package) as z:
            z.extractall(web);z.extractall(web/'subfolder');expected={n:z.read(n) for n in z.namelist()}
        # Exact readable, root-owned unused scaffold fixture; PHP must select external storage.
        subprocess.run(['sudo','chown','root:root',str(web/'storage')],check=True)
        before=(web/'storage').stat().st_uid
        # mod_autoindex matches real shared hosts: with Options -Indexes it produces the 403 a bare directory must get.
        modules=['mpm_prefork','authz_core','authz_host','dir','mime','alias','env','headers','autoindex']+(['rewrite'] if rewrite else [])
        conf=f'''ServerRoot "{work}"
ServerName localhost
Listen 127.0.0.1:{port}
PidFile "{work/'httpd.pid'}"
ErrorLog "{QA/('apache-'+label+'.log')}"
LogLevel warn
User {getpass.getuser()}
Group {grp.getgrgid(os.getgid()).gr_name}
DocumentRoot "{web}"
TypesConfig /etc/mime.types
'''+''.join('LoadModule '+m+'_module /usr/lib/apache2/modules/mod_'+m+'.so\n' for m in modules)+'''
LoadModule php_module /usr/lib/apache2/modules/libphp8.3.so
PHPIniDir /etc/php/8.3/apache2
<FilesMatch "\\.php$">
  SetHandler application/x-httpd-php
</FilesMatch>
'''+f'''<Directory "{web}">
  AllowOverride All
  Require all granted
</Directory>
'''
        config=work/'httpd.conf';config.write_text(conf);proc=subprocess.Popen(['/usr/sbin/apache2','-f',str(config),'-DFOREGROUND'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,start_new_session=True)
        base=f'http://127.0.0.1:{port}'
        try:
            r=wait(base);check(label+' real Apache serves one-page setup','Apache/' in r.headers.get('Server','') and r.status_code==200 and 'id="install-form"' in r.text)
            support=web/'install/views/script.php';saved=support.read_bytes();support.unlink();r=requests.get(base+'/install.php')
            check(label+' missing setup support gives useful guarded fallback',r.status_code==503 and 'Setup files are missing' in r.text and 'install/views/script.php' in r.text);support.write_bytes(saved)
            for suffix,prefix in [('',('apr_' if rewrite else 'apn_')),('/subfolder',('apsr_' if rewrite else 'apsn_'))]:
                url=base+suffix;root=web/suffix.lstrip('/');session=requests.Session();r=session.get(url+'/install.php')
                fields={'csrf':csrf(r),'action':'install','db_host':os.environ['CWP_TEST_HOST'],'db_port':os.environ['CWP_TEST_PORT'],'db_name':'cwp_test','db_user':os.environ['CWP_TEST_USER'],'db_password':os.environ['CWP_TEST_PASSWORD'],'db_prefix':prefix,'owner_name':'Apache fixture owner','owner_email':'apache@example.invalid','owner_password':'Synthetic Apache owner passphrase','owner_confirm':'Synthetic Apache owner passphrase','site_url':url,'confirm_install':'1','seed':'1','preview':'1'}
                r=session.post(url+'/install.php',data=fields,timeout=90)
                (QA/('apache-'+label+('-subfolder' if suffix else '-root')+'-install.json')).write_text(r.text)
                data=r.json();check(label+suffix+' actual COMPLETE installation succeeds',r.status_code==200 and data['ok'])
                check(label+suffix+' PHP verifies create read append rename delete in private store',all(c['ok'] for c in data['checks']) and 'storage' in data and not str(data['storage']).startswith(str(web)))
                check(label+suffix+' staging uses the configured private store',(Path(data['storage'])/'upload/.htaccess').read_text()=='Require all denied\n')
                check(label+suffix+' all executable installer support is removed',not (root/'install.php').exists() and not list((root/'install').rglob('*.php')))
                check(label+suffix+' installed internal setup cannot be reused',session.get(url+'/install/actions/deploy.php').status_code in [403,404])
                for route in ['/','/admin/index.php?view=login','/portal/index.php','/robots.php','/sitemap.php','/api/robots.php']:
                    rr=session.get(url+route);check(label+suffix+' canonical route '+route,rr.status_code==200 and 'We could not complete this request' not in rr.text)
                cookies=[c for c in session.cookies if c.name.startswith('cwp_')];check(label+suffix+' shared session cookie uses site scope',len(cookies)==1 and cookies[0].path==(suffix+'/') and cookies[0].has_nonstandard_attr('HttpOnly'))
                for n in ['images/branding/logo.png','images/icons/favicon.png','favicon.ico','images/backgrounds/hero.webp','images/portfolio/project-restaurant.webp','assets/css/site.css','assets/css/premium.css','assets/js/site.js','assets/js/activity.js']:
                    rr=session.get(url+'/'+n);check(label+suffix+' exact public asset '+n,rr.status_code==200 and rr.content==expected[n])
                for n in ['includes/bootstrap.php','app/staff/accounts.php','pages/home/body.php','database/schema/base.php','config/example.php','admin/controllers/router.php','admin/views/auth.php','portal/controllers/router.php','api/handlers/inquiry.php','media/serve.php','webhooks/authorize-net/receive.php','config.php','includes/','images/','assets/js/','storage/','upload/']:
                    check(label+suffix+' direct private/internal/listing denial '+n,session.get(url+'/'+n).status_code==403)
                for area in ['storage','upload']:
                    for filename,body in [('probe.php','<?php echo "EXECUTABLE-UPLOAD-MUST-NOT-RUN";'),('private.txt','PRIVATE-MUST-NOT-LEAK')]:
                        source=work/('fixture-'+filename);source.write_text(body)
                        if os.access(root/area,os.W_OK):shutil.copyfile(source,root/area/filename)
                        else:subprocess.run(['sudo','install','-m','0644',str(source),str(root/area/filename)],check=True)
                    for n in ['probe.php','private.txt']:
                        rr=session.get(url+'/'+area+'/'+n);check(label+suffix+' protected runtime denies '+area+'/'+n,rr.status_code==403 and 'MUST-NOT' not in rr.text)
                if rewrite:check(label+suffix+' optional crawler rewrite',session.get(url+'/robots.txt').status_code==200 and 'User-agent:' in session.get(url+'/robots.txt').text)
                else:check(label+suffix+' navigation independent of rewrite',session.get(url+'/index.php?page=work').status_code==200 and session.get(url+'/robots.php').status_code==200)
            check(label+' unused root-owned scaffold remains untouched',(web/'storage').stat().st_uid==before==0)
        finally:
            proc.terminate()
            try:proc.wait(timeout=10)
            except subprocess.TimeoutExpired:proc.kill();proc.wait()
finally:
    QA.mkdir(exist_ok=True);(QA/'apache-results.json').write_text(json.dumps({'passed':sum(c['passed'] for c in checks),'checks':checks},indent=2)+'\n')
print('PASS:',len(checks),'Apache protection/root/subfolder/installer checks')
