"""Actual financial notifications reuse the workspace center and exact project history."""
import json
from urllib.parse import urlparse,parse_qs
import requests
from playwright.sync_api import expect
from release_cases import db,chosen_form
from staff_cases import OWNER


def run(env,browser,owner,client,other,job,cid):
    base,qa,check,soup,token,submit=(env[k] for k in ['BASE','QA','check','soup','token','submit'])
    admin,portal=base+'/existing/admin/index.php',base+'/existing/portal/index.php'
    staff_center,client_center=admin+'?view=activity',portal+'?activity=1'
    if not db("SELECT value FROM ci_settings WHERE name='activity_schema_version' AND value='1'"):
        submit(owner,staff_center,'activity_setup',{'password':OWNER})
    events=db('SELECT id,kind,sequence FROM ci_design_payment_events WHERE job_id=? ORDER BY sequence',[job])
    offered=next(e for e in events if e['kind']=='offered');decision=next(e for e in events if e['kind']=='accepted');issued=next(e for e in events if e['kind']=='issued')
    offer_key,decision_key='financial:'+offered['id'],'financial:'+decision['id']
    original=db('SELECT title FROM ci_studio_jobs WHERE id=?',[job])[0]['title']
    db('UPDATE ci_studio_jobs SET title=? WHERE id=?',['Financial <img src=x onerror=alert(1)>',job])
    def reads():return db('SELECT * FROM ci_activity_reads WHERE event_key=? ORDER BY id',[offer_key])
    before=reads();r=client.get(client_center)
    check('Actual client activity lists financial offers with escaped project title',offer_key in r.text and '&lt;img src=x onerror=alert(1)&gt;' in r.text and '<img src=x onerror=alert(1)>' not in r.text)
    check('Financial activity feed does not expose agreement terms or private feedback','Exact financial terms' not in r.text and 'Please split the payment.' not in r.text)
    check('Client activity omits their own financial decision and duplicate issuance',decision_key not in r.text and 'financial:'+issued['id'] not in r.text)
    check('Foreign client cannot list or navigate financial activity',offer_key not in other.get(client_center).text and other.get(client_center+'&open='+offer_key).status_code==403)
    r=client.get(client_center+'&open='+offer_key)
    check('Financial notification reaches original offer after subsequent acceptance and issuance',r.status_code==200 and parse_qs(urlparse(r.url).query).get('financial_page')==[str(offered['sequence']//10+1)] and urlparse(r.url).fragment=='financial-event-'+offered['id'] and soup(r).select_one('#financial-event-'+offered['id']) is not None)
    check('Opening financial history preserves client read state',reads()==before)
    bad=client.post(client_center,data={'csrf':'wrong','action':'activity_mark','keys':json.dumps([offer_key]),'state':'read'})
    check('Financial personal read action rejects forged CSRF','session expired' in bad.text.lower() and reads()==before)
    def mark(state):return client.post(client_center,data={'csrf':token(client.get(client_center)),'action':'activity_mark','keys':json.dumps([offer_key]),'state':state})
    mark('read');mark('read')
    check('Financial personal read request is idempotent over actual HTTP',len(reads())==1 and reads()[0]['actor_type']=='client' and reads()[0]['actor_id']==cid)
    check('Unread financial filter excludes only explicitly read offer',offer_key not in client.get(client_center+'&filter=unread').text)
    mark('unread');check('Financial unread action restores the same offer',offer_key in client.get(client_center+'&filter=unread').text and not reads())
    r=owner.get(staff_center)
    check('Actual financial staff activity receives client consent without own offer',decision_key in r.text and offer_key not in r.text and 'Client accepted financial terms' in r.text)
    r=owner.get(staff_center+'&open='+decision_key)
    check('Staff financial notification opens exact client consent history',urlparse(r.url).fragment=='financial-event-'+decision['id'] and soup(r).select_one('#financial-event-'+decision['id']) is not None)
    endpoint=base+'/existing/api/activity.php'
    r=owner.get(endpoint+'?audience=admin')
    check('Financial workspace count exposes only private count metadata',r.status_code==200 and set(r.json())=={'count','capped','url'} and 'no-store' in r.headers['Cache-Control'] and 'Access-Control-Allow-Origin' not in r.headers)
    check('Anonymous financial activity count contains no private payload',requests.get(endpoint+'?audience=admin').status_code==204 and requests.get(endpoint+'?audience=client').content==b'')
    contexts=[];errors=[]
    try:
        for session,role,url,key in [(client,'Client',client_center,offer_key),(owner,'Staff',staff_center,decision_key)]:
            context=browser.new_context(viewport={'width':1440,'height':1000},device_scale_factor=1,reduced_motion='reduce');contexts.append(context)
            context.add_cookies([{'name':c.name,'value':c.value,'domain':urlparse(base).hostname,'path':c.path or '/'} for c in session.cookies])
            page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(url)
            entry=page.locator('[data-event-key="'+key+'"]');expect(entry).to_be_visible()
            for width,label in [(1440,'Desktop'),(320,'Mobile')]:
                page.set_viewport_size({'width':width,'height':1000});page.screenshot(path=str(qa/('Financial-Activity-'+role+'-'+label+'.png')),full_page=True)
                check('Financial activity '+role.lower()+' fits '+str(width)+' pixels',page.evaluate('document.documentElement.scrollWidth<=innerWidth'))
            entry.get_by_role('link').click();expect(page.locator('#financial-event-'+key[10:])).to_be_visible()
            check('Browser financial '+role.lower()+' notification targets exact retained event',urlparse(page.url).fragment=='financial-event-'+key[10:])
        check('Financial activity browser has no script errors',not errors)
    finally:
        for context in contexts:context.close()
    response=chosen_form(owner,admin+'?view=support','support_start','client_id',cid,{})
    sid=parse_qs(urlparse(response.url).query)['support'][0]
    check('Support cannot borrow client financial activity or personal read state',owner.get(client_center+'&support='+sid).status_code==403 and owner.post(client_center+'&support='+sid,data={'csrf':token(owner.get(portal+'?support='+sid)),'action':'activity_mark','keys':json.dumps([offer_key]),'state':'read'}).status_code==403 and reads()==before)
    submit(owner,portal+'?support='+sid,'support_end')
    check('HTTP denies direct financial activity implementation',requests.get(base+'/existing/app/billing/design_payment_activity.php').status_code==404)
    db('UPDATE ci_studio_jobs SET title=? WHERE id=?',[original,job])
