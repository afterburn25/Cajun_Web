"""Actual recurring notices, personal reads, private bell and exact source navigation."""
import json
from urllib.parse import urlparse, parse_qs
import requests
from release_cases import db
from staff_cases import OWNER


def run_browser(env, browser, owner, client, foreign, job, cid, schedule, capture):
    base, check, soup, submit = (env[k] for k in ['BASE', 'check', 'soup', 'submit'])
    admin, portal = base+'/existing/admin/index.php', base+'/existing/portal/index.php'
    staff_center, client_center = admin+'?view=activity', portal+'?activity=1'
    endpoint = base+'/existing/api/activity.php'
    if not db("SELECT value FROM ci_settings WHERE name='activity_schema_version' AND value='1'"):
        submit(owner, staff_center, 'activity_setup', {'password': OWNER})
    check('Recurring notifications reuse explicitly prepared activity storage', db("SELECT value FROM ci_settings WHERE name='activity_schema_version'")==[{'value':'1'}])
    events = db('SELECT id,actor_staff_id,actor_client_id FROM ci_recurring_billing_events WHERE schedule_id=? ORDER BY revision', [schedule])
    offered, changed = 'recurring-offer:'+schedule, 'recurring-event:'+events[1]['id']
    consent, ended = 'recurring-event:'+events[0]['id'], 'recurring-event:'+events[-1]['id']
    response = client.get(client_center)
    check('Actual client center includes recurring offers and staff changes', offered in response.text and changed in response.text and consent not in response.text and ended not in response.text)
    check('Recurring center escapes project title and omits agreement bodies', 'Recurring browser &lt;project&gt;' in response.text and '<project>' not in response.text and 'Second line' not in response.text and 'Pause by agreement' not in response.text)
    staff_response = owner.get(staff_center)
    check('Actual staff center includes client recurring decisions without own notices', consent in staff_response.text and ended in staff_response.text and offered not in staff_response.text and changed not in staff_response.text)
    business = {t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['recurring_billing_sources','recurring_billing_schedules','recurring_billing_events','recurring_billing_invoices','studio_invoices','invoice_payments']}
    before_reads = db('SELECT * FROM ci_activity_reads ORDER BY id')
    for audience, session, center, key, prefix in [('client',client,client_center,offered,'recurring-offer-'),('client',client,client_center,changed,'recurring-event-'),('staff',owner,staff_center,consent,'recurring-event-')]:
        opened = session.get(center+'&open='+key)
        anchor = prefix+key.split(':')[1]
        check('HTTP recurring '+audience+' notice opens its exact '+prefix+' anchor', opened.status_code==200 and urlparse(opened.url).fragment==anchor and soup(opened).find(id=anchor) is not None and parse_qs(urlparse(opened.url).query).get('schedule')==[schedule])
    check('HTTP opening recurring notices preserves personal read status', db('SELECT * FROM ci_activity_reads ORDER BY id')==before_reads)
    check('Foreign client cannot open a known recurring notice', foreign.get(client_center+'&open='+offered).status_code==403)
    check('Client cannot read staff recurring bell through its client login', client.get(endpoint+'?audience=admin').status_code==204)
    count = client.get(endpoint+'?audience=client', headers={'Origin':'https://untrusted.invalid'})
    check('Recurring private bell contains count and own destination only', count.status_code==200 and set(count.json())=={'count','capped','url'} and count.json()['url']==client_center and count.json()['count']>0 and 'no-store' in count.headers['Cache-Control'] and count.headers.get('Vary')=='Cookie' and 'Access-Control-Allow-Origin' not in count.headers)

    def mark(session, center, keys, state='read', **overrides):
        page=session.get(center)
        if soup(page).select_one('input[name=csrf],meta[name=csrf-token]') is None:
            page=session.get(portal)
        token=env['token'](page)
        return session.post(center,data={'action':'activity_mark','csrf':token,'keys':json.dumps(keys),'state':state,**overrides})

    mark(client,client_center,[offered],csrf='bad')
    check('Recurring read HTTP action requires current CSRF',db('SELECT * FROM ci_activity_reads ORDER BY id')==before_reads)
    mark(foreign,client_center,[offered])
    check('Foreign recurring mark cannot create a read entry',db('SELECT * FROM ci_activity_reads ORDER BY id')==before_reads)
    mark(client,client_center,[offered]); first=db('SELECT * FROM ci_activity_reads ORDER BY id')
    mark(client,client_center,[offered])
    check('Actual recurring read replay retains one original personal row',db('SELECT * FROM ci_activity_reads ORDER BY id')==first and len(first)==len(before_reads)+1)
    check('Recurring unread filter hides only the reviewed offer',offered not in client.get(client_center+'&filter=unread').text and changed in client.get(client_center+'&filter=unread').text)
    mark(client,client_center,[offered],'unread')
    check('Explicit recurring unread restores original notice',offered in client.get(client_center+'&filter=unread').text)
    mark(owner,staff_center,[consent])
    check('Staff recurring read applies only to that staff decision notice',bool(db("SELECT id FROM ci_activity_reads WHERE actor_type='admin' AND event_key=?",[consent])) and not db("SELECT id FROM ci_activity_reads WHERE actor_type='client' AND event_key=?",[consent]))
    foreign_focus = portal+'?recurring=1&job='+str(job)+'&schedule='+schedule+'&focus_event='+'f'*32
    unavailable=client.get(foreign_focus)
    check('Unavailable recurring focus refuses rather than opening another decision',unavailable.status_code==200 and 'The original recurring decision is unavailable.' in unavailable.text and soup(unavailable).select_one('[id^=recurring-event-],[id^=recurring-offer-]') is None)
    check('Recurring activity HTTP interactions never change billing or ledger rows',all(db('SELECT * FROM ci_'+t+' ORDER BY id')==rows for t,rows in business.items()))
    capture(owner,staff_center,'activity-staff');capture(client,client_center,'activity-client')
    context=browser.new_context(viewport={'width':320,'height':1000})
    context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=context.new_page();page.goto(base+'/existing/');page.wait_for_load_state('networkidle')
    check('Public administrator bell opens staff center containing recurring decisions',page.locator('.activity-bell').is_visible() and page.locator('.activity-bell').get_attribute('href')==staff_center)
    page.locator('.activity-bell').click();page.wait_for_load_state('networkidle')
    article=page.locator('[data-event-key="'+ended+'"]');article.locator('h2 a').click();page.wait_for_load_state('networkidle')
    check('Actual public bell and activity click reach exact client decision',urlparse(page.url).fragment=='recurring-event-'+events[-1]['id'] and page.locator('#recurring-event-'+events[-1]['id']).count()==1)
    context.close()
    anonymous=browser.new_context(viewport={'width':320,'height':1000});page=anonymous.new_page();page.goto(base+'/existing/');page.wait_for_load_state('networkidle')
    check('Anonymous visitors receive no recurring staff count or public bell',requests.get(endpoint+'?audience=admin').status_code==204 and page.locator('.activity-bell').count()==0 and page.locator('[data-activity-bell]').is_hidden())
    anonymous.close()
