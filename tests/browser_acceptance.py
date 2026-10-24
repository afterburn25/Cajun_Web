#!/usr/bin/env python3
"""Real HTTP tests against a PHP server and native MySQL fixtures. Not shipping code."""
import os,json,shutil,subprocess,time,hashlib,zipfile,re
from pathlib import Path
from urllib.parse import urlparse,parse_qs,urljoin
import requests
from bs4 import BeautifulSoup
import release_cases
from release_cases import db
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8091';WEB=ROOT/'test-runtime/web';QA=ROOT/'qa';QA.mkdir(exist_ok=True)
checks=[]
def check(name,condition):
    checks.append({'name':name,'passed':bool(condition)})
    if not condition:raise AssertionError(name)
def soup(r):return BeautifulSoup(r.text,'html.parser')
def token(r):
    n=soup(r).select_one('input[name=csrf]') or soup(r).select_one('meta[name=csrf-token]')
    if not n:raise AssertionError('No CSRF in '+r.url+' status '+str(r.status_code))
    return n.get('value',n.get('content'))
def form_data(form):
    data={}
    for n in form.select('input[name],textarea[name],select[name]'):
        if n.get('type') in ('file','submit','button'):continue
        if n.get('type') in ('checkbox','radio') and not n.has_attr('checked'):continue
        if n.name=='textarea':v=n.text
        elif n.name=='select':
            option=n.select_one('option[selected]') or n.select_one('option');v=option.get('value',option.text) if option else ''
        else:v=n.get('value','')
        data[n['name']]=v
    return data
def form_for(r,action):
    for f in soup(r).select('form'):
        a=f.select_one('input[name=action]')
        if a and a.get('value')==action:return f
    raise AssertionError('Form missing '+action+' at '+r.url)
def submit(s,url,action,overrides=None,files=None):
    r=s.get(url,timeout=15);f=form_for(r,action);data=form_data(f);data.update(overrides or {})
    return s.post(urljoin(url,f.get('action') or url),data=data,files=files,timeout=25)
def login(email,password,portal=False):
    s=requests.Session();url=BASE+'/existing/'+('portal/index.php' if portal else 'admin/index.php?view=login')
    r=submit(s,url,'login',{'email':email,'password':password})
    check('HTTP '+('client' if portal else 'admin')+' login '+email, r.status_code==200 and ('Welcome, ' in r.text if portal else 'Sales pipeline' in r.text))
    return s
WEB.mkdir(parents=True,exist_ok=True)
for name in ['existing','fresh']:
    dest=WEB/name
    if dest.exists():shutil.rmtree(dest)
    dest.mkdir(parents=True)
    packages=list((ROOT/'dist').glob('CajunWebPro-*-COMPLETE-UPLOAD.zip'));assert len(packages)==1,'Build exactly one package'
    with zipfile.ZipFile(packages[0]) as package:package.extractall(dest)
shutil.copyfile(ROOT/'test-runtime/config-fixture.php',WEB/'existing/config.php')
from mail_inbox_http_server import InboxServer
inbox_server=InboxServer(ROOT)
from provider_http_server import ProviderServer
provider_server=ProviderServer(ROOT)
from mail_submission_server import SubmissionServer
submission_server=SubmissionServer(ROOT)
php=os.environ.get('PHP_BINARY') or shutil.which('php8.3') or shutil.which('php');log=open(QA/'http-server.log','w');proc=subprocess.Popen([php,'-d','auto_prepend_file='+str(ROOT/'tests/mail_inbox_http_connector.php'),'-d','sendmail_path=python3 '+str(ROOT/'tests/mail_capture.py'),'-S','127.0.0.1:8091','-t',str(WEB)],stdout=log,stderr=log)
release_cases.php=php;release_cases.ROOT=ROOT
db=release_cases.db
error=None
try:
    for _ in range(60):
        try:
            if requests.get(BASE+'/existing/',timeout=1).status_code:break
        except requests.RequestException:time.sleep(.2)
    routes=['','index.php?page=services','index.php?page=work','index.php?page=work&cat=Construction','index.php?page=about','index.php?page=process','index.php?page=contact','index.php?page=why-custom','index.php?page=compare-wix','index.php?page=compare-squarespace','index.php?page=switch','index.php?page=packages','index.php?page=faq','index.php?page=industries','index.php?page=estimate','index.php?page=website-review','index.php?page=consultation']
    for path in routes:
        r=requests.get(BASE+'/existing/'+path,timeout=15);check('Public route '+(path or 'home'),r.status_code==200 and 'CajunWebPro' in r.text and 'We could not complete this request' not in r.text)
    r=requests.get(BASE+'/existing/index.php?page=compare-wix',timeout=15)
    check('Wix comparison renders registry-sourced claims',r.status_code==200 and 'Wix Studio offers responsive design tools' in r.text and 'Hosting documentation' in r.text and 're-verified' not in r.text)
    r=requests.get(BASE+'/existing/index.php?page=compare-squarespace',timeout=15)
    check('Squarespace comparison renders registry-sourced claims',r.status_code==200 and 'code-injection features depend on the plan' in r.text and 'Export documentation' in r.text and 're-verified' not in r.text)
    for path in ['images/icons/favicon.png','favicon.ico','assets/css/growth.css','assets/js/growth.js','images/branding/logo.png']:
        r=requests.get(BASE+'/existing/'+path,timeout=15);check('Asset bytes '+path,r.status_code==200 and hashlib.sha256(r.content).digest()==hashlib.sha256((ROOT/'site'/path).read_bytes()).digest())
    for path in ['includes/bootstrap.php','includes/security/csrf.php','includes/foundation/estimator/PriceRule.php','app/marketing/estimator.php','app/staff/accounts.php','config/example.php','database/schema/base.php','admin/controllers/router.php','admin/views/auth.php','portal/views/workspace.php','pages/home/body.php','includes/foundation/access/AccessContext.php','install/actions/deploy.php']:
        check('PHP guard denies direct internal entry '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    r=requests.get(BASE+'/fresh/admin/index.php',allow_redirects=False)
    check('Uninstalled nested entry redirects to actual site-root setup',r.status_code==302 and r.headers['Location']=='/fresh/install.php')
    r=requests.post(BASE+'/existing/admin/index.php?view=security',data={'action':'account_save','marker':'keep-method'},headers={'Host':'untrusted.example.invalid'},allow_redirects=False)
    check('Transport enforcement retains nested canonical path query and POST method',r.status_code==307 and r.headers['Location']==BASE+'/existing/admin/index.php?view=security')
    r=requests.get(BASE+'/existing/install.php');check('Installed-site guard blocks setup',r.status_code==403)
    doc=soup(requests.get(BASE+'/existing/index.php?page=work&cat=Construction',timeout=15))
    cards={c['data-category']:c.has_attr('hidden') for c in doc.select('.portfolio-grid .project-card')}
    check('Server filter marks non-matching cards hidden',cards.get('Construction') is False and cards.get('Hospitality') and cards.get('Wellness'))
    active=doc.select_one('.filters a[data-filter="Construction"]');check('Filter link marks active category',active is not None and active.get('aria-current')=='true')
    doc=soup(requests.get(BASE+'/existing/index.php?page=work&cat=Unknown-Category',timeout=15));empty=doc.select_one('[data-portfolio-empty]')
    check('Unknown category shows honest empty state',empty is not None and not empty.has_attr('hidden'))
    r=requests.get(BASE+'/existing/index.php?page=project&slug=magnolia-built',timeout=15);check('Published case study resolves',r.status_code==200 and 'Magnolia Built' in r.text)
    r=requests.get(BASE+'/existing/index.php?page=project&slug=no-such-project',timeout=15);check('Unknown case study returns 404',r.status_code==404)
    doc=soup(requests.get(BASE+'/existing/index.php?page=compare-wix',timeout=15));reg=[li.get_text(' ',strip=True) for li in doc.select('.growth-source-list li')]
    check('Comparison source registry lists reviewed scope per source',len(reg)==4 and all('Reviewed 2026-' in t and '·' in t for t in reg))
    check('Comparison registry links primary documentation',all(a.get('href','').startswith('https://') for a in doc.select('.growth-source-list a')))
    s=requests.Session();r=s.get(BASE+'/existing/index.php?page=estimate');data=form_data(soup(r).select_one('[data-growth-form]'));data.update({'action':'calculate','project_type':'business','pages':'7','low':'1','high':'2'})
    r=s.post(BASE+'/existing/index.php?page=estimate',data=data,headers={'Accept':'application/json'});check('HTTP estimator ignores browser total',r.status_code==200 and r.json()['estimate']['low']==110000)
    data.update({'action':'growth_submit','name':'HTTP prospect','email':'http@example.invalid','message':'We would like a website with clear organized services.','consent':'1'})
    time.sleep(2.1);r=s.post(BASE+'/existing/index.php?page=estimate',data=data,headers={'Accept':'application/json'});check('HTTP lead persists',r.status_code==200 and r.json()['ok']);ref=r.json()['result']['reference']
    again=s.post(BASE+'/existing/index.php?page=estimate',data=data,headers={'Accept':'application/json'});check('HTTP duplicate has same saved reference',again.status_code==200 and again.json()['result']['reference']==ref)
    data['csrf']='wrong';r=s.post(BASE+'/existing/index.php?page=estimate',data=data,headers={'Accept':'application/json'});check('HTTP bad CSRF is rejected',r.status_code==422 and not r.json()['ok'])
    admin=login('owner@example.invalid','Synthetic owner passphrase 2026')
    for label,url,session in [('Public','/existing/',requests),('Admin','/existing/admin/index.php',admin),('Client','/existing/portal/index.php',requests),('Installer','/fresh/install.php',requests)]:
        head=soup(session.get(BASE+url));icons=head.select('link[rel=icon]')
        check(label+' head references original favicon',bool(icons) and all(urlparse(urljoin(BASE+url,n['href'])).path.endswith('/images/icons/favicon.png') and session.get(urljoin(BASE+url,n['href'])).content==(ROOT/'site/images/icons/favicon.png').read_bytes() for n in icons))
    for view in ['studio-clients','studio-job&id=1','studio-pipeline','studio-content','studio-prices','studio-history','studio-portfolio','studio-upgrade','lead&id=1']:
        r=admin.get(BASE+'/existing/admin/index.php?view='+view);check('Admin route '+view,r.status_code==200 and 'We could not complete this request' not in r.text and 'This request could not complete' not in r.text)
    r=submit(admin,BASE+'/existing/admin/index.php?view=studio-clients','studio_client',{'name':'HTTP client','email':'newclient@example.invalid'});check('Admin client create persists',r.status_code==200 and 'newclient@example.invalid' in r.text)
    r=submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_message',{'body':'A message recorded through HTTP.'});check('Staff message persists',r.status_code==200 and 'A message recorded through HTTP.' in r.text)
    r=submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_publish',{'kind':'design','title':'HTTP review version','body':'Review the precise version in this test.','preview_url':'https://example.org/preview'});check('Published review appears in admin',r.status_code==200 and 'HTTP review version' in r.text)
    alice=login('alice@example.invalid','Synthetic client passphrase 2026',True);bob=login('bob@example.invalid','Synthetic client passphrase 2026',True)
    r=alice.get(BASE+'/existing/portal/index.php?job=2');check('Other client project is not accessible',r.status_code==404 and 'Private project 2' not in r.text)
    r=alice.get(BASE+'/existing/portal/index.php?job=1');f=form_for(r,'decision');decision=form_data(f);decision.update({'choice':'approved','feedback':'Approved in HTTP test','confirm':'1'})
    r=alice.post(BASE+'/existing/portal/index.php?job=1',data=decision);check('Client approval persists',r.status_code==200 and 'Approved in HTTP test' in r.text)
    r=alice.post(BASE+'/existing/portal/index.php?job=1',data=decision);check('HTTP duplicate approval rejected','already decided or has been superseded' in r.text)
    with open(ROOT/'site/images/icons/favicon.png','rb') as f:r=submit(alice,BASE+'/existing/portal/index.php?job=1','upload',files={'document':('client-capture.png',f,'image/png')})
    links=[a['href'] for a in soup(r).select('a[href]') if 'portal/index.php?file=' in a['href']];check('Private upload saved',bool(links));fileurl=links[0]
    r=alice.get(fileurl);check('Owner downloads identical private file',r.status_code==200 and r.content==(ROOT/'site/images/icons/favicon.png').read_bytes() and r.headers.get('Content-Disposition','').startswith('attachment'))
    r=bob.get(fileurl);check('Another client cannot download private file',r.status_code==404)
    r=requests.get(fileurl);check('Anonymous private-file request denied',r.status_code==403)
    vrow=db("SELECT id FROM ci_studio_versions WHERE job_id=1 ORDER BY id DESC LIMIT 1")[0]
    with open(ROOT/'site/images/icons/favicon.png','rb') as f:r=submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_preview',{'version_id':str(vrow['id'])},files={'document':('version-preview.png',f,'image/png')})
    check('Protected preview attaches to a version',r.status_code==200 and 'Open protected preview' in r.text)
    r=alice.get(BASE+'/existing/portal/index.php?job=1');links=[a['href'] for a in soup(r).select('a[href]') if 'preview=' in a['href']];check('Client sees the protected preview link',bool(links))
    r=alice.get(links[0]);check('Owner opens the protected preview inline',r.status_code==200 and r.content==(ROOT/'site/images/icons/favicon.png').read_bytes() and r.headers.get('Content-Disposition','').startswith('inline') and r.headers.get('Content-Type','').startswith('image/png') and 'no-store' in r.headers.get('Cache-Control',''))
    r=bob.get(BASE+'/existing/portal/index.php?preview='+str(vrow['id']));check('Other client cannot open the protected preview',r.status_code==404)
    r=requests.get(BASE+'/existing/portal/index.php?preview='+str(vrow['id']));check('Anonymous preview request is refused',r.status_code==403)
    old_stage=db("SELECT stage FROM ci_studio_jobs WHERE id=1")[0]['stage'];new_stage='development' if old_stage!='development' else 'design'
    r=submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_job',{'stage':new_stage})
    check('Stage change records a durable event',db("SELECT from_stage,to_stage,actor_type FROM ci_studio_stage_events WHERE job_id=1 ORDER BY id DESC LIMIT 1")==[{'from_stage':old_stage,'to_stage':new_stage,'actor_type':'admin'}])
    check('Unchanged stage save records no event',db("SELECT COUNT(*) AS n FROM ci_studio_stage_events WHERE job_id=1")==[{'n':1}])
    r=alice.get(BASE+'/existing/portal/index.php?job=1');check('Client sees progress history',('Progress history' in r.text) and ('→' in r.text))
    with open(ROOT/'site/images/icons/favicon.png','rb') as f:r=submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_preview',{'version_id':'999999'},files={'document':('stray.png',f,'image/png')})
    check('Preview upload to a foreign version is refused','belongs to this project' in r.text)
    with open(ROOT/'site/images/icons/favicon.png','rb') as f:r=submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_preview',{'version_id':str(vrow['id'])},files={'document':('duplicate.png',f,'image/png')})
    check('A second preview on the same version is refused','could not be saved' in r.text)
    r=submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_invoice',{'title':'HTTP manual invoice','amount':'125.00','instructions':'Synthetic manual payment instructions; do not send money.'});check('Manual invoice created',r.status_code==200 and 'HTTP manual invoice' in r.text and '$125.00' in r.text)
    r=alice.get(BASE+'/existing/portal/index.php?job=1');check('Client sees own invoice','HTTP manual invoice' in r.text)
    r=bob.get(BASE+'/existing/portal/index.php?job=2');check('Other client does not see invoice','HTTP manual invoice' not in r.text)
    with open(ROOT/'site/images/icons/favicon.png','rb') as f:r=submit(admin,BASE+'/existing/admin/index.php?view=studio-portfolio','studio_media',files={'image':('public-capture.png',f,'image/png')})
    check('Real GD image upload returns successfully',r.status_code==200 and 'Saved.' in r.text and 'could not' not in r.text.lower())
    # Negative real HTTP cases: authorization must prevent writes and disclosures.
    r=bob.get(BASE+'/existing/portal/index.php?job=2');bad={'csrf':token(r),'action':'message','body':'Cross-client write must fail.'}
    r=bob.post(BASE+'/existing/portal/index.php?job=1',data=bad);check('Cross-client message POST denied',r.status_code==404)
    r=alice.get(BASE+'/existing/portal/index.php?job=1');check('Cross-client message not saved','Cross-client write must fail.' not in r.text)
    r=alice.post(BASE+'/existing/portal/index.php?job=1',data={'csrf':'invalid','action':'message','body':'CSRF write must fail.'});check('Portal CSRF rejected','session expired' in r.text.lower())
    r=alice.get(BASE+'/existing/portal/index.php?job=1');check('CSRF message not saved','CSRF write must fail.' not in r.text)
    f=form_for(r,'upload');bad=form_data(f)
    r=alice.post(BASE+'/existing/portal/index.php?job=1',data=bad,files={'document':('blocked.php',b'<?php echo 123; ?>','image/png')});check('Disguised executable upload rejected','Only PDF, PNG and JPEG' in r.text)
    r=requests.get(BASE+'/existing/admin/index.php?view=studio-clients',allow_redirects=False);check('Anonymous admin workspace redirected to login',r.status_code==303 and 'login' in r.headers.get('Location',''))
    r=bob.get(BASE+'/existing/admin/index.php?view=studio-clients',allow_redirects=False);check('Client session does not grant admin access',r.status_code==303 and 'login' in r.headers.get('Location',''))
    from release_cases import installer_failures
    installer_failures(globals())
    # Fresh installation is a real combined POST using native PDO MySQL and GD.
    fresh=requests.Session();r=fresh.get(BASE+'/fresh/install.php');check('Fresh setup shows one form',len(soup(r).select('#install-form'))==1 and 'Unlock your installation' not in r.text and 'data-back=' not in r.text)
    data=form_data(soup(r).select_one('#install-form'));data.update({'db_host':os.getenv('CWP_TEST_HOST','127.0.0.1'),'db_port':os.getenv('CWP_TEST_PORT','3306'),'db_name':'cwp_test','db_user':os.getenv('CWP_TEST_USER','cwp_test'),'db_password':os.getenv('CWP_TEST_PASSWORD','local-ci-only'),'db_prefix':'fresh_','owner_name':'Fresh owner','owner_email':'fresh@example.invalid','owner_password':'Fresh synthetic passphrase 2026','owner_confirm':'Fresh synthetic passphrase 2026','site_url':BASE+'/fresh','business_email':'','private_parent':'','confirm_install':'1','preview':'1'})
    r=fresh.post(BASE+'/fresh/install.php',data=data,headers={'Accept':'application/json'},timeout=90)
    try:result=r.json()
    except ValueError:raise AssertionError('Fresh install returned non-JSON: '+r.text[:500])
    (QA/'fresh-install-result.json').write_text(json.dumps(result,indent=2))
    check('Native one-request fresh installation succeeds',r.status_code==200 and result.get('ok'))
    check('Fresh configuration exists',(WEB/'fresh/config.php').is_file())
    check('Installer physically removed after successful setup',not (WEB/'fresh/install.php').exists())
    check('Installed homepage loads after cleanup',fresh.get(BASE+'/fresh/').status_code==200)
    from release_cases import run_http, run_browser
    release_fixture=run_http(globals())
    # Native browser traffic, not offline documents or mocked fetch.
    from playwright.sync_api import sync_playwright, expect
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True)
        context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
        page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        for name,path in [('Homepage',''),('Wix-Comparison','index.php?page=compare-wix'),('Planner','index.php?page=estimate'),('Portfolio','index.php?page=work')]:
            page.goto(BASE+'/existing/'+path,wait_until='networkidle');page.screenshot(path=str(QA/(name+'-Desktop.png')),full_page=True)
            check('Desktop no horizontal overflow '+name,page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            page.set_viewport_size({'width':390,'height':844});page.screenshot(path=str(QA/(name+'-Mobile.png')),full_page=True)
            check('Mobile no horizontal overflow '+name,page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));page.set_viewport_size({'width':1440,'height':1000})
        page.goto(BASE+'/existing/index.php?page=estimate');page.locator('[name=project_type]').select_option('business');page.locator('[name=pages]').fill('7');page.locator('[data-estimate]').click();expect(page.locator('[data-estimate-label]')).to_contain_text('$1,100.00')
        check('Browser estimator uses actual server response','$1,100.00' in page.locator('[data-estimate-label]').inner_text())
        page.goto(BASE+'/existing/index.php?page=work',wait_until='networkidle')
        page.locator('.filters a[data-filter="Construction"]').click()
        check('Browser filter link updates URL and grid','cat=Construction' in page.url and page.locator('.project-card:not([hidden])').count()==1)
        page.go_back();expect(page.locator('.project-card:not([hidden])')).to_have_count(3);check('Browser back restores full portfolio',page.locator('.project-card:not([hidden])').count()==3)
        from motion_cases import run as motion_checks
        motion_checks(globals(),browser)
        page.goto(BASE+'/existing/portal/index.php');page.locator('[name=email]').fill('alice@example.invalid');page.locator('[name=password]').fill('Synthetic client passphrase 2026');page.locator('button[type=submit],form button').first.click();page.wait_for_url('**/portal/index.php')
        expect(page.locator('h1')).to_contain_text('Welcome,')
        for width,height,label in [(1440,1000,'Desktop'),(390,844,'Mobile')]:
            page.set_viewport_size({'width':width,'height':height})
            check('Client project list '+label+' fits viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            page.screenshot(path=str(QA/('Client-Projects-'+label+'.png')),full_page=True)
        page.set_viewport_size({'width':1440,'height':1000});page.goto(BASE+'/existing/portal/index.php?job=1');page.screenshot(path=str(QA/'Client-Workspace-Desktop.png'),full_page=True)
        check('Browser private project renders','HTTP manual invoice' in page.content());check('No uncaught browser errors',not errors)
        with page.expect_download() as download:page.locator('a[href*="portal/index.php?file="]').first.click()
        check('Browser authorized download matches stored file',Path(download.value.path()).read_bytes()==(ROOT/'site/images/icons/favicon.png').read_bytes())
        page.get_by_role('button',name='Sign out').click();expect(page.locator('[name=email]')).to_be_visible();check('Browser client logout clears access',True)
        run_browser(globals(),browser,release_fixture)
        from staff_cases import run_browser as staff_browser
        staff_browser(globals(),browser)
        from support_cases import run_browser as support_browser
        support_browser(globals(),browser)
        from catalog_cases import run_browser as catalog_browser
        catalog_browser(globals(),browser)
        from provider_catalog_import_cases import run_browser as catalog_import_browser
        catalog_import_browser(globals(),browser)
        from payments_cases import run_browser as payments_browser
        payments_browser(globals(),browser)
        from attempts_cases import run_browser as attempts_browser
        attempts_browser(globals(),browser)
        from notifications_cases import run_browser as notifications_browser
        notifications_browser(globals(),browser)
        from ledger_cases import run_browser as ledger_browser
        ledger_browser(globals(),browser)
        from delivery_cases import run_browser as delivery_browser
        delivery_browser(globals(),browser)
        from backups_cases import run_browser as backups_browser
        backups_browser(globals(),browser)
        from activity_cases import run_browser as activity_browser
        activity_browser(globals(),browser)
        from notification_feed_cases import run_browser as notification_feed_browser
        notification_feed_browser(globals(),browser)

        from pipeline_cases import run_browser as pipeline_browser
        pipeline_browser(globals(),browser)
        from portfolio_cases import run_browser as portfolio_browser
        portfolio_browser(globals(),browser)
        from mail_templates_cases import run_browser as mail_templates_browser
        mail_templates_browser(globals(),browser)
        from mail_accounts_cases import run_browser as mail_accounts_browser
        mail_accounts_browser(globals(),browser)
        from reviews_cases import run_browser as reviews_browser
        reviews_browser(globals(),browser)
        from appointments_cases import run_browser as appointments_browser
        appointments_browser(globals(),browser)
        from mail_connections_cases import run_browser as connections_browser
        connections_browser(globals(),browser)
        from mail_inbox_cases import run_browser as inbox_browser
        inbox_browser(globals(),browser)
        from mail_message_cases import run_browser as message_browser
        message_browser(globals(),browser)
        from mail_notices_cases import run_browser as notices_browser
        notices_browser(globals(),browser)
        from provider_settings_cases import run_browser as provider_browser
        provider_browser(globals(),browser)
        from provider_operations_cases import run_browser as provider_operations_browser
        provider_operations_browser(globals(),browser)
        from provider_domain_search_cases import run_browser as domain_search_browser
        domain_search_browser(globals(),browser)
        from provider_mailbox_cases import run_browser as mailbox_creation_browser
        mailbox_creation_browser(globals(),browser)
        from provider_forwarding_cases import run_browser as forwarding_browser
        forwarding_browser(globals(),browser)

        from provider_registrar_cases import run_browser as registrar_browser
        registrar_browser(globals(),browser)
        from domain_renewals_cases import run_browser as domain_renewals_browser
        domain_renewals_browser(globals(),browser)

        from provider_cloud_sites_cases import run_browser as cloud_sites_browser
        cloud_sites_browser(globals(),browser)
        from service_observations_cases import run_browser as service_history_browser
        service_history_browser(globals(),browser)
        from mail_drafts_cases import run_browser as drafts_browser
        drafts_browser(globals(),browser)
        from mail_submissions_cases import run_browser as submissions_browser
        submissions_browser(globals(),browser)
        from mail_reply_cases import run_browser as reply_browser
        reply_browser(globals(),browser)
        from client_authority_cases import run_browser as client_authority_browser
        client_authority_browser(globals(),browser)
        from service_plan_cases import run_browser as service_plan_browser
        service_plan_browser(globals(),browser)
        from project_progress_cases import run_browser as progress_browser
        progress_browser(globals(),browser)
        from project_planning_cases import run_browser as planning_browser
        planning_browser(globals(),browser)
        from design_financial_cases import run_browser as financial_browser
        financial_browser(globals(),browser)
        from recurring_billing_cases import run_browser as recurring_browser
        recurring_browser(globals(),browser)
        from domain_contracts_cases import run_browser as domain_contracts_browser
        domain_contracts_browser(globals(),browser)
        from provider_cloud_providers_cases import run_browser as cloud_providers_browser
        cloud_providers_browser(globals(),browser)
        from provider_registration_periods_cases import run_browser as registration_periods_browser
        registration_periods_browser(globals(),browser)
        from provider_package_php_cases import run_browser as package_php_browser
        package_php_browser(globals(),browser)
        from domain_expiry_cases import run_browser as domain_expiry_browser
        domain_expiry_browser(globals(),browser)
        from provider_document_roots_cases import run_browser as document_roots_browser
        document_roots_browser(globals(),browser)
        from provider_package_force_ssl_cases import run_browser as package_force_ssl_browser
        package_force_ssl_browser(globals(),browser)
        from provider_directory_listing_cases import run_browser as directory_listing_browser
        directory_listing_browser(globals(),browser)
        from provider_force_ssl_controls_cases import run_browser as force_ssl_controls_browser
        force_ssl_controls_browser(globals(),browser)
        from domain_renewal_agreements_cases import run_browser as renewal_agreements_browser
        renewal_agreements_browser(globals(),browser)
        from provider_package_wordpress_installed_cases import run_browser as wordpress_installed_browser
        wordpress_installed_browser(globals(),browser)
        from domain_renewal_dispatch_cases import run_browser as renewal_dispatch_browser
        renewal_dispatch_browser(globals(),browser)
        from provider_directory_listing_controls_cases import run_browser as directory_controls_browser
        directory_controls_browser(globals(),browser)
        from stackcp_prerequisites_cases import run_browser as stackcp_prerequisites_browser
        stackcp_prerequisites_browser(globals(),browser)
        from domain_registration_drafts_cases import run_browser as registration_drafts_browser
        registration_drafts_browser(globals(),browser)
        from stackcp_access_cases import run_browser as stackcp_access_browser
        stackcp_access_browser(globals(),browser)
        from provider_password_reset_template_cases import run_browser as reset_template_browser
        reset_template_browser(globals(),browser)
        from provider_cloud_assignment_cases import run_browser as cloud_assignment_browser
        cloud_assignment_browser(globals(),browser)
        from license_release_cases import run_browser as license_release_browser
        license_release_browser(globals(),browser)
        from ticket_cases import run_browser as ticket_browser
        ticket_browser(globals(),browser)
        from provider_schedule_cases import run_browser as schedule_browser
        schedule_browser(globals(),browser)
        from contacts_cases import run_browser as contacts_browser
        contacts_browser(globals(),browser)

        from proposals_cases import run_browser as proposals_browser
        proposals_browser(globals(),browser)
        browser.close()
    log.flush()
    diagnostics=re.findall(r'^.*PHP (?:Warning|Fatal error|Parse error|Deprecated|Notice):.*$',(QA/'http-server.log').read_text(),re.M)
    (QA/'php-diagnostics.json').write_text(json.dumps(diagnostics,indent=2))
    check('Application server has no PHP runtime warnings or errors',not diagnostics)
except Exception as exc:
    error=repr(exc)
finally:
    proc.terminate();proc.wait(timeout=10);log.close();inbox_server.close();provider_server.close();submission_server.close()
    (QA/'http-results.json').write_text(json.dumps({'transport':'real loopback HTTP; PHP built-in server; native database; actual browser fetch','checks':checks,'passed':error is None,'failure':error},indent=2))
for item in checks:print(('PASS ' if item['passed'] else 'FAIL ')+item['name'])
if error:raise SystemExit(error)
print(f'HTTP/browser checks: {len(checks)} passed')
