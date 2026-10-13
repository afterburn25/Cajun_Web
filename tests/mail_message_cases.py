"""Packaged application, actual synthetic TLS, and actual Chromium message isolation."""
import json,time
from urllib.parse import urlencode
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['BASE','QA','check','soup','token','submit','inbox_server']})
    db('DELETE FROM ci_rate_limits');root=BASE+'/existing/admin/index.php';library=root+'?view=mail-accounts'
    check('Fresh setup creates empty exact-message request storage',db("SELECT value FROM fresh_settings WHERE name='mail_messages_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_mail_message_reads'))
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    aid=db("SELECT id FROM ci_mail_accounts WHERE label='Staff inbox fixture'")[0]['id'];inbox=root+'?view=mail-inbox&account_id='+aid;detail=root+'?view=mail-account&account_id='+aid
    route={'account_id':aid,'revision':'1','uidvalidity':'1234','uid':'82'};message=root+'?view=mail-message&'+urlencode(route)
    before=inbox_server.connections
    check('Upgrade waits for owner message preparation','must prepare message reading' in owner.get(message).text and inbox_server.connections==before)
    r=submit(owner,library,'mail_message_setup',{'csrf':'bad','current_password':OWNER});check('Message preparation rejects bad CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='mail_messages_schema_version'"))
    r=submit(owner,library,'mail_message_setup',{'current_password':'wrong'});check('Message preparation requires current owner password','current password' in r.text and not db("SELECT value FROM ci_settings WHERE name='mail_messages_schema_version'"))
    r=submit(owner,library,'mail_message_setup',{'current_password':OWNER});check('Message preparation commits without mailbox access','Message request history is ready' in r.text and inbox_server.connections==before)
    def load(session,**overrides):
        fields={'action':'mail_message_read','csrf':token(session.get(message)),**route};fields.update(overrides)
        return session.post(message,data=fields,timeout=30)
    def age():db('UPDATE ci_mail_message_reads SET started_epoch=?',[int(time.time())-7200])
    def grant(user,enabled='1',read='1'):
        revision=db('SELECT revision FROM ci_mail_account_grants WHERE account_id=? AND user_id=?',[aid,user])[0]['revision']
        return owner.post(detail,data={'csrf':token(owner.get(detail)),'action':'mail_accounts_grant','account_id':aid,'revision':revision,'user_id':user,'can_read':read,'can_send':'1','enabled':enabled,'days':'0','current_password':OWNER})
    check('Message GET offers explicit loading without network','Load message' in owner.get(message).text and inbox_server.connections==before)
    for overrides,phrase in [({'csrf':'bad'},'session expired'),({'uid':'81'},'no longer matches'),({'revision':'99'},'no longer matches')]:
        r=load(owner,**overrides);check('Malformed message POST refused before network '+str(overrides),phrase in r.text.lower() and inbox_server.connections==before and not db('SELECT * FROM ci_mail_message_reads'))
    check('Malformed query identity never contacts a mailbox',owner.get(message.replace('uid=82','uid=0')).status_code==403 and inbox_server.connections==before)
    inbox_server.mode='message';r=load(owner)
    (QA/'message-first-response.html').write_text(r.text,encoding='utf-8')
    (QA/'message-fixture-observation.json').write_text(json.dumps({'status':r.status_code,'connections':inbox_server.connections,'commands':inbox_server.commands,'errors':inbox_server.errors},indent=2))
    check('Exact message uses real synthetic TLS and renders both inline alternatives',r.status_code==200 and inbox_server.connections==before+1 and 'SYNTHETIC PRIVATE BODY' in r.text and soup(r).select_one('[data-message-formatted]') is not None)
    check('Message headers and plaintext stay escaped in the application DOM',not soup(r).select('[data-message-content] script,[data-message-content] img') and '<script>inert text</script>' in soup(r).select_one('[data-message-plain]').text)
    check('Message response disables caches and referrers','no-store' in r.headers.get('Cache-Control','') and r.headers.get('Referrer-Policy')=='no-referrer')
    stored=db('SELECT q.folder,q.uidvalidity,q.uid,r.outcome,r.result_code FROM ci_mail_message_reads q JOIN ci_mail_message_results r ON r.id=q.id')
    check('Message history binds the complete exact identity',stored==[{'folder':'INBOX','uidvalidity':'1234','uid':'82','outcome':'success','result_code':'ok'}])
    r=owner.get(message);check('Later GET retains no body or subject from the session','SYNTHETIC PRIVATE' not in r.text and 'No message loaded for this page' in r.text)
    r=load(owner);check('Message cooldown rejects repeat without connecting','2 seconds' in r.text and inbox_server.connections==before+1)
    member_id=db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id'];original=db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[member_id])[0]['permissions']
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Current global permission is mandatory for an assigned message',member.get(message).status_code==403)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[json.dumps(json.loads(original)+['mail.read']),member_id]);grant(member_id,read='0')
    check('Send-only assignment cannot open a message',member.get(message).status_code==403)
    grant(member_id);age();r=load(member);check('Assigned non-owner can open an exact message','SYNTHETIC PRIVATE BODY' in r.text and len(db('SELECT * FROM ci_mail_message_reads WHERE actor_id=?',[member_id]))==1)
    check('Message history is private to current staff identity',len(soup(member.get(message)).select('article.workspace-card'))==1)
    grant(member_id,enabled='0');check('Revoking mailbox grant stops the current browser session',member.get(message).status_code==403);grant(member_id)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[original,member_id]);check('Revoking global permission stops exact-message access',member.get(message).status_code==403)
    for mode,phrase in [('message-limit','exceeds the supported'),('message-wrong-uid','no longer available'),('message-epoch','no longer available'),('message-bad-mime','unsupported or incomplete')]:
        age();inbox_server.mode=mode;r=load(owner);check('Exact-message HTTP '+mode+' refuses partial content',phrase in r.text and soup(r).select_one('[data-message-content]') is None)
    age();inbox_server.mode='message-attachment';r=load(owner);check('Attached text is not displayed as an inline body','No supported inline text was provided' in r.text and 'SYNTHETIC ATTACHMENT SECRET' not in r.text)
    age();inbox_server.mode='message-html-only';r=load(owner);check('HTML-only message has formatting without invented plaintext',soup(r).select_one('[data-message-formatted]') is not None and soup(r).select_one('[data-message-plain]') is None)
    retained=json.dumps(db('SELECT * FROM ci_mail_message_reads')+db('SELECT * FROM ci_mail_message_results')+db("SELECT * FROM ci_audit WHERE action LIKE 'mail_accounts.message_%'"))
    check('Message storage and audit retain no contents or credentials','SYNTHETIC PRIVATE' not in retained and 'SYNTHETIC ATTACHMENT' not in retained and 'receiving HTTP password' not in retained)
    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();errors=[];remote=[];external=[];frame_resources=[];dialogs=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.on('request',lambda q:frame_resources.append(q.url) if q.frame.url=='about:srcdoc' else None)
    page.on('dialog',lambda d:(dialogs.append(d.message),d.dismiss()))
    def observe(route):
        url=route.request.url
        if url=='https://external.example.invalid/reference':external.append(dict(route.request.headers));route.fulfill(status=200,content_type='text/html',body='<h1>Synthetic destination</h1>')
        elif url.startswith(BASE):route.continue_()
        else:remote.append(url);route.abort()
    context.route('**/*',observe)
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard')
    inbox_server.mode='ok';db('UPDATE ci_mail_inbox_reads SET started_epoch=?',[int(time.time())-7200]);page.goto(inbox);page.get_by_role('button',name='Load recent headers').click();expect(page.locator('[data-open-message]')).to_have_count(2)
    age();inbox_server.mode='message';page.locator('[data-inbox-uid="82"] [data-open-message] button').click();expect(page.locator('[data-message-plain]')).to_contain_text('SYNTHETIC PRIVATE BODY')
    check('Inbox Open message performs the explicit exact-identity POST','view=mail-message' in page.url and 'uid=82' in page.url)
    frame=page.frame_locator('[data-message-formatted]');expect(frame.locator('p')).to_have_text('Formatted Café 👩‍💻')
    check('Formatted document strips resources scripts forms and head content',frame.locator('img,script,form,svg,link,title').count()==0 and not remote and not external)
    check('Formatted message has an opaque sandbox origin',frame.locator('body').evaluate('()=>{try{return !!parent.document.body}catch(e){return false}}') is False)
    check('Trusted frame styling works with actual application CSP',frame.locator('body').evaluate('(n)=>getComputedStyle(n).backgroundColor')=='rgb(16, 35, 56)')
    check('Message content cannot modify parent DOM',not page.evaluate('Boolean(window.mailInjected)'))
    # Bypass sanitization deliberately through the test browser API to establish
    # independent CSP/sandbox defenses on the actual packaged application's frame.
    frame.locator('body').evaluate('''(body, base)=>{
        let script=document.createElement('script');script.textContent='alert("frame script ran");window.cspInjected=true';body.append(script);
        for(const src of ['https://untrusted.example.invalid/injected',base+'/existing/admin/index.php?view=settings']){let img=document.createElement('img');img.src=src;body.append(img)}
        let form=document.createElement('form');form.action='https://untrusted.example.invalid/form';form.innerHTML='<button>Injected form</button>';body.append(form);
    }''',BASE)
    frame.get_by_role('button',name='Injected form').click();page.wait_for_timeout(100)
    check('Actual frame CSP and sandbox independently block injected scripts resources and forms',not frame_resources and not dialogs and not remote and not frame.locator('body').evaluate('Boolean(window.cspInjected)'))
    frame.locator('body').evaluate("(body)=>body.querySelectorAll('img,script,form').forEach(n=>n.remove())")
    saved_url=page.url
    with context.expect_page() as event:frame.get_by_role('link',name='Referenced website').click()
    popup=event.value;popup.wait_for_load_state('load');check('Only user-selected external link opens without referrer or opener',len(external)==1 and 'referer' not in external[0] and popup.evaluate('window.opener===null') and page.url==saved_url);popup.close()
    page.locator('[data-mail-message]').screenshot(path=str(QA/'Mail-Message-Desktop.png'));page.set_viewport_size({'width':390,'height':844});page.locator('[data-mail-message]').screenshot(path=str(QA/'Mail-Message-Mobile.png'))
    check('Message controls plaintext and iframe fit mobile screen',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Message browser flow has no script errors',not errors)
    before=inbox_server.connections;page.goto(message);check('Browser GET does not reload or cache message content',page.locator('[data-message-content]').count()==0 and inbox_server.connections==before)
    context.close();inbox_server.mode='ok'
    for path in ['app/mail/messages.php','admin/controllers/mail_message.php','admin/views/mail_message.php','database/schema/mail_messages_schema.php','database/migrations/mail_messages_migrate.php','includes/foundation/mail-receiving/MessageReader.php','includes/foundation/mail-receiving/BodyHtml.php']:
        check('Message direct PHP access denied '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    check('Synthetic exact-message TLS fixture observed no unexpected command',not inbox_server.errors and all(not any(' '+verb in c for verb in ['SELECT','STORE','APPEND','EXPUNGE','SEARCH']) for c in inbox_server.commands))
