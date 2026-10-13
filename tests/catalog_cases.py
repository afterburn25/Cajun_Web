"""Actual retained-package upgrade, HTTP boundaries and Chromium catalog/invoice acceptance."""
import json,shutil,subprocess,zipfile
import requests
from playwright.sync_api import expect
from release_cases import db,chosen_form
from staff_cases import OWNER,STAFF

def upgrade():
    dest=WEB/'catalog-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.3.0',dest)
    subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-catalog-upgrade'}),text=True,capture_output=True,check=True)
    shutil.copyfile(ROOT/'test-runtime/catalog-upgrade-config.php',dest/'config.php');(dest/'.installed.php').write_text('<?php exit; ?>original guard');(dest/'unrelated.txt').write_text('Retain unrelated bytes')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/catalog-upgrade-private/media/retained.php']}
    tables=['users','settings','staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_invoices','leads','projects']
    before={t:db('SELECT * FROM catalogup_'+t) for t in tables}
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Actual 1.3.0 update preserves config locks private media and unrelated files',all(p.read_bytes()==v for p,v in preserved.items()))
    up=requests.Session();root=BASE+'/catalog-upgrade/admin/index.php'
    r=submit(up,root+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    check('Actual 1.3.0 owner signs in before catalog upgrade','Sales pipeline' in r.text)
    r=submit(up,root+'?view=catalog','catalog_migrate',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'})
    check('Actual 1.3.0 catalog migration completes','Service catalog enabled' in r.text)
    for t in tables[2:]:check('Actual 1.3.0 retains '+t,db('SELECT * FROM catalogup_'+t)==before[t])
    settings={r['name']:r['value'] for r in db('SELECT * FROM catalogup_settings')};check('Actual 1.3.0 retains all original settings',all(settings[r['name']]==r['value'] for r in before['settings']))
    users=db('SELECT * FROM catalogup_users');check('Actual 1.3.0 retains passwords IDs and auth versions',all(all(a[k]==b[k] for k in b if k!='last_login') for a,b in zip(users,before['users'])))
    check('Actual 1.3.0 publishes no automatic product or price',not db('SELECT * FROM catalogup_catalog_products'))
    check('Actual 1.3.0 installed guard remains',requests.get(BASE+'/catalog-upgrade/install.php').status_code==404)

def product_data(**changes):
    return dict({'sku':'HTTP-DESIGN','title':'HTTP design service','family':'web-design','status':'draft','term':'once','currency':'USD','amount':'125.10','setup':'10.00','cost':'42.37','provider_ref':'INTERNAL_PROVIDER_ONLY_1938','summary':'Approved HTTP design scope','features':'Responsive layout\nAgreed launch testing','terms':'One agreed project phase. No automatic renewal.','sort_order':'0'},**changes)

def preview(session,pid,revision=2,quantity=3):
    return submit(session,ADMIN+'?view=catalog-invoice&job_id=1','catalog_preview',{'product':str(pid)+':'+str(revision),'quantity':str(quantity),'instructions':'Synthetic manual payment instructions'})

def run_http(env):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php','admin','bob']})
    global ADMIN,CATALOG,PACKAGES
    ADMIN=BASE+'/existing/admin/index.php';CATALOG=ADMIN+'?view=catalog';PACKAGES=BASE+'/existing/index.php?page=packages'
    db('DELETE FROM ci_rate_limits');upgrade()
    check('Fresh setup initializes empty catalog',db("SELECT value FROM fresh_settings WHERE name='catalog_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_catalog_products'))
    r=submit(admin,CATALOG,'catalog_migrate',{'owner_password':'wrong','confirm':'1'});check('HTTP catalog setup reauthenticates owner','current password is not correct' in r.text)
    r=submit(admin,CATALOG,'catalog_migrate',{'owner_password':OWNER,'confirm':'1','csrf':'bad'});check('HTTP catalog setup rejects bad CSRF','session expired' in r.text.lower())
    r=submit(admin,CATALOG,'catalog_migrate',{'owner_password':OWNER,'confirm':'1'});check('HTTP owner enables catalog','Service catalog enabled' in r.text)
    original=db('SELECT * FROM ci_studio_invoices')
    r=submit(admin,CATALOG,'catalog_save',product_data(csrf='bad'));check('Catalog product CSRF denial','session expired' in r.text.lower() and not db('SELECT id FROM ci_catalog_products'))
    r=submit(admin,CATALOG,'catalog_save',product_data());pid=db('SELECT id FROM ci_catalog_products WHERE sku=?',['HTTP-DESIGN'])[0]['id'];edit=CATALOG+'&id='+str(pid)
    check('Draft product not in public packages','HTTP design service' not in requests.get(PACKAGES).text)
    r=submit(admin,edit,'catalog_save',product_data(status='published',family='hosting'))
    check('Provider product publication blocked','Provider products remain draft' in r.text and db('SELECT status FROM ci_catalog_products WHERE id=?',[pid])[0]['status']=='draft')
    r=submit(admin,edit,'catalog_save',product_data(status='published'));public=requests.get(PACKAGES)
    check('Published service price and setup reach public page','HTTP design service' in public.text and '$125.10' in public.text and '$10.00' in public.text)
    check('Public catalog cannot leak internal fields','INTERNAL_PROVIDER_ONLY_1938' not in public.text and '42.37' not in public.text and 'cost_cents' not in public.text)
    stale=form_data(form_for(admin.get(edit),'catalog_save'))
    r=preview(admin,pid);check('Invoice preview totals from server prices','Invoice total: $385.30 USD' in r.text)
    issue=form_data(form_for(r,'catalog_issue'));issue.update(confirm='1',amount='0.01',amount_cents='1',currency='EUR')
    count=db('SELECT COUNT(*) AS n FROM ci_studio_invoices')[0]['n'];target=ADMIN+'?view=catalog-invoice&job_id=1'
    r=admin.post(target,data=dict(issue,csrf='bad'));check('Catalog issuance CSRF denial preserves ledger',db('SELECT COUNT(*) AS n FROM ci_studio_invoices')[0]['n']==count)
    r=admin.post(target,data=issue);inv=db('SELECT i.* FROM ci_studio_invoices i JOIN ci_catalog_invoice_snapshots s ON s.invoice_id=i.id ORDER BY i.id DESC LIMIT 1')[0]
    check('Posted browser amount and currency cannot alter issued invoice',inv['amount_cents']==38530 and inv['currency']=='USD' and inv['status']=='issued')
    r=admin.post(target,data=issue);check('HTTP repeated issue creates one invoice',db('SELECT COUNT(*) AS n FROM ci_studio_invoices')[0]['n']==count+1)
    r=admin.post(target,data=dict(issue,quantity='4'));check('HTTP changed duplicate payload denied','already used with different details' in r.text)
    alice=requests.Session();submit(alice,BASE+'/existing/portal/index.php','login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    r=alice.get(BASE+'/existing/portal/index.php?job=1');check('Client sees owned immutable catalog invoice','Invoice total: $385.30 USD' in r.text and 'HTTP-DESIGN' in r.text and 'INTERNAL_PROVIDER_ONLY_1938' not in r.text)
    check('Other client cannot view catalog invoice',bob.get(BASE+'/existing/portal/index.php?job=1').status_code==404 and inv['reference'] not in bob.get(BASE+'/existing/portal/index.php?job=2').text)
    r=bob.post(CATALOG,data={'csrf':token(bob.get(BASE+'/existing/portal/index.php')),'action':'catalog_save',**product_data()},allow_redirects=False);check('Client cannot create catalog products',r.status_code==303 and 'login' in r.headers.get('Location',''))
    pending=form_data(form_for(preview(admin,pid),'catalog_issue'));pending['confirm']='1'
    submit(admin,edit,'catalog_save',product_data(status='published',amount='200.00',title='Revised HTTP service'))
    r=admin.post(edit,data=stale);check('HTTP stale product edit cannot overwrite current price','changed' in r.text and db('SELECT amount_cents FROM ci_catalog_products WHERE id=?',[pid])[0]['amount_cents']==20000)
    r=admin.post(target,data=pending);check('Price changed after preview requires fresh review','price changed' in r.text and db('SELECT COUNT(*) AS n FROM ci_studio_invoices')[0]['n']==count+1)
    r=alice.get(BASE+'/existing/portal/index.php?job=1');check('Issued invoice keeps old service text and price after edit','HTTP design service' in r.text and 'Invoice total: $385.30 USD' in r.text)
    check('Public page shows changed approved price','$200.00' in requests.get(PACKAGES).text)
    # Catalog-only and client-only staff cannot combine capabilities by forging a POST.
    uid=db('SELECT id FROM ci_users WHERE email=?',['client-staff@example.invalid'])[0]['id']
    chosen_form(admin,ADMIN+'?view=staff','staff_save','id',uid,{'permissions[]':['catalog.manage'],'owner_password':OWNER})
    member=requests.Session();r=submit(member,ADMIN+'?view=login','login',{'email':'client-staff@example.invalid','password':STAFF})
    check('Catalog-only staff lands in catalog','view=catalog' in r.url and member.get(target).status_code==403)
    r=member.post(CATALOG,data={'csrf':token(member.get(CATALOG)),'action':'catalog_issue',**{k:v for k,v in issue.items() if k!='csrf'}})
    check('Catalog-only forged invoice denied',db('SELECT COUNT(*) AS n FROM ci_studio_invoices')[0]['n']==count+1 and 'does not have access' in r.text)
    chosen_form(admin,ADMIN+'?view=staff','staff_save','id',uid,{'permissions[]':['clients.manage'],'owner_password':OWNER})
    member=requests.Session();submit(member,ADMIN+'?view=login','login',{'email':'client-staff@example.invalid','password':STAFF})
    check('Client-only staff cannot view internal catalog',member.get(edit).status_code==403)
    r=member.get(ADMIN+'?view=studio-job&id=1');r=member.post(r.url,data={'csrf':token(r),'action':'studio_paid','job_id':'1','invoice_id':inv['id'],'paid_note':'Forged catalog receipt','confirm':'1'})
    check('Client-only staff cannot bypass catalog receipt permission',db('SELECT status FROM ci_studio_invoices WHERE id=?',[inv['id']])[0]['status']=='issued' and 'does not have access' in r.text)
    # Strict audit failure injection for HTTP issuance; returned error is safe and ledger is unchanged.
    pending=form_data(form_for(preview(admin,pid,3,2),'catalog_issue'));pending['confirm']='1'
    db("CREATE TRIGGER ci_catalog_audit_failure BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action LIKE 'catalog.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic catalog audit failure'; END IF; END")
    try:
        r=admin.post(target,data=pending);check('HTTP audit failure rolls back invoice and hides SQL details','could not be saved' in r.text and 'SQLSTATE' not in r.text and db('SELECT COUNT(*) AS n FROM ci_studio_invoices')[0]['n']==count+1)
    finally:db('DROP TRIGGER ci_catalog_audit_failure')
    r=admin.post(target,data=pending);second=db('SELECT MAX(invoice_id) AS id FROM ci_catalog_invoice_snapshots')[0]['id']
    chosen_form(admin,ADMIN+'?view=studio-job&id=1','catalog_void','invoice_id',second,{'reason':'Synthetic duplicate work canceled','confirm':'1'})
    check('HTTP void retains invoice snapshot',db('SELECT status FROM ci_studio_invoices WHERE id=?',[second])==[{'status':'void'}] and bool(db('SELECT id FROM ci_catalog_invoice_snapshots WHERE invoice_id=?',[second])))
    chosen_form(admin,ADMIN+'?view=studio-job&id=1','studio_paid','invoice_id',inv['id'],{'paid_note':'Synthetic independently verified reference','confirm':'1'})
    check('HTTP verified manual receipt updates catalog invoice',db('SELECT status FROM ci_studio_invoices WHERE id=?',[inv['id']])==[{'status':'paid'}])
    r=admin.get(ADMIN+'?view=studio-job&id=1');admin.post(r.url,data={'csrf':token(r),'action':'catalog_void','job_id':'1','invoice_id':inv['id'],'reason':'Forged paid cancellation','confirm':'1'})
    check('Paid catalog invoice cannot be silently canceled',db('SELECT status FROM ci_studio_invoices WHERE id=?',[inv['id']])==[{'status':'paid'}])
    submit(admin,edit,'catalog_save',product_data(status='retired',amount='200.00',title='Revised HTTP service'))
    check('Retirement removes product from public page','Revised HTTP service' not in requests.get(PACKAGES).text)
    check('Earlier free-form manual invoices remain untouched',db('SELECT * FROM ci_studio_invoices WHERE id<=?',[max(i['id'] for i in original)])==original)
    check('Catalog invoice snapshots exclude internal metadata',all('INTERNAL_PROVIDER_ONLY_1938' not in r['snapshot'] and 'cost_cents' not in r['snapshot'] for r in db('SELECT snapshot FROM ci_catalog_invoice_snapshots')))
    db('DELETE FROM ci_rate_limits')

def run_browser(env,browser):
    run_http(env)
    context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=context.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(ADMIN+'?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard');p.goto(CATALOG)
    form=p.locator('form').filter(has=p.locator('[name=action][value=catalog_save]'))
    for key,value in product_data(sku='BROWSER-CARE',title='Website care for one month',family='maintenance',status='published',term='month',amount='99.95',setup='5.00').items():
        field=form.locator('[name='+key+']')
        if key in ['family','status','term']:field.select_option(value)
        elif key=='currency':continue
        else:
            if key in ['cost','provider_ref'] and not form.locator('details').evaluate('(el)=>el.open'):form.locator('summary').click()
            field.fill(value)
    form.get_by_role('button',name='Save product').click();expect(p.get_by_text('Product saved with a new revision.',exact=False)).to_be_visible()
    pid=db('SELECT id FROM ci_catalog_products WHERE sku=?',['BROWSER-CARE'])[0]['id'];check('Browser publishes configured product',db('SELECT status,amount_cents FROM ci_catalog_products WHERE id=?',[pid])==[{'status':'published','amount_cents':9995}])
    p.screenshot(path=str(QA/'Catalog-Desktop.png'),full_page=True);check('Catalog desktop fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    p.set_viewport_size({'width':320,'height':800});p.screenshot(path=str(QA/'Catalog-Narrow.png'),full_page=True);check('Catalog narrow fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    p.goto(PACKAGES);expect(p.get_by_role('heading',name='Website care for one month')).to_be_visible();p.screenshot(path=str(QA/'Catalog-Public-Narrow.png'),full_page=True);check('Public configured cards fit narrow viewport',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    p.set_viewport_size({'width':1440,'height':1000});p.screenshot(path=str(QA/'Catalog-Public-Desktop.png'),full_page=True)
    p.goto(ADMIN+'?view=studio-job&id=1');p.get_by_role('link',name='Create invoice from catalog').click();p.locator('[name=product]').select_option(str(pid)+':1');p.locator('[name=quantity]').fill('2');p.locator('[name=instructions]').fill('Browser manual invoice instructions');p.get_by_role('button',name='Review invoice').click()
    expect(p.locator('.catalog-review')).to_contain_text('Invoice total: $204.90 USD');p.screenshot(path=str(QA/'Catalog-Invoice-Review.png'),full_page=True)
    check('Browser review calculates exact catalog total','Invoice total: $204.90 USD' in p.locator('.catalog-review').inner_text())
    p.locator('.catalog-review [name=confirm]').check();p.get_by_role('button',name='Issue reviewed invoice').click();p.wait_for_url('**/admin/index.php?view=studio-job&id=1')
    invoice=db('SELECT i.* FROM ci_studio_invoices i JOIN ci_catalog_invoice_snapshots s ON s.invoice_id=i.id WHERE s.product_id=?',[pid])[0];check('Browser reviewed invoice persists correct amount',invoice['amount_cents']==20490 and invoice['status']=='issued')
    client=browser.new_context(viewport={'width':390,'height':844});c=client.new_page();c.goto(BASE+'/existing/portal/index.php');c.locator('[name=email]').fill('alice@example.invalid');c.locator('[name=password]').fill('Synthetic client passphrase 2026');c.locator('form button').click();c.wait_for_url('**/portal/index.php');c.goto(BASE+'/existing/portal/index.php?job=1')
    expect(c.get_by_text('Invoice total: $204.90 USD',exact=False)).to_be_visible();c.screenshot(path=str(QA/'Catalog-Client-Invoice.png'),full_page=True);check('Browser client invoice fits and has no private cost',c.evaluate('document.documentElement.scrollWidth <= innerWidth + 1') and 'INTERNAL_PROVIDER_ONLY_1938' not in c.content())
    check('Catalog browser has no uncaught errors',not errors);(QA/'catalog-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors},indent=2));client.close();context.close()
