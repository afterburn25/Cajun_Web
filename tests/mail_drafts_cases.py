"""Packaged HTTP and actual Chromium local drafts; synthetic accounts, no delivery."""
import json,time
from urllib.parse import parse_qs,urlparse,urljoin
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit','inbox_server','provider_server']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php';library=root+'?view=mail-drafts';new=root+'?view=mail-draft';accounts=root+'?view=mail-account'
    check('Fresh installation prepares empty private draft storage',db("SELECT value FROM fresh_settings WHERE name='mail_drafts_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_mail_drafts') and not db('SELECT * FROM fresh_mail_draft_revisions'))
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    snapshot=db('SELECT * FROM ci_settings');jobs=db('SELECT * FROM ci_studio_jobs')
    connections=inbox_server.connections;provider_calls=provider_server.calls;capture=(QA/'mail-capture.txt').read_bytes()
    for changes in [{'csrf':'invalid','current_password':OWNER},{'current_password':'wrong'}]:
        r=submit(owner,library,'mail_drafts_setup',changes)
        check('Draft preparation rejects invalid confirmation '+str(list(changes)),not db("SELECT value FROM ci_settings WHERE name='mail_drafts_schema_version'") and ('session expired' in r.text.lower() or 'current password' in r.text))
    r=submit(owner,library,'mail_drafts_setup',{'current_password':OWNER})
    check('Owner prepares drafts with all previous settings and projects retained','Local email drafts are ready' in r.text and db('SELECT * FROM ci_studio_jobs')==jobs and all(s in db('SELECT * FROM ci_settings') for s in snapshot))
    member_id=db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id']
    owner_id=db("SELECT id FROM ci_users WHERE email='owner@example.invalid'")[0]['id']
    original=db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[member_id])[0]['permissions']
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Staff without global Send cannot view draft section',member.get(library).status_code==403)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[json.dumps(['mail.send','mail.templates.manage']),member_id])
    check('Mail-only sender has draft access without website permission',member.get(library).status_code==200 and member.get(root+'?view=projects').status_code==403)
    def account(label):
        r=owner.get(accounts);f=soup(r).select_one('[data-mail-account-editor]');data=form_data(f)
        data.update({'label':label,'address':'draft-sender@example.invalid','imap_host':'imap.example.invalid','imap_port':'993','imap_encryption':'ssl','imap_username':'synthetic-incoming','smtp_host':'smtp.example.invalid','smtp_port':'587','smtp_encryption':'starttls','smtp_username':'synthetic-outgoing','imap_password':'Synthetic draft incoming secret','smtp_password':'Synthetic draft outgoing secret','enabled':'1','current_password':OWNER})
        r=owner.post(accounts,data=data);return parse_qs(urlparse(r.url).query)['account_id'][0]
    a=account('Draft sender A');b=account('Draft sender B');a_url=new+'&account_id='+a
    check('Unassigned owner cannot inspect drafts for an arbitrary account',soup(owner.get(a_url)).select_one('[data-mail-draft-editor]') is None)
    def grant(aid,uid):
        detail=accounts+'&account_id='+aid
        return owner.post(detail,data={'csrf':token(owner.get(detail)),'action':'mail_accounts_grant','account_id':aid,'revision':'0','user_id':uid,'can_read':'0','can_send':'1','enabled':'1','days':'0','current_password':OWNER})
    for aid in [a,b]:
        for uid in [owner_id,member_id]:grant(aid,uid)
    check('Assigned mailbox hub exposes local draft actions to sender',('view=mail-drafts' in member.get(root+'?view=mailboxes').text and 'view=mail-draft' in member.get(root+'?view=mailboxes').text))
    def fields(session,url):
        r=session.get(url);f=soup(r).select_one('[data-mail-draft-editor]');assert f is not None,(url,r.text[-800:])
        return urljoin(url,f.get('action') or url),form_data(f)
    def edit(session,url,action='save',**values):
        target,data=fields(session,url);data.update({'action':'mail_drafts_'+action,**values});return session.post(target,data=data)
    payload={'to':'Client+web@EXAMPLE.INVALID','cc':'copy@example.invalid','bcc':'private-draft@example.invalid','subject':'SYNTHETIC_PRIVATE_DRAFT_SUBJECT','body':'SYNTHETIC_PRIVATE_DRAFT_BODY\n</textarea><script>window.draftExecuted=true</script>'}
    target,initial=fields(member,a_url);initial.update({'action':'mail_drafts_save',**payload})
    r=member.post(target,data={**initial,'csrf':'bad'})
    check('Draft save requires CSRF and preserves entered text','session expired' in r.text.lower() and not db('SELECT * FROM ci_mail_drafts') and soup(r).select_one('[name=body]').text==payload['body'])
    r=member.post(target,data=initial);did=parse_qs(urlparse(r.url).query)['draft_id'][0];detail=new+'&draft_id='+did
    check('HTTP creates author-owned local draft with separate recipient groups',db('SELECT owner_id,to_json,cc_json,bcc_json,revision FROM ci_mail_drafts WHERE id=?',[did])==[{'owner_id':member_id,'to_json':'["Client+web@example.invalid"]','cc_json':'["copy@example.invalid"]','bcc_json':'["private-draft@example.invalid"]','revision':1}])
    before=db('SELECT * FROM ci_mail_draft_revisions');r=member.post(target,data=initial)
    check('Lost create response can replay without duplicating draft or audit history',len(db('SELECT * FROM ci_mail_drafts'))==1 and db('SELECT * FROM ci_mail_draft_revisions')==before and parse_qs(urlparse(r.url).query)['draft_id']==[did])
    denied=owner.get(detail)
    check('Even an owner cannot read another authors draft or history',payload['subject'] not in denied.text and payload['body'] not in denied.text and 'Draft unavailable' in denied.text)
    r=owner.post(detail,data={**initial,'csrf':token(owner.get(library)),'revision':'1','body':'unauthorized overwrite'})
    check('Forged foreign-author edit does not change saved content',db('SELECT body FROM ci_mail_drafts WHERE id=?',[did])[0]['body']==payload['body'] and 'Draft unavailable' in r.text)
    before=db('SELECT * FROM ci_mail_drafts WHERE id=?',[did]);history=db('SELECT * FROM ci_mail_draft_revisions')
    r=edit(member,detail,'preview')
    preview=soup(r).select_one('[data-mail-draft-preview]')
    check('Preview escapes hostile text and leaves saved data unchanged',preview is not None and '<script>' in preview.get_text() and not preview.find('script') and db('SELECT * FROM ci_mail_drafts WHERE id=?',[did])==before and db('SELECT * FROM ci_mail_draft_revisions')==history)
    r=edit(member,detail,subject='é'*200)
    check('Unicode byte error preserves full entered subject and old revision',soup(r).select_one('[data-mail-draft-editor] [name=subject]')['value']=='é'*200 and db('SELECT revision FROM ci_mail_drafts WHERE id=?',[did])==[{'revision':1}])
    r=edit(member,detail,to='client@example.invalid\r\nBcc: injected@example.invalid')
    check('HTTP header injection is refused before any draft write','bare ASCII' in r.text and db('SELECT revision FROM ci_mail_drafts WHERE id=?',[did])==[{'revision':1}])
    target,stale=fields(member,detail);stale.update({'action':'mail_drafts_save','body':'Keep this unsaved conflict text'})
    edit(member,detail,body='Newer saved text')
    r=member.post(target,data=stale);f=soup(r).select_one('[data-mail-draft-editor]')
    check('Stale edit retains text and original version without overwriting current draft',f.select_one('[name=body]').text=='Keep this unsaved conflict text' and f.select_one('[name=revision]')['value']=='1' and db('SELECT body,revision FROM ci_mail_drafts WHERE id=?',[did])==[{'body':'Newer saved text','revision':2}] and 'Open current saved version' in r.text)
    r=edit(member,detail,sender=b+':1')
    check('Switching sender requires explicit confirmation',db('SELECT account_id FROM ci_mail_drafts WHERE id=?',[did])==[{'account_id':a}] and 'Confirm the current sending account' in r.text)
    r=edit(member,detail,sender=b+':1',confirm_sender='1')
    check('Confirmed sender switch is saved with a new draft version',db('SELECT account_id,account_revision,revision FROM ci_mail_drafts WHERE id=?',[did])==[{'account_id':b,'account_revision':1,'revision':3}])
    db('UPDATE ci_mail_accounts SET revision=2 WHERE id=?',[b])
    r=member.get(detail)
    check('Changed sender settings are shown without silently selecting their new revision',soup(r).select_one('[name=sender] option[selected]').has_attr('disabled'))
    r=edit(member,detail,sender=b+':2')
    check('Current sender revision still requires explicit review','Confirm the current sending account' in r.text and db('SELECT revision FROM ci_mail_drafts WHERE id=?',[did])==[{'revision':3}])
    edit(member,detail,sender=b+':2',confirm_sender='1')
    check('Confirmed current sender revision becomes acknowledged',db('SELECT account_revision,revision FROM ci_mail_drafts WHERE id=?',[did])==[{'account_revision':2,'revision':4}])
    for mode in ['grant','account','global']:
        if mode=='grant':db('UPDATE ci_mail_account_grants SET enabled=0 WHERE account_id=? AND user_id=?',[b,member_id])
        elif mode=='account':db('UPDATE ci_mail_accounts SET enabled=0 WHERE id=?',[b])
        else:db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',['[]',member_id])
        r=member.get(detail)
        check('Current access loss removes draft form and content '+mode,soup(r).select_one('[data-mail-draft-editor]') is None and payload['subject'] not in r.text and 'Newer saved text' not in r.text)
        db('UPDATE ci_mail_account_grants SET enabled=1 WHERE account_id=? AND user_id=?',[b,member_id]);db('UPDATE ci_mail_accounts SET enabled=1 WHERE id=?',[b]);db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[json.dumps(['mail.send','mail.templates.manage']),member_id])
    # Create and activate the canonical personal template through its real forms.
    template_url=root+'?view=mail-template';tf=soup(member.get(template_url)).select_one('[data-template-editor]');td=form_data(tf);td.update({'action':'mail_templates_create','name':'Draft insertion fixture','subject':'Hello {{client_name}}','body':'Project {{project_name}} at {{portal_url}}','visibility':'personal'})
    tr=member.post(template_url,data=td);tid=parse_qs(urlparse(tr.url).query)['template_id'][0];submit(member,tr.url,'mail_templates_activate')
    r=edit(member,detail,'insert',template=tid+':2',sample_client_name='A client',sample_project_name='New website')
    check('Template insertion requires explicit replacement of existing text','Confirm replacing' in r.text and soup(r).select_one('[name=body]').text=='Newer saved text')
    r=edit(member,detail,'insert',template=tid+':2',sample_client_name='A client',sample_project_name='New website',replace_text='1')
    f=soup(r).select_one('[data-mail-draft-editor]')
    check('Canonical active template inserts an editable snapshot without saving',f.select_one('[name=subject]')['value']=='Hello A client' and 'New website at '+BASE+'/existing/portal/index.php' in f.select_one('[name=body]').text and db('SELECT body,revision FROM ci_mail_drafts WHERE id=?',[did])==[{'body':'Newer saved text','revision':4}])
    data=form_data(f);data.update({'action':'mail_drafts_save'});member.post(detail,data=data)
    submit(member,tr.url,'mail_templates_archive')
    check('Saved draft remains independent after source template archive',db('SELECT subject FROM ci_mail_drafts WHERE id=?',[did])==[{'subject':'Hello A client'}])
    r=member.get(detail+'&support=fixture');check('Support mode cannot show staff draft content',r.status_code==403 and 'Hello A client' not in r.text)
    client=requests.Session();submit(client,BASE+'/existing/portal/index.php','login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    check('Client and anonymous sessions have no staff draft content',all('Hello A client' not in s.get(detail).text and soup(s.get(detail)).select_one('[data-mail-draft-editor]') is None for s in [client,requests.Session()]))
    # Real rendered form interactions, keyboard access and mobile overflow.
    context=browser.new_context(viewport={'width':1440,'height':1050});context.add_cookies([{'name':c.name,'value':c.value,'url':BASE+'/existing/'} for c in member.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(detail);form=page.locator('[data-mail-draft-editor]');form.locator('[name=body]').fill(payload['body']);form.get_by_role('button',name='Preview draft',exact=True).click()
    expect(page.locator('[data-mail-draft-preview]')).to_be_visible()
    check('Actual browser preview cannot execute textarea-closing content',page.evaluate('window.draftExecuted!==true') and page.locator('[data-mail-draft-preview] script').count()==0)
    form=page.locator('[data-mail-draft-editor]');form.locator('[name=subject]').focus();page.keyboard.press('Tab')
    check('Keyboard moves from subject to message field',form.locator('[name=body]').evaluate('(node)=>node===document.activeElement'))
    page.screenshot(path=str(QA/'Mail-Draft-Desktop.png'),full_page=True);page.set_viewport_size({'width':320,'height':800})
    # Wait for the existing responsive drawer transition before judging the mobile view.
    # Restore the top so full-page capture does not place off-viewport fixed elements in the image.
    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
    page.evaluate("window.scrollTo({top:0,left:0,behavior:'instant'})")
    expect(page.locator('.sidebar-toggle')).to_be_in_viewport()
    check('Mobile draft editor has settled closed navigation',page.locator('.sidebar-toggle').get_attribute('aria-expanded')=='false' and page.locator('.admin-sidebar').evaluate('(node)=>node.getBoundingClientRect().right<=0'))
    check('Compose form fits narrow mobile viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    page.screenshot(path=str(QA/'Mail-Draft-Mobile.png'),full_page=True)
    form.get_by_role('button',name='Save draft',exact=True).click();expect(page.locator('.notice.success')).to_contain_text('Draft saved locally')
    check('Actual browser saves current entered content',db('SELECT body FROM ci_mail_drafts WHERE id=?',[did])[0]['body']==payload['body'])
    check('Draft UI has no browser errors',not errors);context.close()
    stale_target,discard_race=fields(member,detail);discard_race.update({'action':'mail_drafts_save','subject':'Unsaved after another tab discarded','body':'Preserve my unsaved text </textarea><script>discardRace=true</script>'})
    r=submit(member,detail,'mail_drafts_discard')
    check('HTTP discard retains content and metadata but removes active listing','Draft discarded locally' in r.text and db('SELECT status,body FROM ci_mail_drafts WHERE id=?',[did])==[{'status':'discarded','body':payload['body']}] and soup(member.get(detail)).select_one('[data-mail-draft-editor]') is None)
    check('Discarded draft is readable only through retained author view','Discarded draft' in member.get(detail).text and 'Hello A client' not in owner.get(detail).text)
    discarded_before=db('SELECT * FROM ci_mail_drafts WHERE id=?',[did]);discard_history=db('SELECT * FROM ci_mail_draft_revisions')
    r=member.post(stale_target,data=discard_race);unsaved=soup(r).select_one('[data-mail-draft-unsaved]')
    check('Concurrent discard preserves escaped unsaved text without restoring or changing the saved record',unsaved is not None and unsaved.select_one('[name=subject]')['value']==discard_race['subject'] and unsaved.select_one('textarea').text==discard_race['body'] and not unsaved.find('script') and soup(r).select_one('[data-mail-draft-editor]') is None and db('SELECT * FROM ci_mail_drafts WHERE id=?',[did])==discarded_before and db('SELECT * FROM ci_mail_draft_revisions')==discard_history)
    metadata=json.dumps(db('SELECT * FROM ci_mail_draft_revisions')+db("SELECT * FROM ci_audit WHERE action LIKE 'mail_drafts.%'"))
    check('Draft audit/history excludes private message and Bcc text',all(x not in metadata for x in [payload['subject'],'SYNTHETIC_PRIVATE_DRAFT_BODY','private-draft@example.invalid']))
    for path in ['app/mail/drafts.php','admin/controllers/mail_drafts.php','admin/views/mail_drafts.php','database/schema/mail_drafts_schema.php','database/migrations/mail_drafts_migrate.php','includes/foundation/mail-compose/DraftContent.php']:
        check('HTTP denies direct draft internal '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    check('Every draft workflow performs no mailbox/provider or system-mail delivery',inbox_server.connections==connections and provider_server.calls==provider_calls and (QA/'mail-capture.txt').read_bytes()==capture)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[original,member_id])
