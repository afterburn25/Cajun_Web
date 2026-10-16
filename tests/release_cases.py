"""Additional saved-state, package-upgrade and real-browser release acceptance."""
import json, os, subprocess, shutil, time, zipfile, re
from pathlib import Path
from urllib.parse import urljoin
import requests
from playwright.sync_api import expect
from release_version import VERSION

def db(sql, params=()):
    result=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'sql':sql,'params':params}),text=True,capture_output=True,check=True)
    return json.loads(result.stdout)

def chosen_form(session,url,action,field,value,changes):
    response=session.get(url)
    for form in soup(response).select('form'):
        a=form.select_one('[name=action]'); f=form.select_one('[name='+field+']')
        if a and a.get('value')==action and f and f.get('value')==str(value):
            data=form_data(form); data.update(changes)
            return session.post(urljoin(url,form.get('action') or url),data=data)
    raise AssertionError('No matching '+action+' form for '+str(value))

def run_http(env):
    globals().update({key:env[key] for key in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php','admin','alice','bob']})
    check('PHP acceptance worker is not root',os.geteuid()!=0)
    published=db("SELECT value FROM ci_settings WHERE name='site_published'")[0]['value']
    db("UPDATE ci_settings SET value='1' WHERE name='site_published'")
    sitemap=requests.get(BASE+'/existing/api/sitemap.php')
    check('Published sitemap includes growth routes and portfolio',sitemap.status_code==200 and all('page='+route in sitemap.text for route in ['estimate','website-review','consultation','compare-wix','compare-squarespace']) and 'slug=river-and-oak' in sitemap.text)
    db("UPDATE ci_settings SET value='0' WHERE name='site_published'")
    check('Unpublished sitemap excludes preview content','<loc>' not in requests.get(BASE+'/existing/api/sitemap.php').text)
    db("UPDATE ci_settings SET value=? WHERE name='site_published'",[published])
    # Captured mail proves only local submission acceptance, not external delivery.
    for key,value in [('email_mode','mail'),('mail_from','studio@example.invalid'),('notification_email','owner@example.invalid')]:
        db('UPDATE ci_settings SET value=? WHERE name=?',[value,key])
    for kind,extra in [('website-review',{'current_url':'https://example.invalid/fixture'}),('consultation',{'preferred_date':'2026-12-01','timezone':'America/Chicago','period':'Afternoon'})]:
        session=requests.Session();url=BASE+'/existing/index.php?page='+kind
        data=form_data(soup(session.get(url)).select_one('[data-growth-form]'));data.update(name='Release fixture prospect',email='prospect@example.invalid',message='Synthetic workflow request for the release acceptance.',consent='1',**extra)
        time.sleep(2.1);r=session.post(url,data=data,headers={'Accept':'application/json'})
        check('Saved HTTP '+kind+' submission',r.status_code==200 and r.json()['ok'])
        record=db('SELECT service,message,notification_status FROM ci_leads WHERE reference=?',[r.json()['result']['reference']])[0]
        check('Database and local mail capture '+kind,record['notification_status']=='accepted' and (QA/'mail-capture.txt').exists())
        if kind=='consultation':check('Consultation is still explicitly unconfirmed','not a confirmed booking' in r.json()['result']['message'])
    db("UPDATE ci_settings SET value='disabled' WHERE name='email_mode'")
    content=BASE+'/existing/admin/index.php?view=studio-content'
    r=chosen_form(admin,content,'studio_content','setting_name','growth_intro',{'value':'Synthetic release acceptance introduction.'})
    check('Admin introduction saved in database',db("SELECT value FROM ci_settings WHERE name='growth_intro'")[0]['value']=='Synthetic release acceptance introduction.')
    check('Saved introduction rendered on homepage','Synthetic release acceptance introduction.' in requests.get(BASE+'/existing/').text)
    stale=form_data(form_for(admin.get(content),'studio_content'))
    chosen_form(admin,content,'studio_content','setting_name','growth_intro',{'value':'Newer synthetic introduction.'})
    stale['value']='Stale write must not replace it'; r=admin.post(content,data=stale)
    check('HTTP stale content save rejected','Another session changed' in r.text and db("SELECT value FROM ci_settings WHERE name='growth_intro'")[0]['value']=='Newer synthetic introduction.')
    history=BASE+'/existing/admin/index.php?view=studio-history'
    revision=db("SELECT id FROM ci_studio_revisions WHERE setting_name='growth_intro' ORDER BY id DESC LIMIT 1")[0]['id']
    r=chosen_form(admin,history,'studio_restore_content','id',revision,{'confirm':'1'})
    check('Deliberate HTTP restore persists as history',db("SELECT value FROM ci_settings WHERE name='growth_intro'")[0]['value']=='Synthetic release acceptance introduction.')
    pipeline=BASE+'/existing/admin/index.php?view=studio-pipeline'
    chosen_form(admin,pipeline,'studio_pipeline','id',1,{'status':'contacted','notes':'Saved pipeline note','follow_up':'2026-12-01'})
    check('Pipeline stage and notes persisted',db('SELECT status,notes FROM ci_leads WHERE id=1')[0]=={'status':'contacted','notes':'Saved pipeline note'})
    submit(admin,BASE+'/existing/admin/index.php?view=lead&id=1','lead_save',{'status':'qualified','notes':'Legacy lead editor works'})
    check('Seven-status legacy lead editor regression',db('SELECT status FROM ci_leads WHERE id=1')[0]['status']=='qualified')
    prices=BASE+'/existing/admin/index.php?view=studio-prices'
    p=form_data(form_for(admin.get(prices),'studio_prices'));p.pop('enabled',None);admin.post(prices,data=p)
    prospect=requests.Session();url=BASE+'/existing/index.php?page=estimate';r=prospect.get(url);calc=form_data(soup(r).select_one('[data-growth-form]'));calc.update(action='calculate',pages='7')
    r=prospect.post(url,data=calc,headers={'Accept':'application/json'})
    check('Admin pricing-disabled state uses consultation',r.json()['estimate']['mode']=='consultation')
    p=form_data(form_for(admin.get(prices),'studio_prices'));p.update(enabled='1',business_min='1000',business_max='1500',page_min='50',page_max='100');admin.post(prices,data=p)
    check('Price rules saved by administration',json.loads(db("SELECT value FROM ci_settings WHERE name='growth_prices'")[0]['value'])['business']==[100000,150000])
    bad=dict(calc,pages='-2');r=prospect.post(url,data=bad,headers={'Accept':'application/json'})
    check('Invalid HTTP estimator values rejected',r.status_code==422 and not r.json()['ok'])
    job=BASE+'/existing/admin/index.php?view=studio-job&id=1'
    submit(admin,job,'studio_job',{'title':'Synthetic reviewed project','stage':'review','milestones':'Discovery complete; review pending.'})
    check('Project milestones and stage saved',db('SELECT stage,milestones FROM ci_studio_jobs WHERE id=1')[0]=={'stage':'review','milestones':'Discovery complete; review pending.'})
    invoice=db("SELECT id FROM ci_studio_invoices WHERE title='HTTP manual invoice'")[0]['id']
    payload=form_data(form_for(admin.get(job),'studio_paid'));payload.update(invoice_id=str(invoice),paid_note='Synthetic receipt 123',confirm='1')
    attack=bob.post(BASE+'/existing/portal/index.php?job=2',data=dict(payload,csrf=token(bob.get(BASE+'/existing/portal/index.php?job=2'))))
    check('Client cannot mark invoices paid',db('SELECT status FROM ci_studio_invoices WHERE id=?',[invoice])[0]['status']=='issued')
    r=admin.post(job,data=payload)
    check('Manual receipt sets paid state and reference',db('SELECT status,paid_note FROM ci_studio_invoices WHERE id=?',[invoice])[0]=={'status':'paid','paid_note':'Synthetic receipt 123'})
    r=admin.post(job,data=payload);check('Duplicate manual receipt rejected','already recorded as paid' in r.text)
    check('Client payment status reflects saved receipt','Receipt recorded by the studio' in alice.get(BASE+'/existing/portal/index.php?job=1').text)
    before=db('SELECT COUNT(*) AS n FROM ci_studio_files')[0]['n']
    png=(ROOT/'site/images/icons/favicon.png').read_bytes()
    for filename,contents in [('looks-valid.php',png),('polyglot.png',png+b'<?php echo 123; ?>')]:
        r=submit(alice,BASE+'/existing/portal/index.php?job=1','upload',files={'document':(filename,contents,'image/png')})
        check('Disguised executable rejected: '+filename,'without executable code' in r.text and db('SELECT COUNT(*) AS n FROM ci_studio_files')[0]['n']==before)
    file=db('SELECT id,stored_name FROM ci_studio_files LIMIT 1')[0]
    original=file['stored_name'];db('UPDATE ci_studio_files SET stored_name=? WHERE id=?',['../../config.php',file['id']])
    check('Private download rejects stored path traversal',alice.get(BASE+'/existing/portal/index.php?file='+file['id']).status_code==500)
    db('UPDATE ci_studio_files SET stored_name=? WHERE id=?',[original,file['id']])
    # A populated, exact historical source fixture is updated with the actual UPDATE ZIP.
    upgrade=WEB/'upgrade';shutil.copytree(ROOT/'test-runtime/legacy',upgrade)
    subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-upgrade'}),text=True,capture_output=True,check=True)
    shutil.copyfile(ROOT/'test-runtime/upgrade-config.php',upgrade/'config.php')
    (upgrade/'.installed.php').write_text('<?php exit; ?>original guard');(upgrade/'unrelated.txt').write_text('Keep this unrelated file')
    preserved={p:p.read_bytes() for p in [upgrade/'config.php',upgrade/'.installed.php',upgrade/'unrelated.txt',ROOT/'test-runtime/upgrade-private/media/retained.php']}
    rows_before={t:db('SELECT * FROM upgrade_'+t) for t in ['settings','users','leads','projects']}
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(upgrade)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),upgrade)
    check('Actual update ZIP preserves config locks media and unrelated files',all(p.read_bytes()==data for p,data in preserved.items()))
    up=requests.Session();r=submit(up,BASE+'/upgrade/admin/index.php?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    check('Original owner can sign in after file update','Sales pipeline' in r.text)
    r=submit(up,BASE+'/upgrade/admin/index.php?view=studio-upgrade','studio_migrate',{'confirm':'1'})
    check('Supported admin database upgrade completes','Database update completed' in r.text)
    for table in ['leads','projects']:check('Upgrade retains populated '+table,db('SELECT * FROM upgrade_'+table)==rows_before[table])
    after={r['name']:r['value'] for r in db('SELECT * FROM upgrade_settings')}
    check('Upgrade preserves all original settings',all(after[r['name']]==r['value'] for r in rows_before['settings']))
    user=db('SELECT * FROM upgrade_users')[0]; old=rows_before['users'][0]
    check('Upgrade preserves original credentials and identity',all(user[k]==old[k] for k in old if k!='last_login'))
    submit(up,BASE+'/upgrade/admin/index.php?view=studio-upgrade','studio_migrate',{'confirm':'1'})
    check('HTTP upgrade can safely repeat',db("SELECT value FROM upgrade_settings WHERE name='studio_schema_version'")[0]['value']=='2')
    check('Upgraded site never reruns fresh setup',requests.get(BASE+'/upgrade/install.php').status_code==404)
    # Private invitation is copied from the real admin response; no customer mail.
    clients=BASE+'/existing/admin/index.php?view=studio-clients'
    cid=db("SELECT id FROM ci_studio_clients WHERE email='newclient@example.invalid'")[0]['id']
    r=chosen_form(admin,clients,'studio_invite','id',cid,{})
    invite=next(a['href'] for a in soup(r).select('a[href]') if 'portal/index.php?invite=' in a['href'])
    submit(admin,clients,'studio_job',{'client_id':str(cid),'title':'Browser fixture project','stage':'design','summary':'Synthetic client project','milestones':'Review the fixture'})
    jid=db('SELECT id FROM ci_studio_jobs WHERE client_id=?',[cid])[0]['id']
    submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id='+str(jid),'studio_publish',{'kind':'proposal','title':'Browser fixture proposal','body':'Synthetic scope for browser review'})
    # Configure distinct synthetic capture images using the real GD/media form.
    media=[]
    for filename in ['favicon.png','logo.png','emblem.png']:
        with (ROOT/'site'/('images/icons/' if filename=='favicon.png' else 'images/branding/')/filename).open('rb') as stream:submit(admin,BASE+'/existing/admin/index.php?view=studio-portfolio','studio_media',files={'image':('fixture-'+filename,stream,'image/png')})
        media.append(db('SELECT id FROM ci_media WHERE original_name=? ORDER BY created_at DESC LIMIT 1',['fixture-'+filename])[0]['id'])
    chosen_form(admin,BASE+'/existing/admin/index.php?view=studio-portfolio','studio_portfolio','id',1,{'desktop':media[0],'mobile':media[1],'before':media[2],'caption':'Synthetic acceptance captures only; not client redesign evidence.'})
    return {'invite':invite,'client_id':cid,'job_id':jid,'clients_url':clients}

def installer_failures(env):
    globals().update({key:env[key] for key in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php']})
    check('Filesystem acceptance runs without root',os.geteuid()!=0)
    missing=WEB/'missing';blocked=WEB/'blocked'
    for dest in [missing,blocked]:
        dest.mkdir()
        with zipfile.ZipFile(next((ROOT/'dist').glob('*-COMPLETE-UPLOAD.zip'))) as z:z.extractall(dest)
    (missing/'app/marketing/growth.php').unlink()
    r=requests.get(BASE+'/missing/install.php')
    check('Missing dependency leaves the full one-page form visible',len(soup(r).select('#install-form'))==1 and 'app/marketing/growth.php' in r.text and 'name="db_host"' in r.text and 'name="owner_password"' in r.text)
    session=requests.Session();r=session.get(BASE+'/blocked/install.php');blocked.chmod(0o555)
    try:
        result=session.post(BASE+'/blocked/install.php',data={'csrf':token(r),'action':'checks'},headers={'Accept':'application/json'}).json()
        check('Unprivileged write denial is reported honestly',not result['ok'] and not (blocked/'config.php').exists())
        check('Storage failure leaves setup fields visible',len(soup(session.get(BASE+'/blocked/install.php')).select('#install-form'))==1)
    finally:blocked.chmod(0o755)
    # Run without extensions against otherwise complete bytes. The form remains usable.
    noext=subprocess.Popen([php,'-n','-S','127.0.0.1:8093','-t',str(blocked)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:r=requests.get('http://127.0.0.1:8093/install.php',timeout=1);break
            except requests.RequestException:time.sleep(.1)
        check('Missing PHP extensions do not hide configuration form',r.status_code==200 and len(soup(r).select('#install-form'))==1)
        no=requests.Session();r=no.get('http://127.0.0.1:8093/install.php');result=no.post('http://127.0.0.1:8093/install.php',data={'csrf':token(r),'action':'checks'}).json()
        check('Missing PDO MySQL/GD prevents installation',not result['ok'] and any(not c['ok'] for c in result['checks']))
    finally:noext.terminate();noext.wait(timeout=10)

def run_browser(env,browser,fixture):
    # Real network responses and DOM assertions; no request stubs or CSP bypass.
    context=browser.new_context(viewport={'width':320,'height':800},has_touch=True,reduced_motion='reduce')
    page=context.new_page();errors=[];failed=[]
    page.on('pageerror',lambda e:errors.append({'text':str(e),'url':'pageerror'}))
    page.on('console',lambda m:errors.append({'text':m.text,'url':m.location.get('url','')}) if m.type=='error' else None)
    page.on('requestfailed',lambda r:failed.append({'url':r.url,'failure':r.failure,'status':r.response().status if r.response() else None}))
    for route in ['','index.php?page=compare-squarespace','index.php?page=work','index.php?page=faq','index.php?page=consultation']:
        page.goto(BASE+'/existing/'+route,wait_until='networkidle')
        check('Narrow mobile layout '+(route or 'home'),page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    page.goto(BASE+'/existing/index.php?page=project&slug=river-and-oak',wait_until='networkidle')
    page.get_by_role('button',name='Mobile',exact=True).tap();check('Touch switches portfolio device',page.get_by_role('button',name='Mobile',exact=True).get_attribute('aria-pressed')=='true')
    slider=page.locator('[data-compare-slider]');slider.focus();slider.press('ArrowRight');check('Keyboard changes before-after slider',slider.input_value()=='51')
    check('Portfolio fixture is honestly labeled','DESIGN CONCEPT' in page.content() and 'Synthetic acceptance captures only' in page.content())
    page.evaluate('window.scrollTo(0,0)');page.screenshot(path=str(QA/'Portfolio-Narrow-Mobile.png'),full_page=True)
    for kind in ['website-review','consultation']:
        page.goto(BASE+'/existing/index.php?page='+kind)
        page.locator('[name=name]').fill('Browser fixture prospect');page.locator('[name=email]').fill('browser@example.invalid');page.locator('[name=message]').fill('Synthetic browser request verifying the real submission destination.');page.locator('[name=consent]').check()
        if kind=='website-review':page.locator('[name=current_url]').fill('https://example.invalid/browser-fixture')
        else:page.locator('[name=preferred_date]').fill('2026-12-01')
        page.wait_for_timeout(2100);page.get_by_role('button',name='Send my request').click();expect(page.locator('[data-growth-success]')).to_be_visible()
        check('Browser '+kind+' persisted',bool(db('SELECT id FROM ci_leads WHERE email=? AND service=?',['browser@example.invalid','Website review' if kind=='website-review' else 'Consultation request'])))
    # Invalid form feedback preserves values and does not weaken the site's CSP.
    page.goto(BASE+'/existing/index.php?page=estimate');page.locator('[name=pages]').fill('-2');page.locator('[data-estimate]').click();expect(page.locator('[data-growth-error]')).to_be_visible();check('Browser estimator error preserves entered value',page.locator('[name=pages]').input_value()=='-2')
    page.goto(fixture['invite']);page.locator('[name=password]').fill('Synthetic browser client passphrase');page.locator('[name=confirm_password]').fill('Synthetic browser client passphrase');page.get_by_role('button',name='Set password').click();expect(page.locator('[name=email]')).to_be_visible()
    check('Browser invitation activation consumed token',db('SELECT invite_hash FROM ci_studio_clients WHERE id=?',[fixture['client_id']])[0]['invite_hash'] is None)
    page.locator('[name=email]').fill('newclient@example.invalid');page.locator('[name=password]').fill('Synthetic browser client passphrase');page.locator('form').filter(has=page.locator('[name=action][value=login]')).locator('button').click();expect(page.get_by_role('link',name='Open project')).to_be_visible();page.get_by_role('link',name='Open project').click()
    form=page.locator('form').filter(has=page.locator('[name=action][value=message]'));form.locator('[name=body]').fill('A browser-sent client message.');form.locator('button').click()
    check('Browser client message saved',bool(db('SELECT id FROM ci_studio_messages WHERE job_id=? AND body=?',[fixture['job_id'],'A browser-sent client message.'])))
    form=page.locator('form').filter(has=page.locator('[name=action][value=decision]'));form.locator('[name=choice]').select_option('changes');form.locator('[name=feedback]').fill('Clarify the synthetic proposal scope.');form.locator('[name=confirm]').check();form.locator('button').click()
    expect(page.get_by_text('Your decision was recorded against that version.',exact=True)).to_be_visible()
    page.wait_for_load_state('networkidle');page.evaluate('document.fonts.ready')
    check('Browser version-specific change request saved',db('SELECT decision FROM ci_studio_versions WHERE job_id=?',[fixture['job_id']])[0]['decision']=='changes')
    page.evaluate('window.scrollTo(0,0)');page.screenshot(path=str(QA/'Client-Workspace-Narrow.png'),full_page=True)
    layout=page.evaluate('({viewport:innerWidth,width:document.documentElement.scrollWidth,overflow:[...document.querySelectorAll("body *")].filter(e=>e.getBoundingClientRect().right>innerWidth+1).map(e=>({tag:e.tagName,classes:e.className,right:e.getBoundingClientRect().right})).slice(0,30)})')
    (QA/'client-workspace-layout.json').write_text(json.dumps(layout,indent=2),encoding='utf-8')
    check('Narrow client workspace fits',layout['width']<=layout['viewport']+1)
    chosen_form(admin,fixture['clients_url'],'studio_invite','id',fixture['client_id'],{})
    check('Pending invitation exists before account revocation',db('SELECT invite_hash FROM ci_studio_clients WHERE id=?',[fixture['client_id']])[0]['invite_hash'] is not None)
    chosen_form(admin,fixture['clients_url'],'studio_client','id',fixture['client_id'],{'name':'HTTP client','email':'newclient@example.invalid','active':None})
    page.reload();expect(page.locator('[name=email]')).to_be_visible();check('Client change revokes real browser session',True)
    check('Client update revokes outstanding setup token',db('SELECT invite_hash FROM ci_studio_clients WHERE id=?',[fixture['client_id']])[0]['invite_hash'] is None)
    # A deliberate 422 validation response may generate a Chromium resource-error line.
    unexpected=[e for e in errors if not (e['text'].startswith('Failed to load resource: the server responded with a status of 422') and e['url']==BASE+'/existing/index.php?page=estimate')]
    # Chromium reports the completed bodyless 204 poll as ERR_ABORTED.
    # Keep full diagnostics; tolerate only this exact confirmed response outcome.
    unexpected_failed=[r for r in failed if not ((r['url'].startswith(BASE+'/existing/api/activity.php?audience=') or r['url']==BASE+'/existing/api/mail-notices.php') and r['failure']=='net::ERR_ABORTED' and r['status']==204)]
    (QA/'browser-diagnostics.json').write_text(json.dumps({'console_and_page_errors':errors,'unexpected':unexpected,'failed_requests':failed,'unexpected_failed_requests':unexpected_failed},indent=2))
    check('Extended browser has no unexpected console or page errors',not unexpected);check('Extended browser has no unexpected failed network requests',not unexpected_failed)
    context.close()
    # Regression for the original inquiry form, whose named action input also shadows form.action.
    contact=browser.new_context();p=contact.new_page();p.goto(BASE+'/existing/index.php?page=contact')
    form=p.locator('[data-inquiry]');form.locator('[name=name]').fill('Browser contact fixture');form.locator('[name=email]').fill('contact-browser@example.invalid');form.locator('[name=service]').select_option('Website redesign');form.locator('[name=budget]').select_option('Help me estimate');form.locator('[name=message]').fill('Synthetic original contact form destination regression.');form.locator('[name=consent]').check();p.wait_for_timeout(2100);form.locator('button[type=submit]').click();expect(form.locator('.form-result')).to_be_visible()
    check('Original inquiry browser submission persisted',bool(db('SELECT id FROM ci_leads WHERE email=?',['contact-browser@example.invalid'])));contact.close()
    # Install another actual extracted COMPLETE package entirely through the browser.
    setup=WEB/'browser-setup';setup.mkdir()
    with zipfile.ZipFile(next((ROOT/'dist').glob('*COMPLETE-UPLOAD.zip'))) as package:package.extractall(setup)
    install=browser.new_context(viewport={'width':1440,'height':1000});p=install.new_page();p.goto(BASE+'/browser-setup/install.php',wait_until='networkidle')
    check('Browser installer shows the current source build',('ONE-PAGE INSTALLER · '+VERSION) in p.locator('.build').inner_text())
    check('Browser installer loads both original brand images',p.locator('.topbar img,.intro-sign img').count()==2 and p.locator('.topbar img,.intro-sign img').evaluate_all('items=>items.every(e=>e.complete && e.naturalWidth>0)'))
    values={'db_host':os.getenv('CWP_TEST_HOST','127.0.0.1'),'db_port':os.getenv('CWP_TEST_PORT','3306'),'db_name':'cwp_test','db_user':os.getenv('CWP_TEST_USER','cwp_test'),'db_password':os.getenv('CWP_TEST_PASSWORD','local-ci-only'),'db_prefix':'browser_','owner_name':'Browser owner','owner_email':'browser-owner@example.invalid','owner_password':'Synthetic browser owner passphrase','owner_confirm':'Deliberate mismatch passphrase','site_url':BASE+'/browser-setup'}
    for name,value in values.items():p.locator('[name='+name+']').fill(value)
    p.locator('[name=confirm_install]').check();p.locator('#install-button').click();expect(p.locator('#setup-error')).to_contain_text('passwords do not match');expect(p.locator('#install-form')).to_be_visible()
    check('Browser setup validation preserves entered fields',p.locator('[name=owner_email]').input_value()==values['owner_email'] and not (setup/'config.php').exists())
    p.locator('[name=owner_confirm]').fill(values['owner_password']);p.screenshot(path=str(QA/'Installer-Desktop.png'),full_page=True);p.set_viewport_size({'width':390,'height':844});check('One-page installer fits mobile',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));p.screenshot(path=str(QA/'Installer-Mobile.png'),full_page=True)
    p.locator('#install-button').click();expect(p.locator('#completion')).to_be_visible(timeout=90000)
    check('Browser single-form install creates native database and removes setup',(setup/'config.php').is_file() and not (setup/'install.php').exists() and bool(db('SELECT id FROM browser_users WHERE email=?',[values['owner_email']])))
    p.screenshot(path=str(QA/'Installer-Complete.png'),full_page=True);install.close()
    # Owner controls also run in Chromium, with saved database evidence.
    owner=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=owner.new_page();owner_errors=[];owner_failed=[]
    p.on('pageerror',lambda e:owner_errors.append(str(e)));p.on('console',lambda m:owner_errors.append(m.text) if m.type=='error' else None);p.on('requestfailed',lambda r:owner_failed.append({'url':r.url,'failure':r.failure,'status':r.response().status if r.response() else None}))
    p.goto(BASE+'/existing/admin/index.php?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill('Synthetic owner passphrase 2026');p.locator('form button').first.click();p.goto(BASE+'/existing/admin/index.php?view=studio-job&id=1');form=p.locator('form').filter(has=p.locator('[name=action][value=studio_job]'));form.locator('[name=milestones]').fill('Browser-verified milestone update.');form.get_by_role('button',name='Save project').click();expect(form.locator('[name=milestones]')).to_have_value('Browser-verified milestone update.');check('Browser admin change persisted',db('SELECT milestones FROM ci_studio_jobs WHERE id=1')[0]['milestones']=='Browser-verified milestone update.');p.screenshot(path=str(QA/'Admin-Workspace-Desktop.png'),full_page=True);p.set_viewport_size({'width':390,'height':844});check('Admin mobile workspace fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));p.screenshot(path=str(QA/'Admin-Workspace-Mobile.png'),full_page=True)
    p.get_by_role('button',name='Toggle administration navigation').click();expect(p.locator('.sidebar-toggle')).to_have_attribute('aria-expanded','true');p.keyboard.press('Escape');expect(p.locator('.sidebar-toggle')).to_have_attribute('aria-expanded','false');check('Admin mobile navigation toggles and closes by keyboard',True)
    p.set_viewport_size({'width':1440,'height':1000});p.goto(BASE+'/existing/admin/index.php?view=studio-content');form=p.locator('form').filter(has=p.locator('[name=setting_name][value=growth_intro]'));form.locator('[name=value]').fill('Browser-saved studio introduction.');form.locator('button').click();expect(form.locator('[name=value]')).to_have_value('Browser-saved studio introduction.');check('Browser content edit persisted',db("SELECT value FROM ci_settings WHERE name='growth_intro'")[0]['value']=='Browser-saved studio introduction.')
    p.goto(BASE+'/existing/admin/index.php?view=studio-prices');p.locator('[name=business_min]').fill('1000.00');p.locator('[name=enabled]').uncheck();p.get_by_role('button',name='Save price rules').click();expect(p.locator('[name=enabled]')).not_to_be_checked();check('Browser price-disabled state persisted',json.loads(db("SELECT value FROM ci_settings WHERE name='growth_prices'")[0]['value'])['enabled'] is False)
    public=owner.new_page();public.goto(BASE+'/existing/index.php?page=estimate');public.locator('[data-estimate]').click();expect(public.locator('[data-estimate-label]')).to_contain_text('scope your project together');check('Browser unpublished pricing offers consultation',True);public.close();p.locator('[name=enabled]').check();p.get_by_role('button',name='Save price rules').click();expect(p.locator('[name=enabled]')).to_be_checked()
    p.goto(BASE+'/existing/admin/index.php?view=studio-pipeline');form=p.locator('form').filter(has=p.locator('[name=action][value=studio_pipeline]')).filter(has=p.locator('[name=id][value="1"]'));form.locator('[name=status]').select_option('proposal');form.locator('[name=notes]').fill('Browser saved pipeline note.');form.locator('button').click();expect(form.locator('[name=notes]')).to_have_value('Browser saved pipeline note.');check('Browser pipeline edit persisted',db('SELECT status,notes FROM ci_leads WHERE id=1')[0]=={'status':'proposal','notes':'Browser saved pipeline note.'})
    p.goto(BASE+'/existing/admin/index.php?view=studio-clients');form=p.locator('form').filter(has=p.locator('[name=action][value=studio_client]')).filter(has=p.locator('[name=id][value="0"]'));form.locator('[name=name]').fill('Browser-created synthetic client');form.locator('[name=email]').fill('admin-browser-client@example.invalid');form.locator('button').click();expect(p.locator('[name=email][value="admin-browser-client@example.invalid"]')).to_be_visible();check('Browser admin client create persisted',bool(db('SELECT id FROM ci_studio_clients WHERE email=?',['admin-browser-client@example.invalid'])))
    p.goto(BASE+'/existing/admin/index.php?view=studio-portfolio');before=db('SELECT COUNT(*) AS n FROM ci_media')[0]['n'];p.locator('[name=image]').set_input_files(str(ROOT/'site/images/icons/favicon.png'));p.get_by_role('button',name='Upload capture').click();expect(p.locator('.notice.success')).to_be_visible();check('Browser admin GD media upload persisted',db('SELECT COUNT(*) AS n FROM ci_media')[0]['n']==before+1)
    owner_unexpected=[r for r in owner_failed if not ((r['url'].startswith(BASE+'/existing/api/activity.php?audience=') or r['url']==BASE+'/existing/api/mail-notices.php') and r['failure']=='net::ERR_ABORTED' and r['status']==204)]
    (QA/'owner-browser-network.json').write_text(json.dumps({'failed_requests':owner_failed,'unexpected_failed_requests':owner_unexpected},indent=2))
    check('Admin browser has no console errors or unexpected failed requests',not owner_errors and not owner_unexpected);owner.close()
    # Public forms remain functional with JavaScript disabled.
    plain=browser.new_context(java_script_enabled=False);p=plain.new_page();p.goto(BASE+'/existing/index.php?page=estimate');p.locator('[name=pages]').fill('7');p.locator('[data-estimate]').click();expect(p.locator('[data-estimate-label]')).to_contain_text('$1,100.00');check('No-JavaScript estimator computes on server',True);plain.close()
    # Reuse the freshly installed fixture at the document root on another loopback port.
    root_config=WEB/'fresh/config.php';original=root_config.read_bytes();root_config.write_bytes(original.replace((BASE+'/fresh').encode(),b'http://127.0.0.1:8092'))
    server=subprocess.Popen([php,'-S','127.0.0.1:8092','-t',str(WEB/'fresh')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:r=requests.get('http://127.0.0.1:8092/',timeout=1);break
            except requests.RequestException:time.sleep(.1)
        root_context=browser.new_context(viewport={'width':390,'height':844});p=root_context.new_page();p.goto('http://127.0.0.1:8092/',wait_until='networkidle')
        check('Root-domain links use the configured root',p.locator('a[href="http://127.0.0.1:8092/index.php?page=services"]').count()>0)
        check('Root-domain favicon loads',requests.get('http://127.0.0.1:8092/images/icons/favicon.png').content==(ROOT/'site/images/icons/favicon.png').read_bytes());p.screenshot(path=str(QA/'Fresh-Root-Mobile.png'),full_page=True);root_context.close()
    finally:server.terminate();server.wait(timeout=10);root_config.write_bytes(original)
