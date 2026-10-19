"""Native HTTP/Chromium payment preparation; no external payment/network fixtures represented as real provider acceptance."""
import json,shutil,subprocess,zipfile
import requests
from urllib.parse import parse_qs,urlparse
from playwright.sync_api import expect
from release_cases import db,chosen_form
from staff_cases import OWNER,STAFF

# The additions below use only the installed synthetic fixture. They do not
# prepare extra requests or change a request's captured invoice fingerprint.
PAYMENT_LINK_CAPTURES = tuple(
    'Payment-Link-' + state + '-' + viewport + '.png'
    for state in ('Valid', 'Terminal') for viewport in ('Desktop', 'Mobile')
)


def payment_link_local_url(link):
    value = urlparse(link)
    base = urlparse(BASE)
    query = parse_qs(value.query, keep_blank_values=True)
    if (value.scheme, value.netloc) != (base.scheme, base.netloc) or value.fragment or value.path != base.path.rstrip('/') + '/existing/index.php':
        raise AssertionError('Payment link escaped the installed local fixture')
    if set(query) != {'page', 't'} or query['page'] != ['pay'] or len(query['t']) != 1 or len(query['t'][0]) != 64 or any(c not in '0123456789abcdef' for c in query['t'][0]):
        raise AssertionError('Payment link has an unexpected route or token shape')
    return link


def payment_link_rows():
    # These exact financial and binding tables must remain unchanged by a GET.
    # Optional ledger/trial tables are absent before their original later setup.
    tables = ('studio_invoices', 'studio_jobs', 'studio_clients', 'payment_requests',
              'payment_gateways', 'audit', 'invoice_payments', 'payment_attempts',
              'payment_notifications', 'catalog_invoice_snapshots')
    present = {r['TABLE_NAME'] for r in db('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')}
    return {name: db('SELECT * FROM ci_' + name + ' ORDER BY id') if 'ci_' + name in present else None for name in tables}


def payment_link_terminal_page(response, state, forbidden, label):
    page = soup(response)
    cards = page.select('article.workspace-card')
    status = cards[0].select_one('.workspace-status') if len(cards) == 1 else None
    check(label + ' has the exact safe terminal state', response.status_code == 200 and status is not None and status.get_text(strip=True) == state)
    body = page.get_text(' ', strip=True)
    check(label + ' exposes no invoice metadata or stale amount', len(cards) == 1 and not cards[0].select('h2, .prose, strong') and '$' not in cards[0].get_text() and all(not value or value not in body for value in forbidden) and ' USD due' not in body and ' EUR due' not in body and 'request expires' not in body)


def payment_link_read_only_terminal(link, state, forbidden, label):
    before = payment_link_rows()
    response = requests.get(payment_link_local_url(link), allow_redirects=False)
    payment_link_terminal_page(response, state, forbidden, label)
    check(label + ' GET preserves financial and binding rows', payment_link_rows() == before)


def payment_link_http_isolation(link, request):
    original = db('SELECT * FROM ci_studio_invoices WHERE id=?', [INVOICE['id']])[0]
    job = db('SELECT * FROM ci_studio_jobs WHERE id=?', [original['job_id']])[0]
    client = db('SELECT * FROM ci_studio_clients WHERE id=?', [job['client_id']])[0]
    other = db('SELECT * FROM ci_studio_jobs WHERE id<>? AND client_id<>? ORDER BY id LIMIT 1', [job['id'], client['id']])[0]
    before = payment_link_rows()
    response = requests.get(payment_link_local_url(link), allow_redirects=False)
    page = soup(response)
    card = page.select_one('article.workspace-card')
    expected_amount = '$' + format(request['amount_cents'] / 100, '.2f') + ' USD due'
    check('Anonymous unchanged link renders exact captured invoice details', response.status_code == 200 and card is not None and card.select_one('h2').get_text() == original['reference'] + ' · ' + original['title'] and card.select_one('.prose').get_text() == original['instructions'] and expected_amount in card.get_text(' ', strip=True) and 'Agreed milestone invoice' in card.get_text())
    check('Anonymous unchanged link GET preserves financial and binding rows', payment_link_rows() == before)
    cases = (
        ('changed title', 'studio_invoices', original, 'title', 'Private changed payment title', 'invoice changed'),
        ('changed reference', 'studio_invoices', original, 'reference', 'PRIVATE-LINK-REFERENCE', 'invoice changed'),
        ('changed instructions', 'studio_invoices', original, 'instructions', 'Private changed invoice instructions', 'invoice changed'),
        ('changed cost', 'studio_invoices', original, 'amount_cents', original['amount_cents'] + 123, 'invoice changed'),
        ('changed currency', 'studio_invoices', original, 'currency', 'EUR', 'unavailable'),
        ('moved invoice project', 'studio_invoices', original, 'job_id', other['id'], 'unavailable'),
        ('moved project client', 'studio_jobs', job, 'client_id', other['client_id'], 'unavailable'),
        ('inactive original client', 'studio_clients', client, 'active', 0, 'unavailable'),
        ('paid invoice status', 'studio_invoices', original, 'status', 'paid', 'paid'),
    )
    for label, table, row, column, changed, state in cases:
        # Fixed identifiers above only. The fixture fault does not rewrite any
        # immutable payment request; restore the exact original cell even on failure.
        original_rows = payment_link_rows()
        try:
            db('UPDATE ci_' + table + ' SET ' + column + '=? WHERE id=?', [changed, row['id']])
            forbidden = [original['title'], original['reference'], original['instructions'], expected_amount,
                         'Agreed milestone invoice', '$' + format((original['amount_cents'] + 123) / 100, '.2f')]
            if column in ('title', 'reference', 'instructions'):
                forbidden.append(changed)
            payment_link_read_only_terminal(link, state, forbidden, 'Anonymous link after ' + label)
        finally:
            db('UPDATE ci_' + table + ' SET ' + column + '=? WHERE id=?', [row[column], row['id']])
            check('Anonymous link restores exact fixture after ' + label, payment_link_rows() == original_rows)


def payment_link_browser_isolation(browser):
    # The original browser flow has just prepared its legitimate deposit request.
    request = db('SELECT * FROM ci_payment_requests WHERE invoice_id=? ORDER BY id DESC LIMIT 1', [INVOICE['id']])[0]
    review = soup(admin.get(PAY + '&invoice_id=' + str(INVOICE['id'])))
    cards = [node for node in review.select('article.workspace-version')
             if node.select_one('[name=request_id]') is not None and node.select_one('[name=request_id]').get('value') == str(request['id'])]
    check('Anonymous browser uses the exact rendered current request link', len(cards) == 1 and len(cards[0].select('code')) == 1 and request['status'] == 'prepared')
    link = payment_link_local_url(cards[0].select_one('code').get_text(strip=True))
    before = payment_link_rows()
    context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce')
    local = urlparse(BASE)
    blocked = []
    def local_only(route):
        target = urlparse(route.request.url)
        if (target.scheme, target.netloc) == (local.scheme, local.netloc):
            route.continue_()
        else:
            blocked.append((target.scheme, target.netloc))
            route.abort()
    context.route('**/*', local_only)
    check('Anonymous payment browser starts without an authenticated session', not context.cookies())
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    try:
        for state in ('Valid', 'Terminal'):
            original_rows = payment_link_rows()
            try:
                if state == 'Terminal':
                    db("UPDATE ci_payment_requests SET status='revoked' WHERE id=?", [request['id']])
                for viewport, width, height in (('Desktop', 1440, 1000), ('Mobile', 390, 844)):
                    page.set_viewport_size({'width': width, 'height': height})
                    response = page.goto(link, wait_until='networkidle')
                    card = page.locator('article.workspace-card')
                    expect(card).to_have_count(1)
                    expect(card).to_be_visible()
                    check('Anonymous payment link ' + state + ' ' + viewport + ' returns protected HTTP content', response is not None and response.status == 200 and 'no-store' in response.headers.get('cache-control', '') and response.headers.get('referrer-policy') == 'no-referrer')
                    if state == 'Valid':
                        expect(card.locator('h2')).to_have_text(INVOICE['reference'] + ' · ' + INVOICE['title'])
                        expect(card.locator('.prose')).to_have_text(INVOICE['instructions'])
                        check('Anonymous payment link Valid ' + viewport + ' shows exact due amount', '$' + format(request['amount_cents'] / 100, '.2f') + ' USD due' in card.inner_text())
                    else:
                        expect(card.locator('.workspace-status')).to_have_text('revoked')
                        check('Anonymous payment link Terminal ' + viewport + ' hides invoice details and due amount', card.locator('h2, .prose, strong').count() == 0 and '$' not in card.inner_text() and all(not value or value not in page.locator('body').inner_text() for value in (INVOICE['title'], INVOICE['reference'], INVOICE['instructions'], 'USD due', 'request expires')))
                    check('Anonymous payment link ' + state + ' ' + viewport + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
                    page.screenshot(path=str(QA / ('Payment-Link-' + state + '-' + viewport + '.png')), full_page=True)
            finally:
                if state == 'Terminal':
                    db('UPDATE ci_payment_requests SET status=? WHERE id=?', [request['status'], request['id']])
                check('Anonymous payment browser ' + state + ' restores financial and binding rows', payment_link_rows() == original_rows)
        check('Anonymous payment browser has no uncaught errors', not errors)
    finally:
        context.close()
    check('Anonymous payment browser preserves all original financial rows', payment_link_rows() == before)


def upgrade():
    dest=WEB/'payment-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.4.0',dest)
    seed=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-payment-upgrade'}),text=True,capture_output=True)
    (QA/'payment-upgrade-seed.log').write_text(seed.stdout+seed.stderr)
    seed.check_returncode()
    shutil.copyfile(ROOT/'test-runtime/payment-upgrade-config.php',dest/'config.php');(dest/'.installed.php').write_text('<?php exit; ?>original guard');(dest/'unrelated.txt').write_text('Retain unrelated file')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/payment-upgrade-private/media/retained.php']}
    tables=['staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_invoices','catalog_products','catalog_revisions','catalog_invoice_snapshots','leads','projects']
    before={t:db('SELECT * FROM paymentup_'+t) for t in tables};settings=db('SELECT * FROM paymentup_settings');users=db('SELECT * FROM paymentup_users')
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update, removed_setup_unavailable
    apply_file_update(globals(),dest)
    check('Actual 1.4.0 update preserves configuration guards and private files',all(p.read_bytes()==v for p,v in preserved.items()))
    up=requests.Session();url=BASE+'/payment-upgrade/admin/index.php';submit(up,url+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    r=submit(up,url+'?view=studio-upgrade','studio_migrate',{'confirm':'1'});check('Workspace schema upgrade precedes the feature migration','Database update completed' in r.text)
    r=submit(up,url+'?view=payments','payments_migrate',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'})
    check('Actual 1.4.0 payment preparation migration completes','Payment preparation enabled' in r.text)
    for t in tables:check('Actual 1.4.0 retains '+t,db('SELECT * FROM paymentup_'+t)==before[t])
    now={v['name']:v['value'] for v in db('SELECT * FROM paymentup_settings')};check('Actual 1.4.0 retains all original settings',all(now[s['name']]==s['value'] for s in settings if not s['name'].endswith('_schema_version')))
    check('Actual 1.4.0 retains owner passwords and account identities',all(all(a[k]==b[k] for k in b if k!='last_login') for a,b in zip(db('SELECT * FROM paymentup_users'),users)))
    check('Actual 1.4.0 upgrade creates no gateway or payable requests',not db('SELECT * FROM paymentup_payment_gateways') and not db('SELECT * FROM paymentup_payment_requests'))
    check('Actual 1.4.0 installed guard remains',removed_setup_unavailable(globals(),dest,up))

def run_http(env):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php','admin','bob']})
    global ADMIN,PAY,SETTINGS,INVOICE,CREDS
    ADMIN=BASE+'/existing/admin/index.php';PAY=ADMIN+'?view=payments';SETTINGS=ADMIN+'?view=payment-settings'
    CREDS={'api_login_id':'HttpFixtureLogin','transaction_key':'HttpFixtureKey12','signature_key':'b'*128,'public_client_key':'HttpFixturePublicKey'}
    db('DELETE FROM ci_rate_limits');upgrade()
    check('Fresh installer initializes payment schema without credentials',db("SELECT value FROM fresh_settings WHERE name='payments_schema_version'")==[{'value':'2'}] and not db('SELECT * FROM fresh_payment_gateways'))
    r=submit(admin,PAY,'payments_migrate',{'owner_password':'wrong','confirm':'1'});check('Payment setup HTTP reauthentication','current password is not correct' in r.text)
    r=submit(admin,PAY,'payments_migrate',{'owner_password':OWNER,'confirm':'1','csrf':'bad'});check('Payment setup HTTP CSRF denial','session expired' in r.text.lower())
    r=submit(admin,PAY,'payments_migrate',{'owner_password':OWNER,'confirm':'1'});check('Owner enables payment preparation','Payment preparation enabled' in r.text)
    r=submit(admin,SETTINGS,'payments_save',dict(CREDS,owner_password=OWNER,csrf='bad'));check('Gateway save rejects CSRF without persistence',not db('SELECT * FROM ci_payment_gateways') and 'HttpFixture' not in r.text)
    r=submit(admin,SETTINGS,'payments_save',dict(CREDS,owner_password='wrong'));check('Gateway save reauthenticates and never echoes secrets','current password is not correct' in r.text and 'HttpFixture' not in r.text and not db('SELECT * FROM ci_payment_gateways'))
    r=submit(admin,SETTINGS,'payments_save',dict(CREDS,owner_password=OWNER));check('Gateway values saved but remain blank in HTTP response','protected and saved' in r.text and 'HttpFixture' not in r.text and all(soup(r).select_one('[name='+k+']').get('value','')=='' for k in CREDS))
    row=db('SELECT * FROM ci_payment_gateways')[0];check('Gateway native record has encrypted values only','HttpFixture' not in row['credentials'] and row['validation']=='not_verified')
    production=SETTINGS+'&environment=production';r=submit(admin,production,'payments_save',dict(CREDS,api_login_id='ProductionHttpFixture',owner_password=OWNER));check('Sandbox production saved independently',len(db('SELECT * FROM ci_payment_gateways'))==2 and 'ProductionHttpFixture' not in r.text)
    r=admin.post(production,data={'action':'payments_verify','csrf':token(r),'environment':'production','revision':'1','owner_password':OWNER});check('Forged production verification fails closed','Production connection and charging are disabled' in r.text)
    before=db('SELECT * FROM ci_payment_gateways');r=submit(admin,SETTINGS,'payments_save',{'revision':'0','owner_password':OWNER});check('Stale credential edit cannot overwrite saved settings','settings changed' in r.text and db('SELECT * FROM ci_payment_gateways')==before)
    r=submit(admin,SETTINGS,'payments_save',{'owner_password':OWNER});check('Empty update keeps protected keys and increments revision','protected and saved' in r.text and db("SELECT revision FROM ci_payment_gateways WHERE environment='sandbox'")[0]['revision']==2)
    r=submit(admin,SETTINGS,'payments_clear',{'owner_password':OWNER,'confirm':'0'});check('Credential clear requires explicit confirmation','Confirm removal' in r.text)
    r=submit(admin,SETTINGS,'payments_clear',{'owner_password':OWNER,'confirm':'1'});check('Credential clear preserves other environment',db("SELECT credentials FROM ci_payment_gateways WHERE environment='sandbox'")==[{'credentials':''}] and db("SELECT credentials FROM ci_payment_gateways WHERE environment='production'")[0]['credentials']!='')
    INVOICE=db("SELECT * FROM ci_studio_invoices WHERE job_id=1 AND status='issued' ORDER BY id DESC LIMIT 1")[0];target=PAY+'&invoice_id='+str(INVOICE['id']);r=admin.get(target);review=form_data(form_for(r,'payments_prepare'));review.update(confirm='1',purpose='milestone',amount_cents='1',currency='EUR')
    r=admin.post(target,data=dict(review,csrf='bad'));check('Request creation HTTP CSRF denial',not db('SELECT * FROM ci_payment_requests'))
    r=admin.post(target,data=review);request=db('SELECT * FROM ci_payment_requests')[0];check('Request uses server invoice total and currency despite forged fields',request['amount_cents']==INVOICE['amount_cents'] and request['currency']=='USD' and 'No charge was made' in r.text)
    r=admin.post(target,data=review);check('HTTP retry does not duplicate request',len(db('SELECT * FROM ci_payment_requests'))==1)
    r=admin.post(target,data=dict(review,purpose='final'));check('HTTP changed duplicate request denied','already submitted with different details' in r.text)
    alice=requests.Session();submit(alice,BASE+'/existing/portal/index.php','login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    r=alice.get(BASE+'/existing/portal/index.php?job=1');check('Client sees own prepared request with truthful unpaid notice','Agreed milestone invoice' in r.text and 'Online payment is not available yet' in r.text and 'HttpFixture' not in r.text and 'ProductionHttpFixture' not in r.text)
    check('Other client cannot view payment request',bob.get(BASE+'/existing/portal/index.php?job=1').status_code==404 and 'Agreed milestone invoice' not in bob.get(BASE+'/existing/portal/index.php?job=2').text)
    pay=soup(r).select_one('a[href*="page=pay"]');check('Client portal exposes own payment-request link',pay is not None)
    link=pay['href'] if pay else '';r=requests.get(link);check('Shared payment link resolves invoice without a portal session',r.status_code==200 and 'SECURE PAYMENT REQUEST' in r.text and 'Agreed milestone invoice' in r.text and 'not available on this link yet' in r.text and 'noindex' in r.headers.get('X-Robots-Tag','') and 'no-referrer' in r.headers.get('Referrer-Policy',''))
    check('Shared payment link never echoes secrets or other records','HttpFixture' not in r.text and 'ProductionHttpFixture' not in r.text)
    payment_link_http_isolation(link, request)
    check('Unknown payment link refused honestly',requests.get(BASE+'/existing/index.php?page=pay&t='+('0'*64)).status_code==404 and 'not valid' in requests.get(BASE+'/existing/index.php?page=pay&t='+('0'*64)).text)
    check('Missing payment token refused',requests.get(BASE+'/existing/index.php?page=pay').status_code==404)
    r=alice.post(PAY,data={'action':'payments_prepare','csrf':token(alice.get(BASE+'/existing/portal/index.php?job=1')),**{k:v for k,v in review.items() if k not in ['action','csrf']}},allow_redirects=False);check('Client cannot prepare administrator request',r.status_code==303 and 'login' in r.headers.get('Location',''))
    r=alice.post(PAY,data={'action':'payments_prepare','csrf':token(alice.get(PAY)),**{k:v for k,v in review.items() if k not in ['action','csrf']}},allow_redirects=False);check('Client cannot prepare administrator request',r.status_code==303 and 'login' in r.headers.get('Location',''))
    earlier=form_data(form_for(admin.get(target),'payments_prepare'));db('UPDATE ci_studio_invoices SET amount_cents=amount_cents+1 WHERE id=?',[INVOICE['id']]);r=admin.post(target,data=dict(earlier,confirm='1'));check('Stale invoice amount rejected after review','invoice changed' in r.text.lower() and len(db('SELECT * FROM ci_payment_requests'))==1);db('UPDATE ci_studio_invoices SET amount_cents=? WHERE id=?',[INVOICE['amount_cents'],INVOICE['id']])
    r=submit(admin,target,'payments_prepare',{'confirm':'1','purpose':'final','days':'3'});check('New request supersedes old instead of enabling two',db('SELECT status FROM ci_payment_requests ORDER BY id')==[{'status':'superseded'},{'status':'prepared'}])
    check('Superseded payment link stays honest','newer payment request replaced this link' in requests.get(link).text)
    payment_link_read_only_terminal(link, 'superseded', [INVOICE['title'], INVOICE['reference'], INVOICE['instructions'], 'Agreed milestone invoice'], 'Superseded anonymous payment link')
    link2=soup(alice.get(BASE+'/existing/portal/index.php?job=1')).select_one('a[href*="page=pay"]')['href']
    r=submit(admin,PAY,'payments_revoke',{'confirm':'1'});check('Revocation preserves invoice financial state',db('SELECT status FROM ci_payment_requests ORDER BY id DESC LIMIT 1')==[{'status':'revoked'}] and db('SELECT * FROM ci_studio_invoices WHERE id=?',[INVOICE['id']])[0]==INVOICE)
    check('Revoked payment link refuses honestly','revoked this payment request' in requests.get(link2).text)
    payment_link_read_only_terminal(link2, 'revoked', [INVOICE['title'], INVOICE['reference'], INVOICE['instructions'], 'Final balance invoice'], 'Revoked anonymous payment link')
    r=submit(admin,target,'payments_prepare',{'confirm':'1'});latest=db('SELECT id FROM ci_payment_requests ORDER BY id DESC LIMIT 1')[0]['id'];db('UPDATE ci_payment_requests SET expires_at=1 WHERE id=?',[latest]);check('Expired request displayed honestly','expired' in admin.get(PAY).text and 'expired' in alice.get(BASE+'/existing/portal/index.php?job=1').text)
    link3=[c.get_text() for c in soup(admin.get(PAY)).select('code') if 'page=pay' in c.get_text()];check('Expired payment link refuses honestly',link3 and 'This payment link expired' in requests.get(link3[0]).text)
    payment_link_read_only_terminal(link3[0], 'expired', [INVOICE['title'], INVOICE['reference'], INVOICE['instructions']], 'Expired anonymous payment link')
    # Existing clients permission must not silently receive newly introduced finance permission.
    email='request-staff@example.invalid';submit(admin,ADMIN+'?view=staff','staff_save',{'name':'Request staff','email':email,'role':'administrator','permissions[]':['clients.manage'],'enabled':'1','owner_password':OWNER})
    uid=db('SELECT id FROM ci_users WHERE email=?',[email])[0]['id'];r=chosen_form(admin,ADMIN+'?view=staff','staff_invite','id',uid,{'owner_password':OWNER});link=soup(r).select_one('[data-staff-invite]')['href'];code=parse_qs(urlparse(link).fragment)['token'][0];staff=requests.Session();submit(staff,link,'staff_activate',{'token':code,'password':STAFF,'confirm_password':STAFF});submit(staff,ADMIN+'?view=login','login',{'email':email,'password':STAFF})
    check('Client-only staff cannot read payment requests',staff.get(PAY).status_code==403);check('Client-only staff cannot read gateway settings',staff.get(SETTINGS).status_code==403)
    r=chosen_form(admin,ADMIN+'?view=staff','staff_save','id',uid,{'permissions[]':['clients.manage','payments.manage'],'owner_password':OWNER});submit(staff,ADMIN+'?view=login','login',{'email':email,'password':STAFF});check('Explicit request permission admits staff without gateway access',staff.get(PAY).status_code==200 and staff.get(SETTINGS).status_code==403)
    r=submit(staff,target,'payments_prepare',{'confirm':'1'});check('Permitted staff can prepare requests','Payment request prepared' in r.text)
    # Strict audit failure must roll back both replacement and credential mutation.
    db("CREATE TRIGGER ci_payment_audit_failure BEFORE INSERT ON ci_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic payment audit failure'")
    before=db('SELECT * FROM ci_payment_requests');r=submit(admin,target,'payments_prepare',{'confirm':'1'});check('HTTP audit failure rolls back request replacement',db('SELECT * FROM ci_payment_requests')==before and 'could not be saved' in r.text)
    before=db('SELECT * FROM ci_payment_gateways');r=submit(admin,production,'payments_clear',{'confirm':'1','owner_password':OWNER});check('HTTP audit failure rolls back protected credential deletion',db('SELECT * FROM ci_payment_gateways')==before and 'ProductionHttpFixture' not in r.text)
    db('DROP TRIGGER ci_payment_audit_failure')
    check('Public pages contain no gateway keys or payment request data',all('HttpFixture' not in requests.get(BASE+'/existing/'+p).text and 'Agreed milestone invoice' not in requests.get(BASE+'/existing/'+p).text for p in ['', 'index.php?page=packages']))
    check('Payment preparations never alter invoice status',db('SELECT * FROM ci_studio_invoices WHERE id=?',[INVOICE['id']])[0]==INVOICE)

def run_browser(env,browser):
    run_http(env)
    ctx=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=ctx.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(ADMIN+'?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard');p.goto(SETTINGS)
    f=p.locator('form').filter(has=p.locator('[name=action][value=payments_save]'))
    for k,v in CREDS.items():f.locator('[name='+k+']').fill(v)
    f.locator('[name=owner_password]').fill(OWNER);f.get_by_role('button').click();expect(p.get_by_text('Credentials protected and saved.',exact=False)).to_be_visible()
    check('Browser credentials clear after successful save',all(p.locator('[name='+k+']').input_value()=='' for k in CREDS) and 'HttpFixture' not in p.content())
    p.screenshot(path=str(QA/'Payment-Settings-Desktop.png'),full_page=True);p.set_viewport_size({'width':320,'height':844});p.screenshot(path=str(QA/'Payment-Settings-Narrow.png'),full_page=True);check('Payment settings fit narrow screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    p.set_viewport_size({'width':1440,'height':1000});p.goto(PAY+'&invoice_id='+str(INVOICE['id']));p.locator('.payment-review [name=purpose]').select_option('deposit');p.locator('.payment-review [name=confirm]').check();p.screenshot(path=str(QA/'Payment-Request-Review.png'),full_page=True);p.get_by_role('button',name='Prepare request',exact=True).click();expect(p.get_by_text('Payment request prepared.',exact=False)).to_be_visible()
    check('Browser request preparation retains invoice amount',db('SELECT amount_cents,purpose FROM ci_payment_requests ORDER BY id DESC LIMIT 1')==[{'amount_cents':INVOICE['amount_cents'],'purpose':'deposit'}]);p.set_viewport_size({'width':320,'height':844});p.screenshot(path=str(QA/'Payment-Requests-Narrow.png'),full_page=True);check('Payment requests fit narrow screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    check('Payment browser has no uncaught errors',not errors);(QA/'payment-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors,'provider_calls':'None; connection response parsing tested separately with CLI fixtures'},indent=2));ctx.close()
    payment_link_browser_isolation(browser)
