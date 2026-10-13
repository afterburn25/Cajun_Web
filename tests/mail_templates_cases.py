"""Actual A-07 admin workflows, private visibility, escaping and draft preservation."""
from urllib.parse import parse_qs,urlparse
import requests,json
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit']})
    db('DELETE FROM ci_rate_limits')
    check('Fresh install enables empty template library',db("SELECT value FROM fresh_settings WHERE name='mail_templates_schema_version'")==[{'value':'1'}] and db('SELECT * FROM fresh_mail_templates')==[])
    root=BASE+'/existing/admin/index.php';library=root+'?view=mail-templates';new=root+'?view=mail-template'
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    before=db('SELECT * FROM ci_studio_jobs');settings=db('SELECT * FROM ci_settings')
    r=submit(owner,library,'mail_templates_setup',{'current_password':OWNER,'csrf':'wrong'})
    check('Template setup rejects invalid CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='mail_templates_schema_version'"))
    r=submit(owner,library,'mail_templates_setup',{'current_password':'wrong'})
    check('Template setup rejects wrong owner password','current password' in r.text.lower())
    r=submit(owner,library,'mail_templates_setup',{'current_password':OWNER})
    check('HTTP template setup preserves business data',r.status_code==200 and 'Template library enabled' in r.text and db('SELECT * FROM ci_studio_jobs')==before)
    after={s['name']:s['value'] for s in db('SELECT * FROM ci_settings')}
    check('Template setup preserves every existing setting',all(after[s['name']]==s['value'] for s in settings))
    def editor(session,url,action,**values):
        response=session.get(url);form=soup(response).select_one('[data-template-editor]');assert form is not None
        data=form_data(form);data.update({'action':action,**values});return session.post(url,data=data)
    create={'name':'Welcome shared','subject':'Hello {{client_name}}','body':'Your {{project_name}} at {{portal_url}}','visibility':'shared'}
    r=editor(owner,new,'mail_templates_create',**create);tid=parse_qs(urlparse(r.url).query)['template_id'][0];detail=new+'&template_id='+tid
    check('HTTP creates a saved draft and first revision',r.status_code==200 and 'Welcome shared' in r.text and len(db('SELECT * FROM ci_mail_template_revisions WHERE template_id=?',[tid]))==1)
    personal=editor(owner,new,'mail_templates_create',name='Owner private needle',subject='Private subject',body='Personal secret needle',visibility='personal')
    private_url=personal.url;private_id=parse_qs(urlparse(private_url).query)['template_id'][0]
    member_id=db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id']
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Unassigned staff cannot open template library',member.get(library).status_code==403)
    original=db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[member_id])[0]['permissions']
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[json.dumps(json.loads(original)+['mail.templates.manage']),member_id])
    check('Explicit grant opens shared template library',member.get(library).status_code==200 and 'Welcome shared' in member.get(library).text)
    check('Personal templates absent from foreign listing search and detail','Owner private needle' not in member.get(library+'&search=Owner').text and 'Personal secret needle' not in member.get(private_url).text)
    r=member.post(detail,data={'csrf':token(member.get(detail)),'action':'mail_templates_edit','template_id':tid,'revision':'1','name':'Bad','subject':'Bad','body':'Bad'})
    check('Foreign staff cannot edit shared template',db('SELECT revision,name FROM ci_mail_templates WHERE id=?',[tid])==[{'revision':1,'name':'Welcome shared'}] and 'Only its creator' in r.text)
    r=submit(member,detail,'mail_templates_duplicate',{'copy_name':'Member personal copy'})
    check('Shared template duplicates into separate personal draft',r.status_code==200 and 'Member personal copy' in r.text and db("SELECT visibility,owner_id,status FROM ci_mail_templates WHERE name='Member personal copy'")==[{'visibility':'personal','owner_id':member_id,'status':'draft'}])
    r=member.post(private_url,data={'csrf':token(member.get(library)),'action':'mail_templates_preview','template_id':private_id})
    check('Tampered preview cannot reveal personal content','Personal secret needle' not in r.text)
    before=db('SELECT * FROM ci_mail_templates WHERE id=?',[tid]);history=db('SELECT * FROM ci_mail_template_revisions WHERE template_id=?',[tid])
    r=editor(owner,detail,'mail_templates_edit',name='Preserved invalid draft',subject='Bad {{ UNKNOWN }}',body='Keep my unsaved text')
    check('Validation failure retains draft text without replacing saved version','Keep my unsaved text' in r.text and 'Preserved invalid draft' in r.text and db('SELECT * FROM ci_mail_templates WHERE id=?',[tid])==before)
    r=editor(owner,detail,'mail_templates_preview',**create,sample_client_name='<img src=x onerror=alert(1)>',sample_project_name='{{client_name}}')
    preview=soup(r).select_one('[data-template-preview]')
    check('Preview escapes HTML and keeps inserted values literal',preview is not None and preview.find('img') is None and '<img src=x onerror=alert(1)>' in preview.get_text() and '{{client_name}}' in preview.get_text())
    check('HTTP preview neither changes records nor history',db('SELECT * FROM ci_mail_templates WHERE id=?',[tid])==before and db('SELECT * FROM ci_mail_template_revisions WHERE template_id=?',[tid])==history)
    r=editor(owner,detail,'mail_templates_preview',**create)
    check('Missing preview values are named','Missing sample values: client_name, project_name' in r.text)
    stale={'csrf':token(owner.get(detail)),'action':'mail_templates_edit','template_id':tid,'revision':'1','name':'Stale submitted','subject':'Stale','body':'Unsaved conflict text'}
    submit(owner,detail,'mail_templates_activate')
    r=owner.post(detail,data=stale)
    check('Stale edit keeps submitted text and does not silently advance revision','Unsaved conflict text' in r.text and 'This template changed' in r.text and soup(r).select_one('[data-template-editor] [name=revision]')['value']=='1' and db('SELECT revision FROM ci_mail_templates WHERE id=?',[tid])[0]['revision']==2)
    r=submit(owner,detail,'mail_templates_archive');check('HTTP archive retains prior revisions','archived' in r.text and len(db('SELECT * FROM ci_mail_template_revisions WHERE template_id=?',[tid]))==3)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[original,member_id])
    check('Removing template permission takes effect immediately',member.get(library).status_code==403)
    for path in ['app/mail/templates.php','database/schema/mail_templates_schema.php','database/migrations/mail_templates_migrate.php','admin/controllers/mail_templates.php','admin/views/mail_templates.php','includes/foundation/email-templates/EmailTemplate.php']:
        check('Direct HTTP denial for template internal '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard')
    page.goto(new);page.locator('[name=name]').fill('Browser welcome');page.locator('[name=subject]').fill('Welcome {{client_name}}');page.locator('[name=body]').fill('We are ready to discuss {{project_name}}. Sign in: {{portal_url}}');page.get_by_role('button',name='Save draft',exact=True).click();page.wait_for_url('**template_id=*')
    check('Chromium creates template draft','Browser welcome' in page.content())
    page.locator('[name=sample_client_name]').fill('Sample client');page.locator('[name=sample_project_name]').fill('New website');page.get_by_role('button',name='Preview message',exact=True).click();expect(page.locator('[data-template-preview]')).to_be_visible()
    check('Chromium preview resolves sample fields','Welcome Sample client' in page.locator('[data-template-preview]').inner_text())
    page.screenshot(path=str(QA/'Email-Templates-Desktop.png'),full_page=True)
    page.get_by_role('button',name='Activate saved draft',exact=True).click();expect(page.locator('.status-badge').first).to_have_text('active')
    browser_id=parse_qs(urlparse(page.url).query)['template_id'][0]
    check('Chromium activates saved draft',db('SELECT status FROM ci_mail_templates WHERE id=?',[browser_id])==[{'status':'active'}])
    page.set_viewport_size({'width':390,'height':844});page.screenshot(path=str(QA/'Email-Templates-Mobile.png'),full_page=True)
    check('Template page fits mobile width',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Template page has no browser errors',not errors);context.close()
