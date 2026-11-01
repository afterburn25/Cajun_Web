"""Real HTTP/Chromium owner controls. History fixtures are synthetic; no external mail server."""
import json,time
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF
from owner_browser_forms import refuse_owner_action, assert_no_owner_reprompt
from installed_owner_authority import revoked_owner

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit']})
    db('DELETE FROM ci_rate_limits');root=BASE+'/existing/admin/index.php';new=root+'?view=mail-account'
    check('Fresh setup prepares empty connection check storage',db("SELECT value FROM fresh_settings WHERE name='mail_connections_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_mail_connection_checks'))
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    fields=form_data(soup(owner.get(new)).select_one('[data-mail-account-editor]'))
    fields.update(label='Connection test fixture',address='connection-browser@example.invalid',imap_host='imap.example.invalid',imap_port='993',imap_encryption='ssl',imap_username='synthetic-incoming',smtp_host='smtp.example.invalid',smtp_port='587',smtp_encryption='starttls',smtp_username='synthetic-outgoing',imap_password='Synthetic connection incoming password',smtp_password='Synthetic connection outgoing password',enabled='1',current_password=OWNER)
    owner.post(new,data=fields)
    aid=db("SELECT id FROM ci_mail_accounts WHERE address='connection-browser@example.invalid'")[0]['id'];detail=new+'&account_id='+aid
    def action(name,**values):
        return owner.post(detail,data={'csrf':token(owner.get(detail)),'action':name,'account_id':aid,'revision':'1','current_password':OWNER,**values})
    r=action('mail_accounts_connections_setup',csrf='bad')
    check('Connection-check setup rejects CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='mail_connections_schema_version'"))
    refuse_owner_action(env,owner,detail,'mail_accounts_connections_setup',{})
    r=action('mail_accounts_connections_setup')
    check('Owner prepares checks without connecting','Connection-check history is ready' in r.text and not db('SELECT * FROM ci_mail_connection_checks'))
    check('Unassigned owner sees requirement instead of test button','assign yourself Read access' in r.text and soup(r).select_one('[data-mail-check=imap] button') is None)
    action('mail_accounts_grant',revision='0',user_id='1',can_read='1',can_send='1',enabled='1',days='0')
    r=owner.get(detail);check('Owner gets separate incoming and outgoing controls',soup(r).select_one('[data-mail-check=imap] button') is not None and soup(r).select_one('[data-mail-check=smtp] button') is not None and 'reads and sends no messages' in r.text)
    r=action('mail_accounts_check',target='imap',csrf='bad')
    check('Diagnostic action rejects CSRF before intent','session expired' in r.text.lower() and not db('SELECT * FROM ci_mail_connection_checks'))
    r=refuse_owner_action(env,owner,detail,'mail_accounts_check',{'target':'imap','account_id':aid,'revision':'1'})
    check('Revoked diagnostic authority cannot save an attempt or expose credentials',all(f.get('value','')=='' for f in soup(r).select('input[type=password]')) and not db('SELECT * FROM ci_mail_connection_checks'))
    r=action('mail_accounts_check',target='imap',revision='99')
    check('Diagnostic action rejects stale saved settings','account changed' in r.text and not db('SELECT * FROM ci_mail_connection_checks'))
    r=action('mail_accounts_check',target='pop3')
    check('Unsupported diagnostic protocol is rejected','Choose incoming or outgoing' in r.text and not db('SELECT * FROM ci_mail_connection_checks'))
    r=action('mail_accounts_check',target='imap')
    check('Fixture-only hostname is rejected without network or result','public server hostname' in r.text and not db('SELECT * FROM ci_mail_connection_checks'))
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    r=member.post(root+'?view=security',data={'csrf':token(member.get(root+'?view=security')),'action':'mail_accounts_check','account_id':aid,'revision':'1','target':'imap','current_password':STAFF})
    check('Forged non-owner diagnostic does not create an attempt',not db('SELECT * FROM ci_mail_connection_checks'))
    # Explicit display fixtures, not a claimed login or end-to-end provider result.
    stamp=time.strftime('%Y-%m-%d %H:%M:%S',time.gmtime())
    for key,target in [('a'*32,'imap'),('b'*32,'smtp')]:
        db('INSERT INTO ci_mail_connection_checks (id,account_id,account_revision,target,actor_id,started_at,started_epoch) VALUES (?,?,1,?,1,?,?)',[key,aid,target,stamp,int(time.time())])
    db("INSERT INTO ci_mail_connection_results (id,outcome,result_code,finished_at,elapsed_ms) VALUES (?,'failed','tls',?,1)",['a'*32,stamp])
    r=owner.get(detail)
    check('Synthetic failed and interrupted history never claims success','Check failed' in r.text and 'Result not recorded' in r.text and 'No final connection result was recorded' in r.text and 'Login checked' not in r.text)
    edit=form_data(soup(r).select_one('[data-mail-account-editor]'));edit.update(current_password=OWNER,label='Updated connection fixture');owner.post(detail,data=edit)
    check('Changed settings mark earlier check results historical','Historical settings' in owner.get(detail).text and len(db('SELECT * FROM ci_mail_connection_checks WHERE account_id=?',[aid]))==2)
    action('mail_accounts_grant',revision='1',user_id='1',can_read='1',can_send='1',enabled='0',days='0')
    r=action('mail_accounts_check',target='imap',revision='2')
    check('Revoked current mailbox assignment stops a fresh check','matching mailbox assignment' in r.text and len(db('SELECT * FROM ci_mail_connection_checks WHERE account_id=?',[aid]))==2)
    action('mail_accounts_grant',revision='2',user_id='1',can_read='1',can_send='1',enabled='1',days='0')
    for path in ['app/mail/connections.php','database/schema/mail_connections_schema.php','database/migrations/mail_connections_migrate.php','admin/views/mail_connections.php','includes/foundation/mail-transport/SecureChannel.php','includes/foundation/mail-transport/ConnectionProbe.php']:
        check('Mail-check internal HTTP denial '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    context=browser.new_context(viewport={'width':1440,'height':1000});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard');page.goto(detail)
    assert_no_owner_reprompt(check,page.locator('[data-mail-check=imap]'),'Test incoming connection')
    with revoked_owner(env['WEB']/'existing',env['php']):
        with page.expect_navigation(wait_until='domcontentloaded') as refused:
            page.get_by_role('button',name='Test incoming connection').click()
        check('Chromium refuses the same owner after diagnostic authority is withdrawn',refused.value is not None and (refused.value.status==403 or (refused.value.status==200 and 'Your account does not have access to this action.' in page.locator('body').inner_text())))
    page.goto(detail)
    assert_no_owner_reprompt(check,page.locator('[data-mail-check=imap]'),'Restored owner connection check')
    page.get_by_role('button',name='Test incoming connection').click()
    expect(page.locator('.notice.error').first).to_contain_text('public server hostname')
    check('Chromium unsupported endpoint leaves synthetic history unchanged',len(db('SELECT * FROM ci_mail_connection_checks WHERE account_id=?',[aid]))==2)
    page.locator('[data-mail-checks]').screenshot(path=str(QA/'Mail-Connection-Checks-Desktop.png'))
    page.set_viewport_size({'width':390,'height':844});expect(page.locator('.admin-sidebar')).not_to_be_in_viewport();page.locator('[data-mail-checks]').screenshot(path=str(QA/'Mail-Connection-Checks-Mobile.png'))
    check('Connection-check forms fit mobile',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Connection controls have no browser errors',not errors);context.close()
