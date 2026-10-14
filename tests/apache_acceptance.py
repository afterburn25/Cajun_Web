"""Apache 2.4 + mod_php, unprivileged worker, actual COMPLETE ZIP, rewrite on/off."""
from pathlib import Path
import getpass,grp,json,os,shutil,subprocess,time,zipfile
from release_version import VERSION,STAGE,RECOVERY,LOCK_RECOVERY
import requests
from bs4 import BeautifulSoup
ROOT=Path(__file__).resolve().parents[1];QA=ROOT/'qa';RUNTIME=ROOT/'test-runtime/apache';checks=[]
def check(name,value):
    checks.append({'name':name,'passed':bool(value)})
    if len(checks)%25==0 or not value:
        QA.mkdir(exist_ok=True)
        checkpoint=QA/'apache-progress.json.tmp'
        checkpoint.write_text(json.dumps({'complete':False,'scope':'Partial Apache progress; not final acceptance','checks':checks},indent=2)+'\n')
        checkpoint.replace(QA/'apache-progress.json')
        print('Apache progress:',len(checks),name,flush=True)
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
        print('Apache acceptance starting:',label,flush=True)
        with zipfile.ZipFile(package) as z:
            z.extractall(web);z.extractall(web/'subfolder');expected={n:z.read(n) for n in z.namelist()}
        # Exact readable, root-owned unused scaffold fixture; PHP must select external storage.
        subprocess.run(['sudo','chown','root:root',str(web/'storage')],check=True)
        before=(web/'storage').stat().st_uid
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
        # Enable the cache at PHP startup, not per request (which PHP forbids).
        ini=work/'php-conf';ini.mkdir();(ini/'99-acceptance.ini').write_text('opcache.enable=1\nopcache.validate_timestamps=0\nopcache.file_update_protection=0\n')
        env=dict(os.environ,PHP_INI_SCAN_DIR='/etc/php/8.3/apache2/conf.d:'+str(ini))
        config=work/'httpd.conf';config.write_text(conf);proc=subprocess.Popen(['/usr/sbin/apache2','-f',str(config),'-DFOREGROUND'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,start_new_session=True,env=env)
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
                for n in ['app/mail/submissions.php', 'app/mail/submission_dispatch.php', 'app/mail/submission_views.php', 'admin/controllers/mail_submissions.php', 'admin/views/mail_submissions.php', 'database/schema/mail_submissions_schema.php', 'database/migrations/mail_submissions_migrate.php', 'includes/foundation/mail-submission/SubmissionChannel.php', 'includes/foundation/mail-submission/vendor/phpmailer/src/SMTP.php', 'app/mail/drafts.php', 'admin/controllers/mail_drafts.php', 'admin/views/mail_drafts.php', 'database/schema/mail_drafts_schema.php', 'database/migrations/mail_drafts_migrate.php', 'includes/foundation/mail-compose/DraftContent.php', 'app/providers/settings.php', 'app/providers/credentials.php', 'app/providers/checks.php', 'admin/controllers/providers.php', 'admin/views/providers.php', 'database/schema/provider_settings_schema.php', 'database/migrations/provider_settings_migrate.php', 'includes/foundation/twentyi/FixedHttpTransport.php', 'includes/foundation/provider/ProviderGateway.php', 'app/mail/notices.php', 'admin/controllers/mail_notices.php', 'admin/views/mail_notices.php', 'api/handlers/mail_notices.php', 'database/schema/mail_notices_schema.php', 'database/migrations/mail_notices_migrate.php', 'app/mail/messages.php', 'admin/controllers/mail_message.php', 'admin/views/mail_message.php', 'database/schema/mail_messages_schema.php', 'database/migrations/mail_messages_migrate.php', 'includes/foundation/mail-receiving/MessageReader.php', 'includes/foundation/mail-receiving/BodyDecoder.php', 'includes/foundation/mail-receiving/BodyHtml.php', 'includes/foundation/mail-receiving/vendor/symfony/html-sanitizer/HtmlSanitizer.php', 'includes/foundation/mail-receiving/vendor/autoload.php', 'includes/foundation/mail-receiving/vendor/composer/installed.json', 'includes/foundation/mail-receiving/composer.lock', 'includes/bootstrap.php', 'app/staff/accounts.php', 'pages/home/body.php', 'database/schema/base.php', 'config/example.php', 'admin/controllers/router.php', 'admin/views/auth.php', 'portal/controllers/router.php', 'api/handlers/inquiry.php', 'media/serve.php', 'webhooks/authorize-net/receive.php', 'config.php', 'includes/', 'images/', 'assets/js/', 'storage/', 'upload/', 'app/clients/accounts.php', 'app/clients/identity.php', 'admin/views/studio/clients.php', 'portal/controllers/actions.php', 'app/hosting/records.php', 'app/hosting/operations.php', 'app/plans/views.php', 'admin/controllers/plans.php', 'admin/views/plans.php', 'portal/controllers/plans.php', 'portal/views/plans.php', 'app/plans/forms.php', 'includes/layout/plans.php', 'app/plans/documents.php', 'app/support/ticket_documents.php', 'app/support/ticket_forms.php', 'app/support/ticket_choices.php', 'app/support/ticket_notifications.php', 'includes/layout/tickets.php', 'includes/layout/ticket_activity.php', 'admin/controllers/tickets.php', 'admin/views/tickets.php', 'portal/controllers/tickets.php', 'portal/views/tickets.php', 'app/support/tickets.php', 'app/support/ticket_reads.php', 'app/support/ticket_views.php', 'database/schema/support_tickets_schema.php', 'database/migrations/support_tickets_migrate.php', 'includes/foundation/desk/TraceLink.php', 'includes/foundation/desk/TicketMessage.php', 'includes/foundation/desk/SupportTicket.php', 'includes/foundation/desk/ApprovalRequest.php', 'includes/foundation/desk/FulfillmentJob.php', 'app/plans/records.php', 'database/schema/service_plans_schema.php', 'database/migrations/service_plans_migrate.php', 'includes/foundation/service-plans/ServicePlan.php', 'includes/foundation/service-plans/PlanSubscription.php', 'database/schema/hosting_records_schema.php', 'database/schema/hosting_operations_schema.php', 'database/migrations/hosting_records_migrate.php', 'database/migrations/hosting_operations_migrate.php', 'includes/foundation/hosting/HostingAccount.php', 'includes/foundation/hosting/HostingJournal.php', 'includes/foundation/hosting/HostingPackage.php', 'includes/foundation/hosting/OperationResult.php', 'includes/foundation/hosting/PlanChange.php', 'includes/foundation/hosting/StackCpGrant.php', 'app/hosting/reconciliation.php', 'database/schema/hosting_reconciliation_schema.php', 'database/migrations/hosting_reconciliation_migrate.php']:
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
            # Real Apache protection during an interrupted old-layout file switch.
            # This fixture reuses the isolated fresh schema; the comprehensive HTTP
            # suite separately retains actual old databases and business records.
            legacy=web/'legacy';shutil.copytree(ROOT/'test-runtime/released-1.10.0',legacy)
            code="define('CWP_BOOT',true);$c=require $argv[1];$c['site_url']=$argv[3];file_put_contents($argv[2],'<?php return '.var_export($c,true).';');"
            php=shutil.which('php8.3') or shutil.which('php')
            subprocess.run([php,'-r',code,str(web/'config.php'),str(legacy/'config.php'),base+'/legacy'],check=True)
            shutil.copyfile(web/'.installed.php',legacy/'.installed.php')
            warm=requests.get(base+'/legacy/admin.php?view=login')
            check(label+' old privileged entry was served before migration',warm.status_code==200 and 'Sign in to manage' in warm.text)
            # Synthetic test-only diagnostic, never part of a distribution.
            probe=web/'cache-probe.php';probe.write_text("<?php echo json_encode(['enabled'=>opcache_get_status(false)['opcache_enabled']??false,'timestamps'=>ini_get('opcache.validate_timestamps'),'cached'=>opcache_is_script_cached(__DIR__.'/legacy/admin.php')]);")
            cache=requests.get(base+'/cache-probe.php').json();probe.unlink()
            check(label+' old entry is actually cached with timestamp validation disabled',cache['enabled'] and cache['timestamps']=='0' and cache['cached'])
            with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(legacy)
            failed=subprocess.run([php,str(ROOT/'tests/structure_worker.php'),str(legacy),'apply','5'],capture_output=True,text=True)
            (QA/('apache-'+label+'-interruption.log')).write_text('rc='+str(failed.returncode)+'\n'+failed.stdout+failed.stderr)
            check(label+' actual migration interrupts after journaled operation: '+failed.stderr.strip(),failed.returncode!=0 and 'Synthetic interruption' in failed.stderr)
            for route in ['admin.php','portal.php','index.php','assets/site.css']:
                check(label+' maintenance denies old application '+route,requests.get(base+'/legacy/'+route).status_code==403)
            session=requests.Session();url=base+'/legacy/'+STAGE+'/owner-update.php';r=session.get(url)
            check(label+' authenticated owner recovery page remains reachable in maintenance',r.status_code==200 and 'Current owner password' in r.text)
            check(label+' staged payload is denied independently of rewriting',requests.get(base+'/legacy/'+STAGE+'/site/admin/index.php').status_code==403)
            r=session.post(url,data={'csrf':csrf(r),'email':'apache@example.invalid','owner_password':'Synthetic Apache owner passphrase','backup_confirm':'1','action':'apply'},timeout=90)
            (QA/('apache-'+label+'-owner-update.html')).write_text(r.text)
            check(label+' owner resumes interrupted migration through actual Apache','<h2>Complete</h2>' in r.text and r.status_code==200)
            r=session.get(base+'/legacy/admin.php?view=security');doc=BeautifulSoup(r.text,'html.parser');form=next(f for f in doc.select('form') if f.select_one('[name=action][value=account_save]'))
            fields={n['name']:n.get('value','') for n in form.select('input[name]')};fields['current_password']='Incorrect fixture password'
            r=session.post(base+'/legacy/admin.php?view=security',data=fields,allow_redirects=False)
            check(label+' Apache legacy POST invokes canonical action with body and session',r.status_code==200 and 'current password' in r.text.lower() and 'Location' not in r.headers and ('STUDIO RELEASE '+VERSION) in r.text)
            check(label+' Apache upgraded canonical portal still serves',session.get(base+'/legacy/portal/index.php').status_code==200)
            from organized_update_cases import run as organized_update
            # Exercise the newly added baseline first; keep every historical baseline.
            for number,version in enumerate(['1.23.0','1.22.0','1.11.0','1.11.1','1.11.2','1.12.0','1.13.0','1.14.0','1.15.0','1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0']):
                organized_update(web,base,check,QA,php,label,('aor' if rewrite else 'aon')+str(number)+'_',version)
        finally:
            proc.terminate()
            try:proc.wait(timeout=10)
            except subprocess.TimeoutExpired:proc.kill();proc.wait()
finally:
    QA.mkdir(exist_ok=True);(QA/'apache-results.json').write_text(json.dumps({'passed':sum(c['passed'] for c in checks),'checks':checks},indent=2)+'\n')
print('PASS:',len(checks),'Apache protection/root/subfolder/installer checks')
