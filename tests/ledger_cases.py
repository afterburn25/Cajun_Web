"""Real retained-1.7 upgrade, HTTP authorization and Chromium receipts; no real payments."""
import datetime,json,shutil,subprocess,zipfile
from urllib.parse import parse_qs,urlparse
import requests
from playwright.sync_api import expect
from release_cases import db,chosen_form
from staff_cases import OWNER,STAFF

def upgrade():
    dest=WEB/'ledger-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.7.0',dest)
    seed=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-ledger-upgrade'}),text=True,capture_output=True)
    (QA/'ledger-upgrade-seed.log').write_text(seed.stdout+seed.stderr,encoding='utf-8');seed.check_returncode()
    shutil.copyfile(ROOT/'test-runtime/ledger-upgrade-config.php',dest/'config.php');(dest/'.installed.php').write_text('<?php exit; ?>original guard');(dest/'unrelated.txt').write_text('Retain unrelated file')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/ledger-upgrade-private/media/retained.php',ROOT/'test-runtime/ledger-upgrade-private/payment-master.key']}
    tables=['staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_invoices','catalog_products','catalog_revisions','catalog_invoice_snapshots','payment_gateways','payment_requests','payment_attempts','payment_attempt_events','payment_notifications','leads','projects']
    before={t:db('SELECT * FROM ledgerup_'+t) for t in tables};settings=db('SELECT * FROM ledgerup_settings');users=db('SELECT * FROM ledgerup_users')
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Actual 1.7 update preserves configuration locks private media and master key',all(p.read_bytes()==v for p,v in preserved.items()))
    up=requests.Session();url=BASE+'/ledger-upgrade/admin/index.php';submit(up,url+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    r=submit(up,url+'?view=payments','payments_migrate',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'});check('Payment schema upgrade precedes the feature migration','Payment preparation enabled' in r.text)
    r=submit(up,url+'?view=invoice-payments','payments_ledger_setup',{'owner_password':'wrong','confirm':'1'});check('Ledger upgrade requires owner reauthentication','current password' in r.text.lower())
    r=submit(up,url+'?view=invoice-payments','payments_ledger_setup',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'});check('Actual 1.7 ledger upgrade succeeds','Invoice payment history enabled' in r.text)
    after={t:db('SELECT * FROM ledgerup_'+t) for t in before};check('Actual 1.7 retains prior invoices requests trials notifications and data',all(len(after[t])==len(v) and all(row[k]==v[i][k] for i,row in enumerate(after[t]) for k in v[i]) for t,v in before.items()))
    after={s['name']:s['value'] for s in db('SELECT * FROM ledgerup_settings')};check('Actual 1.7 retains original settings',all(after[s['name']]==s['value'] for s in settings if not s['name'].endswith('_schema_version')))
    check('Actual 1.7 retains passwords and auth versions',all(all(a[k]==b[k] for k in b if k!='last_login') for a,b in zip(db('SELECT * FROM ledgerup_users'),users)))
    rows=db('SELECT * FROM ledgerup_invoice_payments');check('Actual legacy paid import retains original amount and date without fabricated receipt',len(rows)==1 and rows[0]['kind']=='legacy' and rows[0]['amount_cents']==18050 and rows[0]['received_on']==before['studio_invoices'][1]['paid_at'][:10] and rows[0]['request_key'] is None)
    client=requests.Session();portal=BASE+'/ledger-upgrade/portal/index.php';submit(client,portal,'login',{'email':'retained@example.invalid','password':'Synthetic retained client passphrase'});r=client.get(portal+'?invoice=2');check('Legacy client statement is clearly imported and cannot print new receipt','Imported legacy paid balance' in r.text and 'Printable receipt' not in r.text and client.get(portal+'?receipt='+str(rows[0]['id'])).status_code==404)
    r=submit(up,url+'?view=invoice-payments&invoice_id=1','payments_ledger_receive',{'amount':'1.00','reference':'Blocked unresolved trial','received_on':TODAY,'confirm':'1'});check('Retained unresolved sandbox trial blocks manual payment after upgrade',not db("SELECT * FROM ledgerup_invoice_payments WHERE kind='manual'") and 'unresolved' in r.text.lower())
    r=submit(up,url+'?view=payment-settings','payments_save',{'owner_password':'Synthetic upgrade passphrase 2026'});check('Retained 1.7 credentials still decrypt after ledger upgrade','Credentials protected and saved' in r.text)

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php']})
    global TODAY
    TODAY=datetime.datetime.now(datetime.timezone.utc).date().isoformat();db('DELETE FROM ci_rate_limits');upgrade()
    check('Fresh setup creates empty enabled real-payment ledger',db("SELECT value FROM fresh_settings WHERE name='invoice_ledger_schema_version'")==[{'value':'1'}] and db('SELECT * FROM fresh_invoice_payments')==[])
    admin=requests.Session();root=BASE+'/existing/admin/index.php';portal=BASE+'/existing/portal/index.php';url=root+'?view=invoice-payments';job=root+'?view=studio-job&id=1'
    submit(admin,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    original=db('SELECT * FROM ci_studio_invoices');r=submit(admin,url,'payments_ledger_setup',{'owner_password':OWNER,'confirm':'1','csrf':'invalid'});check('Ledger enablement rejects forged CSRF',not db("SELECT value FROM ci_settings WHERE name='invoice_ledger_schema_version'") and 'session expired' in r.text.lower())
    r=submit(admin,url,'payments_ledger_setup',{'owner_password':OWNER,'confirm':'1'});check('HTTP owner enables ledger without editing prior invoices','Invoice payment history enabled' in r.text and db('SELECT * FROM ci_studio_invoices')==original)
    submit(admin,job,'studio_invoice',{'title':'Ledger design phase','amount':'1250.10','instructions':'Synthetic external payment instructions; do not send money.'});invoice=db("SELECT * FROM ci_studio_invoices WHERE title='Ledger design phase'")[0];iid=invoice['id'];target=url+'&invoice_id='+str(iid)
    check('New balance page is linked from admin project',target in [a.get('href') for a in soup(admin.get(job)).select('a')])
    check('Anonymous cannot open administrator ledger','Record external payment' not in requests.get(target).text)
    receipt_form=form_data(form_for(admin.get(target),'payments_ledger_receive'));check('Four-digit amount defaults to valid decimal without thousands separator',receipt_form['amount']=='1250.10')
    receive=dict(receipt_form,amount='400.10',reference='PrivateLedgerBankReference <script>neverRun()</script>',received_on=TODAY,confirm='1')
    count=len(db('SELECT * FROM ci_invoice_payments'));r=admin.post(target,data=dict(receive,csrf='bad'));check('Receipt rejects forged CSRF without mutation','session expired' in r.text.lower() and len(db('SELECT * FROM ci_invoice_payments'))==count)
    for value in ['1e3','1,000.00','1.001','-1','NaN','1250.11']:
        r=admin.post(target,data=dict(receive,amount=value));check('HTTP rejects invalid or excessive amount '+value,len(db('SELECT * FROM ci_invoice_payments'))==count and 'External payment recorded' not in r.text)
    r=admin.post(target,data=dict(receive,confirm='0'));check('Receipt requires explicit verification confirmation',len(db('SELECT * FROM ci_invoice_payments'))==count)
    r=admin.post(target,data=receive);entry=db("SELECT * FROM ci_invoice_payments WHERE invoice_id=? AND kind='manual'",[iid])[0];rid=entry['id'];check('HTTP partial payment creates receipt and retains issued status','External payment recorded' in r.text and '$850.00' in r.text and db('SELECT status FROM ci_studio_invoices WHERE id=?',[iid])==[{'status':'issued'}])
    check('Private reference is escaped in administrator history','&lt;script&gt;neverRun()&lt;/script&gt;' in r.text and '<script>neverRun()' not in r.text)
    r=admin.post(target,data=receive);check('Browser resubmission cannot duplicate receipt',len(db('SELECT * FROM ci_invoice_payments'))==count+1)
    r=admin.post(target,data=dict(receive,request_key='f'*64,reference='Stale form second receipt'));check('Different key cannot bypass stale balance review','balance changed' in r.text and len(db('SELECT * FROM ci_invoice_payments'))==count+1)
    r=submit(admin,target,'payments_ledger_receive',{'amount':'1.00','reference':receive['reference'],'received_on':TODAY,'confirm':'1'});check('HTTP duplicate external reference rejected','already recorded' in r.text)
    pay=root+'?view=payments&invoice_id='+str(iid);r=submit(admin,pay,'payments_prepare',{'confirm':'1'});check('Remaining balance flows into reviewed request',db('SELECT amount_cents FROM ci_payment_requests WHERE invoice_id=? ORDER BY id DESC LIMIT 1',[iid])==[{'amount_cents':85000}])
    alice=requests.Session();submit(alice,portal,'login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'});bob=requests.Session();submit(bob,portal,'login',{'email':'bob@example.invalid','password':'Synthetic client passphrase 2026'})
    statement=portal+'?invoice='+str(iid);receipt=portal+'?receipt='+str(rid)
    r=alice.get(statement);check('Owned statement shows total partial payment remaining and receipt link',r.status_code==200 and all(x in r.text for x in ['$1250.10','$400.10','$850.00',receipt]) and 'PrivateLedgerBankReference' not in r.text)
    r=alice.get(receipt);check('Owned printable receipt excludes staff-only data',r.status_code==200 and 'Bank transfer' in r.text and 'PrivateLedgerBankReference' not in r.text and 'Correction reason' not in r.text and 'Receipt #'+str(rid) in r.text)
    check('Cross-client invoice and receipt IDs are denied',bob.get(statement).status_code==404 and bob.get(receipt).status_code==404)
    check('Anonymous cannot read client invoice or receipt',requests.get(statement).status_code==403 and requests.get(receipt).status_code==403)
    r=alice.post(target,data=receive,allow_redirects=False);check('Client cannot invoke admin receipt mutation',r.status_code==303 and len(db('SELECT * FROM ci_invoice_payments'))==count+1)
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF});check('Payment staff with client grant can read invoice ledger',member.get(target).status_code==200)
    reverse=form_data(form_for(admin.get(target),'payments_ledger_reverse'));reverse.update(reason='PrivateCorrectionReason recording mistake',owner_password=OWNER,confirm='1')
    forged=dict(reverse,csrf=token(member.get(target)));r=member.post(target,data=forged);check('Non-owner cannot forge recording reversal',not db('SELECT id FROM ci_invoice_payments WHERE reversal_of=?',[rid]))
    r=admin.post(target,data=dict(reverse,owner_password='wrong'));check('Reversal reauthenticates current owner',not db('SELECT id FROM ci_invoice_payments WHERE reversal_of=?',[rid]) and 'current password' in r.text.lower())
    db("CREATE TRIGGER ci_ledger_fail BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action LIKE 'payments.ledger.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic ledger audit failure'; END IF; END")
    try:
        r=admin.post(target,data=reverse);check('HTTP reversal audit failure preserves payment and redacts SQL','could not be saved' in r.text and 'SQLSTATE' not in r.text and not db('SELECT id FROM ci_invoice_payments WHERE reversal_of=?',[rid]))
    finally:db('DROP TRIGGER ci_ledger_fail')
    r=admin.post(target,data=reverse);check('Owner correction appends reversal and reopens full amount','Recording corrected' in r.text and db('SELECT * FROM ci_invoice_payments WHERE id=?',[rid])==[entry] and '$1250.10' in r.text)
    r=alice.get(receipt);check('Original receipt remains accessible but clearly reversed','This recording was reversed' in r.text and 'PrivateCorrectionReason' not in r.text)
    r=chosen_form(admin,root+'?view=support','support_policy','client_id',1,{'allowed':'1','owner_password':OWNER});r=chosen_form(admin,root+'?view=support','support_start','client_id',1,{});sid=parse_qs(urlparse(r.url).query)['support'][0]
    r=admin.get(statement+'&support='+sid);check('Support can read owned statement with banner but no receipt link','Support session' in r.text and 'Remaining balance' in r.text and 'Printable receipt' not in r.text)
    r=admin.get(receipt+'&support='+sid);check('Support receipt access is denied and audited',r.status_code==403 and bool(db("SELECT id FROM ci_support_events WHERE action='receipt.view' AND outcome='denied' AND object_id=?",[str(rid)])))
    check('Support statement view is attributed to staff',bool(db("SELECT id FROM ci_support_events WHERE action='invoice.view' AND outcome='allowed' AND object_id=?",[str(iid)])))
    # Isolated browser owner records the full corrected amount, then client prints retained receipt.
    ctx=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=ctx.new_page();errors=[];external=[];p.on('pageerror',lambda e:errors.append(str(e)));p.on('request',lambda req:external.append(req.url) if 'authorize.net' in req.url else None)
    p.goto(root+'?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard');p.goto(target)
    p.screenshot(path=str(QA/'Invoice-Ledger-Desktop.png'),full_page=True);p.set_viewport_size({'width':320,'height':844});p.screenshot(path=str(QA/'Invoice-Ledger-Narrow.png'),full_page=True);check('Invoice ledger fits 320px screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    f=p.locator('form').filter(has=p.locator('[name=action][value=payments_ledger_receive]'));f.locator('[name=reference]').fill('Browser full payment reference');f.locator('[name=confirm]').check();f.get_by_role('button',name='Record payment',exact=True).click();expect(p.get_by_text('External payment recorded',exact=False)).to_be_visible()
    check('Browser final receipt pays exactly remaining corrected amount',db('SELECT status FROM ci_studio_invoices WHERE id=?',[iid])==[{'status':'paid'}] and db("SELECT amount_cents FROM ci_invoice_payments WHERE invoice_id=? AND kind='manual' ORDER BY id DESC LIMIT 1",[iid])==[{'amount_cents':125010}])
    p.goto(portal);p.locator('[name=email]').fill('alice@example.invalid');p.locator('[name=password]').fill('Synthetic client passphrase 2026');p.locator('form button').click();p.wait_for_url('**/portal/index.php');p.goto(statement);expect(p.get_by_role('heading',name='Payment history')).to_be_visible();p.screenshot(path=str(QA/'Invoice-Statement-Narrow.png'),full_page=True);check('Client statement fits 320px screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    p.get_by_role('link',name='Printable receipt',exact=False).first.click();expect(p.get_by_role('heading',name='Receipt #',exact=False)).to_be_visible();p.screenshot(path=str(QA/'Invoice-Receipt-Narrow.png'),full_page=True);check('Client receipt fits 320px screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    p.set_viewport_size({'width':1440,'height':1000});p.emulate_media(media='print');p.screenshot(path=str(QA/'Invoice-Receipt-Print.png'),full_page=True);p.pdf(path=str(QA/'Invoice-Receipt.pdf'),format='Letter',print_background=True)
    check('Receipt print hides site navigation and retains payment amount',p.locator('body>header').count()==0 or not p.locator('body>header').is_visible());check('Receipt print has dark text on white page and hides secondary navigation',p.locator('.ledger-document p').first.evaluate("e=>getComputedStyle(e).color==='rgb(17, 17, 17)'") and p.locator('body').evaluate("e=>getComputedStyle(e).backgroundColor==='rgb(255, 255, 255)'") and not p.locator('.growth-subnav').is_visible());check('Printed receipt PDF is nonempty',(QA/'Invoice-Receipt.pdf').stat().st_size>5000)
    check('Ledger browser has no runtime errors or gateway traffic',not errors and not external);ctx.close()
    (QA/'ledger-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors,'provider_requests':external,'scope':'Synthetic records in isolated database, actual HTTP and Chromium; no payments or refunds sent'},indent=2),encoding='utf-8')
