"""Additional saved-state, package-upgrade and real-browser release acceptance."""
import json, os, subprocess, shutil, time, zipfile, re
from pathlib import Path
from urllib.parse import urljoin
import requests
from playwright.sync_api import expect

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
    content=BASE+'/existing/admin.php?view=studio-content'
    r=chosen_form(admin,content,'studio_content','setting_name','growth_intro',{'value':'Synthetic release acceptance introduction.'})
    check('Admin introduction saved in database',db("SELECT value FROM ci_settings WHERE name='growth_intro'")[0]['value']=='Synthetic release acceptance introduction.')
    check('Saved introduction rendered on homepage','Synthetic release acceptance introduction.' in requests.get(BASE+'/existing/').text)
    stale=form_data(form_for(admin.get(content),'studio_content'))
    chosen_form(admin,content,'studio_content','setting_name','growth_intro',{'value':'Newer synthetic introduction.'})
    stale['value']='Stale write must not replace it'; r=admin.post(content,data=stale)
    check('HTTP stale content save rejected','Another session changed' in r.text and db("SELECT value FROM ci_settings WHERE name='growth_intro'")[0]['value']=='Newer synthetic introduction.')
    history=BASE+'/existing/admin.php?view=studio-history'
    revision=db("SELECT id FROM ci_studio_revisions WHERE setting_name='growth_intro' ORDER BY id DESC LIMIT 1")[0]['id']
    r=chosen_form(admin,history,'studio_restore_content','id',revision,{'confirm':'1'})
    check('Deliberate HTTP restore persists as history',db("SELECT value FROM ci_settings WHERE name='growth_intro'")[0]['value']=='Synthetic release acceptance introduction.')
    pipeline=BASE+'/existing/admin.php?view=studio-pipeline'
    chosen_form(admin,pipeline,'studio_pipeline','id',1,{'status':'proposal','notes':'Saved pipeline note','follow_up':'2026-12-01'})
    check('Pipeline stage and notes persisted',db('SELECT status,notes FROM ci_leads WHERE id=1')[0]=={'status':'proposal','notes':'Saved pipeline note'})
    submit(admin,BASE+'/existing/admin.php?view=lead&id=1','lead_save',{'status':'qualified','notes':'Legacy lead editor works'})
    check('Seven-status legacy lead editor regression',db('SELECT status FROM ci_leads WHERE id=1')[0]['status']=='qualified')
    prices=BASE+'/existing/admin.php?view=studio-prices'
    p=form_data(form_for(admin.get(prices),'studio_prices'));p.pop('enabled',None);admin.post(prices,data=p)
    prospect=requests.Session();url=BASE+'/existing/index.php?page=estimate';r=prospect.get(url);calc=form_data(soup(r).select_one('[data-growth-form]'));calc.update(action='calculate',pages='7')
    r=prospect.post(url,data=calc,headers={'Accept':'application/json'})
    check('Admin pricing-disabled state uses consultation',r.json()['estimate']['mode']=='consultation')
    p=form_data(form_for(admin.get(prices),'studio_prices'));p.update(enabled='1',business_min='1000',business_max='1500',page_min='50',page_max='100');admin.post(prices,data=p)
    check('Price rules saved by administration',json.loads(db("SELECT value FROM ci_settings WHERE name='growth_prices'")[0]['value'])['business']==[100000,150000])
    bad=dict(calc,pages='-2');r=prospect.post(url,data=bad,headers={'Accept':'application/json'})
    check('Invalid HTTP estimator values rejected',r.status_code==422 and not r.json()['ok'])
    job=BASE+'/existing/admin.php?view=studio-job&id=1'
    submit(admin,job,'studio_job',{'title':'Synthetic reviewed project','stage':'review','milestones':'Discovery complete; review pending.'})
    check('Project milestones and stage saved',db('SELECT stage,milestones FROM ci_studio_jobs WHERE id=1')[0]=={'stage':'review','milestones':'Discovery complete; review pending.'})
    invoice=db("SELECT id FROM ci_studio_invoices WHERE title='HTTP manual invoice'")[0]['id']
    payload=form_data(form_for(admin.get(job),'studio_paid'));payload.update(invoice_id=str(invoice),paid_note='Synthetic receipt 123',confirm='1')
    attack=bob.post(BASE+'/existing/portal.php?job=2',data=dict(payload,csrf=token(bob.get(BASE+'/existing/portal.php?job=2'))))
    check('Client cannot mark invoices paid',db('SELECT status FROM ci_studio_invoices WHERE id=?',[invoice])[0]['status']=='issued')
    r=admin.post(job,data=payload)
    check('Manual receipt sets paid state and reference',db('SELECT status,paid_note FROM ci_studio_invoices WHERE id=?',[invoice])[0]=={'status':'paid','paid_note':'Synthetic receipt 123'})
    r=admin.post(job,data=payload);check('Duplicate manual receipt rejected','already recorded as paid' in r.text)
    check('Client payment status reflects saved receipt','Receipt recorded by the studio' in alice.get(BASE+'/existing/portal.php?job=1').text)
    before=db('SELECT COUNT(*) AS n FROM ci_studio_files')[0]['n']
    png=(ROOT/'site/assets/favicon.png').read_bytes()
    for filename,contents in [('looks-valid.php',png),('polyglot.png',png+b'<?php echo 123; ?>')]:
        r=submit(alice,BASE+'/existing/portal.php?job=1','upload',files={'document':(filename,contents,'image/png')})
        check('Disguised executable rejected: '+filename,'without executable code' in r.text and db('SELECT COUNT(*) AS n FROM ci_studio_files')[0]['n']==before)
    file=db('SELECT id,stored_name FROM ci_studio_files LIMIT 1')[0]
    original=file['stored_name'];db('UPDATE ci_studio_files SET stored_name=? WHERE id=?',['../../config.php',file['id']])
    check('Private download rejects stored path traversal',alice.get(BASE+'/existing/portal.php?file='+file['id']).status_code==500)
    db('UPDATE ci_studio_files SET stored_name=? WHERE id=?',[original,file['id']])
    # A populated, exact historical source fixture is updated with the actual UPDATE ZIP.
    upgrade=WEB/'upgrade';shutil.copytree(ROOT/'test-runtime/legacy',upgrade)
    subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-upgrade'}),text=True,capture_output=True,check=True)
    shutil.copyfile(ROOT/'test-runtime/upgrade-config.php',upgrade/'config.php')
    (upgrade/'.installed.php').write_text('<?php exit; ?>original guard');(upgrade/'unrelated.txt').write_text('Keep this unrelated file')
    preserved={p:p.read_bytes() for p in [upgrade/'config.php',upgrade/'.installed.php',upgrade/'unrelated.txt',ROOT/'test-runtime/upgrade-private/media/retained.php']}
    rows_before={t:db('SELECT * FROM upgrade_'+t) for t in ['settings','users','leads','projects']}
    with zipfile.ZipFile(ROOT/'dist/CajunWebPro-1.1.0-UPDATE.zip') as z:z.extractall(upgrade)
    check('Actual update ZIP preserves config locks media and unrelated files',all(p.read_bytes()==data for p,data in preserved.items()))
    up=requests.Session();r=submit(up,BASE+'/upgrade/admin.php?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    check('Original owner can sign in after file update','Sales pipeline' in r.text)
    r=submit(up,BASE+'/upgrade/admin.php?view=studio-upgrade','studio_migrate',{'confirm':'1'})
    check('Supported admin database upgrade completes','Database update completed' in r.text)
    for table in ['leads','projects']:check('Upgrade retains populated '+table,db('SELECT * FROM upgrade_'+table)==rows_before[table])
    after={r['name']:r['value'] for r in db('SELECT * FROM upgrade_settings')}
    check('Upgrade preserves all original settings',all(after[r['name']]==r['value'] for r in rows_before['settings']))
    user=db('SELECT * FROM upgrade_users')[0]; old=rows_before['users'][0]
    check('Upgrade preserves original credentials and identity',all(user[k]==old[k] for k in old if k!='last_login'))
    submit(up,BASE+'/upgrade/admin.php?view=studio-upgrade','studio_migrate',{'confirm':'1'})
    check('HTTP upgrade can safely repeat',db("SELECT value FROM upgrade_settings WHERE name='studio_schema_version'")[0]['value']=='2')
    check('Upgraded site never reruns fresh setup',requests.get(BASE+'/upgrade/install.php').status_code==403)
    # Private invitation is copied from the real admin response; no customer mail.
    clients=BASE+'/existing/admin.php?view=studio-clients'
    cid=db("SELECT id FROM ci_studio_clients WHERE email='newclient@example.invalid'")[0]['id']
    r=chosen_form(admin,clients,'studio_invite','id',cid,{})
    invite=next(a['href'] for a in soup(r).select('a[href]') if 'portal.php?invite=' in a['href'])
    submit(admin,clients,'studio_job',{'client_id':str(cid),'title':'Browser fixture project','stage':'design','summary':'Synthetic client project','milestones':'Review the fixture'})
    jid=db('SELECT id FROM ci_studio_jobs WHERE client_id=?',[cid])[0]['id']
    submit(admin,BASE+'/existing/admin.php?view=studio-job&id='+str(jid),'studio_publish',{'kind':'proposal','title':'Browser fixture proposal','body':'Synthetic scope for browser review'})
    # Configure distinct synthetic capture images using the real GD/media form.
    media=[]
    for filename in ['favicon.png','logo.png','emblem.png']:
        with (ROOT/'site/assets'/filename).open('rb') as stream:submit(admin,BASE+'/existing/admin.php?view=studio-portfolio','studio_media',files={'image':('fixture-'+filename,stream,'image/png')})
        media.append(db('SELECT id FROM ci_media WHERE original_name=? ORDER BY created_at DESC LIMIT 1',['fixture-'+filename])[0]['id'])
    chosen_form(admin,BASE+'/existing/admin.php?view=studio-portfolio','studio_portfolio','id',1,{'desktop':media[0],'mobile':media[1],'before':media[2],'caption':'Synthetic acceptance captures only; not client redesign evidence.'})
    return {'invite':invite,'client_id':cid,'job_id':jid,'clients_url':clients}

def installer_failures(env):
    globals().update({key:env[key] for key in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php']})
    check('Filesystem acceptance runs without root',os.geteuid()!=0)
    missing=WEB/'missing';blocked=WEB/'blocked'
    for dest in [missing,blocked]:
        dest.mkdir()
        with zipfile.ZipFile(ROOT/'dist/CajunWebPro-1.1.0-COMPLETE-UPLOAD.zip') as z:z.extractall(dest)
    (missing/'app/growth.php').unlink()
    r=requests.get(BASE+'/missing/install.php')
    check('Missing dependency leaves the full one-page form visible',len(soup(r).select('#install-form'))==1 and 'app/growth.php' in r.text and 'name="db_host"' in r.text and 'name="owner_password"' in r.text)
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
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.on('console',lambda m:errors.append(m.text) if m.type=='error' else None)
    page.on('requestfailed',lambda r:failed.append(r.url))
    for route in ['','index.php?page=compare-squarespace','index.php?page=work','index.php?page=faq','index.php?page=consultation']:
        page.goto(BASE+'/existing/'+route,wait_until='networkidle')
        check('Narrow mobile layout '+(route or 'home'),page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    page.goto(BASE+'/existing/index.php?page=project&slug=river-and-oak',wait_until='networkidle')
    page.get_by_role('button',name='Mobile',exact=True).tap();check('Touch switches portfolio device',page.get_by_role('button',name='Mobile',exact=True).get_attribute('aria-pressed')=='true')
    slider=page.locator('[data-compare-slider]');slider.focus();slider.press('ArrowRight');check('Keyboard changes before-after slider',slider.input_value()=='51')
    check('Portfolio fixture is honestly labeled','DESIGN CONCEPT' in page.content() and 'Synthetic acceptance captures only' in page.content())
    page.screenshot(path=str(QA/'Portfolio-Narrow-Mobile.png'),full_page=True)
    # Invalid form feedback preserves values and does not weaken the site's CSP.
    page.goto(BASE+'/existing/index.php?page=estimate');page.locator('[name=pages]').fill('-2');page.locator('[data-estimate]').click();expect(page.locator('[data-growth-error]')).to_be_visible();check('Browser estimator error preserves entered value',page.locator('[name=pages]').input_value()=='-2')
    page.goto(fixture['invite']);page.locator('[name=password]').fill('Synthetic browser client passphrase');page.locator('[name=confirm_password]').fill('Synthetic browser client passphrase');page.get_by_role('button',name='Set password').click();expect(page.locator('[name=email]')).to_be_visible()
    check('Browser invitation activation consumed token',db('SELECT invite_hash FROM ci_studio_clients WHERE id=?',[fixture['client_id']])[0]['invite_hash'] is None)
    page.locator('[name=email]').fill('newclient@example.invalid');page.locator('[name=password]').fill('Synthetic browser client passphrase');page.get_by_role('button',name='Sign in',exact=True).click();expect(page.get_by_role('link',name='Open project')).to_be_visible();page.get_by_role('link',name='Open project').click()
    form=page.locator('form').filter(has=page.locator('[name=action][value=message]'));form.locator('[name=body]').fill('A browser-sent client message.');form.locator('button').click()
    check('Browser client message saved',bool(db('SELECT id FROM ci_studio_messages WHERE job_id=? AND body=?',[fixture['job_id'],'A browser-sent client message.'])))
    form=page.locator('form').filter(has=page.locator('[name=action][value=decision]'));form.locator('[name=choice]').select_option('changes');form.locator('[name=feedback]').fill('Clarify the synthetic proposal scope.');form.locator('[name=confirm]').check();form.locator('button').click()
    check('Browser version-specific change request saved',db('SELECT decision FROM ci_studio_versions WHERE job_id=?',[fixture['job_id']])[0]['decision']=='changes')
    check('Narrow client workspace fits',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));page.screenshot(path=str(QA/'Client-Workspace-Narrow.png'),full_page=True)
    chosen_form(admin,fixture['clients_url'],'studio_client','id',fixture['client_id'],{'name':'HTTP client','email':'newclient@example.invalid','active':'1'})
    page.reload();expect(page.locator('[name=email]')).to_be_visible();check('Client change revokes real browser session',True)
    check('Client update revokes outstanding setup token',db('SELECT invite_hash FROM ci_studio_clients WHERE id=?',[fixture['client_id']])[0]['invite_hash'] is None)
    check('Extended browser has no console or page errors',not errors);check('Extended browser has no failed network requests',not failed)
    context.close()
    # Owner controls also run in Chromium, with saved database evidence.
    owner=browser.new_context(viewport={'width':1440,'height':1000});p=owner.new_page();p.goto(BASE+'/existing/admin.php?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill('Synthetic owner passphrase 2026');p.locator('form button').first.click();p.goto(BASE+'/existing/admin.php?view=studio-job&id=1');form=p.locator('form').filter(has=p.locator('[name=action][value=studio_job]'));form.locator('[name=milestones]').fill('Browser-verified milestone update.');form.get_by_role('button',name='Save project').click();expect(form.locator('[name=milestones]')).to_have_value('Browser-verified milestone update.');check('Browser admin change persisted',db('SELECT milestones FROM ci_studio_jobs WHERE id=1')[0]['milestones']=='Browser-verified milestone update.');p.screenshot(path=str(QA/'Admin-Workspace-Desktop.png'),full_page=True);p.set_viewport_size({'width':390,'height':844});check('Admin mobile workspace fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));p.screenshot(path=str(QA/'Admin-Workspace-Mobile.png'),full_page=True);owner.close()
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
        check('Root-domain favicon loads',requests.get('http://127.0.0.1:8092/assets/favicon.png').content==(ROOT/'site/assets/favicon.png').read_bytes());p.screenshot(path=str(QA/'Fresh-Root-Mobile.png'),full_page=True);root_context.close()
    finally:server.terminate();server.wait(timeout=10);root_config.write_bytes(original)
