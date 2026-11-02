"""Real HTTP/browser planning forms, client dashboard and personal activity."""
import json
from urllib.parse import urljoin, urlparse, parse_qs
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['BASE', 'QA', 'check', 'soup', 'token', 'form_data', 'form_for', 'submit']})
    db('DELETE FROM ci_rate_limits')
    admin, portal = BASE+'/existing/admin/index.php', BASE+'/existing/portal/index.php'
    owner = requests.Session()
    submit(owner, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    clients = []
    for n in [1, 2]:
        email = 'planning-client'+str(n)+'@example.invalid'
        chosen_form(owner, admin+'?view=studio-clients', 'studio_client', 'id', 0, {'name': 'Planning client '+str(n), 'email': email})
        cid = db('SELECT id FROM ci_studio_clients WHERE email=?', [email])[0]['id']
        invitation = chosen_form(owner, admin+'?view=studio-clients', 'studio_invite', 'id', cid, {})
        link = soup(invitation).select_one('.notice.info a')['href']; client = requests.Session()
        submit(client, link, 'activate', {'password': OWNER, 'confirm_password': OWNER})
        submit(client, portal, 'login', {'email': email, 'password': OWNER}); clients.append((client, cid))
    (client, cid), (other, _) = clients
    submit(owner, admin+'?view=studio-clients', 'studio_job', {'client_id': str(cid), 'title': 'Planning acceptance project', 'summary': 'Existing project description', 'stage': 'design', 'milestones': 'Legacy milestone notes'})
    job = db('SELECT id FROM ci_studio_jobs WHERE title=?', ['Planning acceptance project'])[0]['id']
    staff_url, client_url = admin+'?view=studio-job&id='+str(job), portal+'?job='+str(job)
    old_job = db('SELECT * FROM ci_studio_jobs WHERE id=?', [job])
    check('Structured planning begins disabled without fabricated plan', 'An owner can enable structured project planning' in owner.get(staff_url).text)
    setup = admin+'?view=studio-upgrade'
    for values, label in [({'password': OWNER, 'confirm': '1', 'csrf': 'bad'}, 'CSRF'), ({'password': 'wrong', 'confirm': '1'}, 'owner password'), ({'password': OWNER, 'confirm': None}, 'backup confirmation')]:
        submit(owner, setup, 'project_planning_setup', values)
        check('Actual planning setup requires '+label, not db("SELECT value FROM ci_settings WHERE name='project_planning_schema_version'"))
    submit(owner, setup, 'project_planning_setup', {'password': OWNER, 'confirm': '1'})
    r = owner.get(staff_url)
    check('Owner preparation exposes real plan form and honest empty state', form_for(r, 'project_planning_save') is not None and 'has not saved a structured plan' in r.text)
    check('Planning page is private with no referrer leakage', r.headers.get('Cache-Control') == 'no-store, private' and r.headers.get('Referrer-Policy') == 'no-referrer')
    milestones = [{'id': '1'*32, 'title': 'Content approved', 'details': 'Client supplies copy', 'state': 'completed', 'owner': 'client', 'due_date': '2028-02-29', 'depends_on': []}, {'id': '2'*32, 'title': 'Design review', 'details': 'Review the layouts', 'state': 'planned', 'owner': 'studio', 'due_date': None, 'depends_on': ['1'*32]}]
    encoded = json.dumps(milestones, ensure_ascii=False, separators=(',', ':'))
    first = form_data(form_for(r, 'project_planning_save'))
    first.update({'scope': 'Website scope <script>literal</script>', 'assigned_contact': 'Studio coordinator', 'target_date': '2028-03-15', 'client_action': 'Review layout', 'blockers': '', 'milestones_json': encoded})
    owner.post(staff_url, data={**first, 'csrf': 'bad'})
    check('Actual plan save requires CSRF', not db('SELECT id FROM ci_studio_plan_events WHERE job_id=?', [job]))
    response = owner.post(staff_url, data=first); first_id = first['request_id']
    check('First real save creates one immutable event request and head', len(db('SELECT id FROM ci_studio_plan_events WHERE job_id=?', [job])) == 1 and len(db('SELECT id FROM ci_studio_plan_requests WHERE id=?', [first_id])) == 1 and db('SELECT event_id FROM ci_studio_plan_heads WHERE id=?', [job]) == [{'event_id': first_id}])
    check('Saved plan redirects to its exact history event', 'focus_plan='+first_id in response.url and response.url.endswith('#planning-'+first_id))
    stale = form_data(form_for(owner.get(staff_url), 'project_planning_save'))
    submit(owner, staff_url, 'project_planning_save', {'client_action': 'Newer client action'})
    latest = db('SELECT * FROM ci_studio_plan_heads WHERE id=?', [job])
    response = owner.post(staff_url, data=first)
    check('Original planning retry after later change cannot revert current plan', db('SELECT * FROM ci_studio_plan_heads WHERE id=?', [job]) == latest and 'already saved' in response.text)
    stale['scope'] = 'Unsaved <img src=x onerror=alert(1)> draft'
    response = owner.post(staff_url, data=stale); retained = form_data(form_for(response, 'project_planning_save'))
    check('Stale planning draft retains original identity version and escaped text', retained['scope'] == stale['scope'] and retained['expected'] == stale['expected'] and retained['request_id'] == stale['request_id'] and '<img src=x onerror=' not in response.text and 'current saved plan is shown below' in response.text)
    owner.post(staff_url, data=retained)
    check('Resubmitted stale planning draft never silently adopts newer version', db('SELECT * FROM ci_studio_plan_heads WHERE id=?', [job]) == latest)
    response = client.get(client_url)
    check('Client dashboard shows real scope date action and prerequisites', all(x in response.text for x in ['Scope &amp; next steps', 'Newer client action', '2028-03-15', 'Depends on:', 'Content approved']) and 'name="expected"' not in response.text and '<script>literal' not in response.text)
    check('Foreign client cannot open exact planning event', other.get(client_url+'&focus_plan='+first_id).status_code == 404)
    check('Planning preserves existing project stage free text and dates', db('SELECT * FROM ci_studio_jobs WHERE id=?', [job]) == old_job)
    if not db("SELECT value FROM ci_settings WHERE name='activity_schema_version'"):
        submit(owner, admin+'?view=activity', 'activity_setup', {'password': OWNER})
    activity = portal+'?activity=1'; key = 'planning:'+first_id
    before = db('SELECT * FROM ci_activity_reads ORDER BY id')
    response = client.get(activity+'&open='+key)
    check('Planning notice opens the original snapshot without marking it read', response.url.endswith('#planning-'+first_id) and soup(response).select_one('#planning-'+first_id+' details[open]') is not None and db('SELECT * FROM ci_activity_reads ORDER BY id') == before)
    mark = {'action': 'activity_mark', 'keys': json.dumps([key]), 'state': 'read', 'csrf': token(client.get(activity))}
    client.post(activity, data={**mark, 'csrf': 'bad'})
    check('Planning read state requires client CSRF', db('SELECT * FROM ci_activity_reads ORDER BY id') == before)
    client.post(activity, data=mark)
    check('Personal planning read belongs only to exact client and event', db('SELECT actor_type,actor_id,event_key FROM ci_activity_reads WHERE event_key=?', [key]) == [{'actor_type': 'client', 'actor_id': cid, 'event_key': key}])
    response = client.get(BASE+'/existing/api/activity.php?audience=client')
    check('Private client bell includes unread planning changes', response.status_code == 200 and response.json()['count'] >= 1 and response.headers.get('Cache-Control') == 'no-store, private')
    for n in range(10):
        submit(owner, staff_url, 'project_planning_save', {'blockers': 'Recorded blocker '+str(n)})
    response = client.get(client_url)
    check('Actual planning timeline paginates ten recorded plans', 'Page 2 of 2' in response.text and len(soup(response).select('[id^=planning-].progress-event')) == 2)
    response = client.get(activity+'&open='+key)
    check('Old planning activity selects its earlier history page', 'Page 1 of 2' in response.text and soup(response).select_one('#planning-'+first_id) is not None)
    contexts = []
    def context(session, javascript=True):
        ctx = browser.new_context(viewport={'width': 1440, 'height': 1000}, device_scale_factor=1, reduced_motion='reduce', java_script_enabled=javascript); contexts.append(ctx)
        ctx.add_cookies([{'name': c.name, 'value': c.value, 'domain': urlparse(BASE).hostname, 'path': c.path or '/'} for c in session.cookies]); return ctx
    def capture(page, role):
        page.set_viewport_size({'width': 1440, 'height': 1000}); page.screenshot(path=str(QA/('Project-Planning-'+role+'-Desktop.png')), full_page=True)
        page.set_viewport_size({'width': 320, 'height': 900}); page.screenshot(path=str(QA/('Project-Planning-'+role+'-Mobile.png')), full_page=True)
        check('Planning '+role.lower()+' fits320px viewport', page.evaluate('document.documentElement.scrollWidth<=innerWidth'))
    try:
        p = context(owner).new_page(); errors = []; p.on('pageerror', lambda e: errors.append(str(e))); p.goto(staff_url)
        editor = p.locator('[data-planning-editor]'); expect(editor.locator('[data-planning-controls]')).to_be_enabled()
        editor.locator('[data-planning-add]').click(); editor.locator('[data-planning-field=title]').fill('Launch <review>')
        editor.locator('[data-planning-field=details]').fill('Unicode café\u2028next line\u2029final')
        editor.locator('[data-planning-field=owner]').select_option('client')
        editor.locator('[data-planning-dependencies] input[value="'+'2'*32+'"]').check()
        editor.locator('[data-planning-earlier]').click()
        editor.locator('[data-planning-field=title]').press('Tab')
        check('Milestone editor supports keyboard focus through real controls', p.evaluate('document.activeElement.matches("[data-planning-field=details]")'))
        editor.get_by_role('button', name='Save project plan', exact=True).click(); expect(p.locator('#planning-title')).to_be_visible()
        saved = json.loads(db('SELECT plan_json FROM ci_studio_plan_heads WHERE id=?', [job])[0]['plan_json'])
        check('Browser adds orders and links a milestone with exact Unicode details', saved['milestones'][1]['title'] == 'Launch <review>' and saved['milestones'][1]['depends_on'] == ['2'*32] and saved['milestones'][1]['details'] == 'Unicode café\u2028next line\u2029final')
        editor = p.locator('[data-planning-editor]'); editor.locator('[data-planning-list] button').nth(1).click()
        check('Persisted milestones cannot be removed in the editor', editor.locator('[data-planning-remove]').is_hidden())
        editor.locator('[data-planning-field=state]').select_option('cancelled'); editor.get_by_role('button', name='Save project plan', exact=True).click()
        expect(p.locator('#planning-title')).to_be_visible(); capture(p, 'Staff')
        cpage = context(client).new_page(); cpage.goto(client_url); expect(cpage.locator('#planning-title')).to_be_visible(); capture(cpage, 'Client')
        check('Client browser sees escaped milestone title and cancelled state', cpage.locator('#planning-title').locator('..').get_by_text('Launch <review>', exact=True).count() == 1 and cpage.locator('#planning-title').locator('..').get_by_text('Cancelled', exact=True).count() == 1)
        p.set_viewport_size({'width': 1440, 'height': 1000}); p.goto(staff_url); editor = p.locator('[data-planning-editor]'); editor.locator('[name=scope]').fill('Browser unsaved scope')
        submit(owner, staff_url, 'project_planning_save', {'scope': 'Concurrent planning winner'})
        editor.get_by_role('button', name='Save project plan', exact=True).click(); expect(p.locator('[data-planning-editor] [name=scope]')).to_have_value('Browser unsaved scope'); capture(p, 'Conflict')
        check('Browser conflict preserves actual newer current plan', json.loads(db('SELECT plan_json FROM ci_studio_plan_heads WHERE id=?', [job])[0]['plan_json'])['scope'] == 'Concurrent planning winner')
        nojs = context(owner, False).new_page(); nojs.goto(staff_url); before_nojs = form_data(form_for(owner.get(staff_url), 'project_planning_save'))['milestones_json']
        nojs.locator('[data-planning-editor] [name=client_action]').fill('No JavaScript update'); nojs.get_by_role('button', name='Save project plan', exact=True).click()
        after_nojs = form_data(form_for(owner.get(staff_url), 'project_planning_save'))['milestones_json']
        check('No-JavaScript edit preserves every milestone byte', before_nojs == after_nojs and 'No JavaScript update' in client.get(client_url).text)
        check('Planning editor has no browser script errors', errors == [])
    finally:
        for ctx in contexts: ctx.close()
    check('Planning UI retains reviews conversation invoices and files', all(x in client.get(client_url).text for x in ['Designs & proposals', 'Project conversation', 'Private project files', 'Invoices & payment instructions']))
    chosen_form(owner, admin+'?view=support', 'support_policy', 'client_id', cid, {'allowed': '1', 'owner_password': OWNER})
    response = chosen_form(owner, admin+'?view=studio-clients', 'support_start', 'client_id', cid, {})
    sid = parse_qs(urlparse(response.url).query)['support'][0]; support_url = portal+'?support='+sid+'&job='+str(job)
    response = owner.get(support_url)
    check('Scoped support sees planning without an edit token or form', 'Scope &amp; next steps' in response.text and 'data-planning-editor' not in response.text and 'name="expected"' not in response.text)
    before = db('SELECT * FROM ci_studio_plan_heads WHERE id=?', [job])
    response = owner.post(staff_url+'&support='+sid, data={**first, 'csrf': token(owner.get(staff_url))})
    check('Support selector cannot submit administrative planning changes', response.status_code == 403 and db('SELECT * FROM ci_studio_plan_heads WHERE id=?', [job]) == before)
    submit(owner, portal+'?support='+sid, 'support_end')
    for path in ['app/projects/planning_forms.php', 'app/projects/planning_activity.php', 'includes/layout/project_planning.php', 'includes/layout/project_planning_editor.php']:
        check('HTTP denies direct planning internal '+path, requests.get(BASE+'/existing/'+path).status_code == 404)
    check('Planning creates no project invoice or provider order', not db('SELECT id FROM ci_studio_invoices WHERE job_id=?', [job]))
