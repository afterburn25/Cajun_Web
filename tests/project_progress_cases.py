"""Actual project editor, history and activity HTTP/browser acceptance."""
import json
from urllib.parse import urljoin, urlparse, parse_qs
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['BASE','QA','check','soup','token','form_data','form_for','submit']})
    db('DELETE FROM ci_rate_limits')
    admin, portal = BASE+'/existing/admin/index.php', BASE+'/existing/portal/index.php'
    owner = requests.Session()
    submit(owner, admin+'?view=login', 'login', {'email':'owner@example.invalid','password':OWNER})
    clients=[]
    for n in [1,2]:
        email='history-client'+str(n)+'@example.invalid'; password='Synthetic history client passphrase'
        chosen_form(owner,admin+'?view=studio-clients','studio_client','id',0,{'name':'History client '+str(n),'email':email})
        cid=db('SELECT id FROM ci_studio_clients WHERE email=?',[email])[0]['id']
        invitation=chosen_form(owner,admin+'?view=studio-clients','studio_invite','id',cid,{})
        link=soup(invitation).select_one('.notice.info a')['href'];client=requests.Session()
        submit(client,link,'activate',{'password':password,'confirm_password':password})
        submit(client,portal,'login',{'email':email,'password':password});clients.append((client,cid))
    client,cid=clients[0];other,other_cid=clients[1]
    create={'client_id':str(cid),'title':'History acceptance project','summary':'Existing summary','stage':'design','milestones':'Content supplied\r\nDesign underway'}
    r=submit(owner,admin+'?view=studio-clients','studio_job',create)
    job=db('SELECT id FROM ci_studio_jobs WHERE title=?',[create['title']])[0]['id'];staff_url=admin+'?view=studio-job&id='+str(job);client_url=portal+'?job='+str(job)
    check('Existing project starts without invented progress history','Dated history is not enabled' in r.text and not db("SELECT value FROM ci_settings WHERE name='project_progress_schema_version'"))
    staff_list=admin+'?view=staff';staff_password='Synthetic history manager passphrase'
    chosen_form(owner,staff_list,'staff_save','id',0,{'name':'History manager','email':'history-manager@example.invalid','role':'administrator','enabled':'1','permissions[]':['clients.manage'],'owner_password':OWNER})
    manager_id=db("SELECT id FROM ci_users WHERE email='history-manager@example.invalid'")[0]['id']
    invitation=chosen_form(owner,staff_list,'staff_invite','id',manager_id,{'owner_password':OWNER});link=soup(invitation).select_one('[data-staff-invite]')['href'];manager=requests.Session()
    submit(manager,admin+'?view=staff-setup','staff_activate',{'token':parse_qs(urlparse(link).fragment)['token'][0],'password':staff_password,'confirm_password':staff_password})
    submit(manager,admin+'?view=login','login',{'email':'history-manager@example.invalid','password':staff_password})
    setup=admin+'?view=studio-upgrade'
    manager.post(setup,data={'csrf':token(manager.get(staff_url)),'action':'project_progress_setup','password':OWNER,'confirm':'1'})
    check('Client-manager permission cannot perform owner history setup',not db("SELECT value FROM ci_settings WHERE name='project_progress_schema_version'"))
    for changes,name in [({'password':OWNER,'confirm':'1','csrf':'bad'},'CSRF'),({'password':'wrong','confirm':'1'},'owner password'),({'password':OWNER},'backup confirmation')]:
        submit(owner,setup,'project_progress_setup',changes)
        check('Project history setup requires '+name,not db("SELECT value FROM ci_settings WHERE name='project_progress_schema_version'"))
    r=submit(owner,setup,'project_progress_setup',{'password':OWNER,'confirm':'1'})
    check('Actual owner form prepares additive project history',db("SELECT value FROM ci_settings WHERE name='project_progress_schema_version'")==[{'value':'1'}] and 'Dated project updates are enabled' in r.text)
    check('Prepared legacy project is honestly untracked','History has not started for this project' in client.get(client_url).text and not db('SELECT id FROM ci_studio_progress_events WHERE job_id=?',[job]))
    original=form_data(form_for(owner.get(staff_url),'studio_job'));first={**original,'stage':'review','summary':'Client-visible <script>literal</script>\r\nSecond line'}
    owner.post(staff_url,data={**first,'csrf':'bad'})
    check('Invalid CSRF cannot append a project baseline or update',not db('SELECT id FROM ci_studio_progress_events WHERE job_id=?',[job]))
    r=owner.post(staff_url,data=first);first_id=first['request_id']
    check('First actual edit atomically records honest baseline and change',len(db('SELECT * FROM ci_studio_progress_events WHERE job_id=?',[job]))==2 and 'Tracking started' in r.text and 'Stage' in r.text and 'Summary' in r.text)
    check('Project editor escapes saved client-visible markup','&lt;script&gt;literal&lt;/script&gt;' in r.text and '<script>literal</script>' not in r.text)
    check('Project detail is private and refuses referrer leakage',r.headers.get('Cache-Control')=='no-store, private' and r.headers.get('Referrer-Policy')=='no-referrer')
    stale=form_data(form_for(owner.get(staff_url),'studio_job'))
    current=submit(owner,staff_url,'studio_job',{'stage':'development','summary':'Newer saved details'});later_count=len(db('SELECT id FROM ci_studio_progress_events WHERE job_id=?',[job]))
    r=owner.post(staff_url,data=first)
    check('Original accepted update replay after later edits preserves current fields',len(db('SELECT id FROM ci_studio_progress_events WHERE job_id=?',[job]))==later_count and db('SELECT stage,summary FROM ci_studio_jobs WHERE id=?',[job])==[{'stage':'development','summary':'Newer saved details'}] and 'already saved' in r.text)
    stale['title']='Unsaved <img src=x onerror=alert(1)> draft';stale['summary']='Exact unsaved multi-line\r\nKeep this draft'
    r=owner.post(staff_url,data=stale);retained=form_data(form_for(r,'studio_job'))
    check('Stale actual form retains escaped draft original request and old token',retained['title']==stale['title'] and retained['expected']==stale['expected'] and retained['request_id']==stale['request_id'] and 'Current saved project' in r.text and '<img src=x onerror=' not in r.text)
    r=owner.post(staff_url,data=retained)
    check('Retrying retained stale form cannot become an overwrite',len(db('SELECT id FROM ci_studio_progress_events WHERE job_id=?',[job]))==later_count and db('SELECT title FROM ci_studio_jobs WHERE id=?',[job])==[{'title':create['title']}])
    newform=form_data(form_for(owner.get(admin+'?view=studio-clients'),'studio_job'));newform.update(create,title='Created with history')
    owner.post(admin+'?view=studio-clients',data=newform);newjob=db('SELECT id FROM ci_studio_jobs WHERE title=?',['Created with history'])[0]['id']
    submit(owner,admin+'?view=studio-job&id='+str(newjob),'studio_job',{'stage':'launched'})
    owner.post(admin+'?view=studio-clients',data=newform)
    check('Original creation retry cannot duplicate or reset a later project',len(db('SELECT id FROM ci_studio_jobs WHERE title=?',['Created with history']))==1 and db('SELECT stage FROM ci_studio_jobs WHERE id=?',[newjob])==[{'stage':'launched'}] and len(db('SELECT id FROM ci_studio_progress_events WHERE job_id=?',[newjob]))==2)
    r=client.get(client_url)
    check('Client sees dated history without internal request or staff metadata','Project history' in r.text and 'Tracking started' in r.text and 'name="expected"' not in r.text and 'staff_id' not in r.text)
    check('Another client cannot open exact project history',other.get(client_url+'&focus_progress='+first_id).status_code==404)
    if not db("SELECT value FROM ci_settings WHERE name='activity_schema_version'"):
        submit(owner,admin+'?view=activity','activity_setup',{'password':OWNER})
    activity=portal+'?activity=1';key='progress:'+first_id;before=db('SELECT * FROM ci_activity_reads ORDER BY id')
    r=client.get(activity+'&open='+key)
    check('Activity opens exact historical event and page without marking read','focus_progress='+first_id in r.url and '#progress-'+first_id in r.url and soup(r).select_one('#progress-'+first_id) is not None and db('SELECT * FROM ci_activity_reads ORDER BY id')==before)
    response=client.get(BASE+'/existing/api/activity.php?audience=client')
    check('Private client bell includes project progress',response.status_code==200 and response.json()['count']>=3 and response.headers.get('Cache-Control')=='no-store, private')
    mark={'action':'activity_mark','keys':json.dumps([key]),'state':'read','csrf':token(client.get(activity))}
    client.post(activity,data={**mark,'csrf':'bad'})
    check('Progress read state requires actual client CSRF',db('SELECT * FROM ci_activity_reads ORDER BY id')==before)
    client.post(activity,data=mark)
    check('Explicit progress read affects only real client and selected event',db('SELECT actor_type,actor_id,event_key FROM ci_activity_reads WHERE event_key=?',[key])==[{'actor_type':'client','actor_id':cid,'event_key':key}])
    for n in range(32):submit(owner,staff_url,'studio_job',{'summary':'Dated update '+str(n),'stage':'review' if n%2 else 'development'})
    r=client.get(client_url);check('Real history pagination keeps latest page bounded','Page 2 of 2' in r.text and len(soup(r).select('.progress-event'))==5)
    r=client.get(activity+'&open='+key)
    check('Old activity focus resolves beyond the latest history page','Page 1 of 2' in r.text and soup(r).select_one('#progress-'+first_id) is not None)
    check('History UI preserves reviews conversation invoices and files',all(x in r.text for x in ['Designs & proposals','Private project files','Invoices & payment instructions']))
    contexts=[]
    def mobile(page, name):
        page.set_viewport_size({'width':320,'height':900})
        if page.locator('.sidebar-toggle').count():
            page.evaluate('window.scrollTo({top:0,left:0,behavior:"instant"})')
            expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            page.locator('.sidebar-toggle').click();expect(page.locator('.admin-sidebar')).to_be_in_viewport()
            page.keyboard.press('Escape');expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            check('Project '+name+' mobile navigation opens and closes',page.locator('.sidebar-toggle').get_attribute('aria-expanded')=='false')
        page.screenshot(path=str(QA/('Project-History-'+name+'-Mobile.png')),full_page=True)
    try:
        for session,role,url in [(owner,'Staff',staff_url),(client,'Client',client_url)]:
            ctx=browser.new_context(viewport={'width':1440,'height':1000},device_scale_factor=1,reduced_motion='reduce');contexts.append(ctx)
            ctx.add_cookies([{'name':c.name,'value':c.value,'domain':urlparse(BASE).hostname,'path':c.path or '/'} for c in session.cookies]);p=ctx.new_page();p.goto(url)
            expect(p.locator('#progress-title')).to_be_visible();p.screenshot(path=str(QA/('Project-History-'+role+'-Desktop.png')),full_page=True)
            check('Browser renders actual '+role.lower()+' dated history',p.locator('.progress-event').count()==5)
            mobile(p,role)
            check('Project history '+role.lower()+' fits320px viewport',p.evaluate('document.documentElement.scrollWidth<=innerWidth'))
        p=contexts[0].pages[0];p.set_viewport_size({'width':1440,'height':1000});p.goto(staff_url)
        form=p.locator('.progress-editor form');form.locator('[name=title]').fill('Browser unsaved <review>');form.locator('[name=summary]').fill('Browser draft stays visible')
        submit(owner,staff_url,'studio_job',{'summary':'Concurrent browser winner'})
        form.locator('button').click();expect(p.get_by_role('heading',name='Current saved project')).to_be_visible();expect(p.locator('.progress-editor [name=title]')).to_have_value('Browser unsaved <review>')
        check('Actual browser conflict retains unsaved draft',p.locator('.progress-editor [name=summary]').input_value()=='Browser draft stays visible')
        p.screenshot(path=str(QA/'Project-History-Conflict-Desktop.png'),full_page=True);mobile(p,'Conflict')
        check('Retained browser conflict fits320px viewport',p.evaluate('document.documentElement.scrollWidth<=innerWidth'))
    finally:
        for context in contexts:context.close()
    def retained():
        return [db('SELECT * FROM ci_'+name+' ORDER BY '+order) for name,order in [('studio_jobs','id'),('studio_progress_events','job_id,revision'),('studio_progress_requests','id'),('studio_progress_heads','id'),('activity_reads','id')]]
    chosen_form(owner,admin+'?view=support','support_policy','client_id',cid,{'allowed':'1','owner_password':OWNER})
    response=chosen_form(owner,admin+'?view=studio-clients','support_start','client_id',cid,{})
    sid=parse_qs(urlparse(response.url).query)['support'][0];support_url=portal+'?support='+sid+'&job='+str(job);saved=retained();response=owner.get(support_url)
    check('Scoped support sees public dated history without editor tokens','Project history' in response.text and 'name="expected"' not in response.text and soup(response).select_one('.progress-editor') is None)
    owner.post(support_url,data={'csrf':token(response),'action':'activity_mark','keys':json.dumps([key]),'state':'unread'})
    check('Support cannot change client progress read state',retained()==saved)
    support_edit=form_data(form_for(owner.get(staff_url),'studio_job'));support_edit['summary']='Forbidden support edit'
    response=owner.post(staff_url+'&support='+sid,data=support_edit)
    check('Support selector refuses administrative progress edit and screen',response.status_code==403 and retained()==saved)
    submit(owner,portal+'?support='+sid,'support_end')
    manager_form=form_data(form_for(manager.get(staff_url),'studio_job'));saved=retained()
    chosen_form(owner,staff_list,'staff_save','id',manager_id,{'permissions[]':[],'owner_password':OWNER})
    response=manager.post(staff_url,data={**manager_form,'summary':'Revoked manager attempt'},allow_redirects=False)
    check('Revoked manager cannot submit a previously valid progress form',response.status_code==303 and 'view=login' in response.headers.get('Location','') and retained()==saved)
    submit(manager,admin+'?view=login','login',{'email':'history-manager@example.invalid','password':staff_password})
    check('Renewed login cannot regain revoked progress screens',manager.get(staff_url).status_code==403 and manager.get(admin+'?view=activity&open='+key).status_code==403 and retained()==saved)
    chosen_form(owner,admin+'?view=studio-clients','studio_client','id',cid,{'active':None})
    response=client.get(client_url)
    check('Deactivated client cannot continue reading dated project history','Welcome, ' not in response.text and soup(response).select_one('.project-progress') is None)
    for path in ['app/projects/progress.php','app/projects/progress_forms.php','app/projects/progress_activity.php','includes/layout/project_progress.php']:
        check('HTTP denies direct project internal '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    check('Project progress never creates invoices for the tested projects',not db('SELECT id FROM ci_studio_invoices WHERE job_id IN (?,?)',[job,newjob]))
