"""Packaged ticket HTTP/Chromium acceptance using disposable local identities."""
import json
from urllib.parse import parse_qs, urlparse, urljoin
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF
from ticket_screenshot import capture_ticket_page


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['BASE', 'QA', 'check', 'soup', 'token', 'form_data', 'form_for', 'submit', 'provider_server', 'inbox_server']})
    db('DELETE FROM ci_rate_limits')
    admin, portal = BASE+'/existing/admin/index.php', BASE+'/existing/portal/index.php'
    owner = requests.Session()
    submit(owner, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    listing = admin+'?view=tickets'
    calls, connections = provider_server.calls, inbox_server.connections
    capture = (QA/'mail-capture.txt').read_bytes()
    invoices = db('SELECT * FROM ci_studio_invoices ORDER BY id')

    def data(session, url, action):
        return form_data(form_for(session.get(url), action))

    def snapshot():
        return [db('SELECT * FROM ci_'+table+' ORDER BY '+order) for table, order in [
            ('support_tickets', 'id'), ('support_ticket_events', 'id'),
            ('support_ticket_staff_reads', 'ticket_id,user_id'), ('support_ticket_client_reads', 'ticket_id,client_id')]]

    def count(session, audience):
        response = session.get(BASE+'/existing/api/activity.php?audience='+audience)
        check('Ticket activity response is private '+audience, response.status_code==200 and response.headers.get('Cache-Control')=='no-store, private')
        return response.json()['count']

    r = owner.get(listing)
    check('Ticket storage requires explicit existing-install preparation', 'Prepare support tickets' in r.text and not db("SELECT * FROM ci_settings WHERE name='support_tickets_schema_version'"))
    for changes in [{'current_password': OWNER, 'csrf': 'bad'}, {'current_password': 'wrong'}]:
        r = submit(owner, listing, 'tickets_setup', changes)
        check('Ticket setup refuses '+('bad CSRF' if 'csrf' in changes else 'wrong password'), 'Prepare support tickets' in r.text and not db("SELECT * FROM ci_settings WHERE name='support_tickets_schema_version'"))
    r = submit(owner, listing, 'tickets_setup', {'current_password': OWNER})
    check('Owner prepares empty native tickets through actual form', 'Support tickets are ready' in r.text and not db('SELECT * FROM ci_support_tickets'))
    check('Staff ticket pages prohibit caching and referrer disclosure', r.headers.get('Cache-Control')=='no-store, private' and r.headers.get('Referrer-Policy')=='no-referrer')

    clients = []
    for number in [1, 2]:
        email = 'ticket-client'+str(number)+'@example.invalid'
        chosen_form(owner, admin+'?view=studio-clients', 'studio_client', 'id', 0, {'name': 'Ticket client '+str(number), 'email': email})
        cid = db('SELECT id FROM ci_studio_clients WHERE email=?', [email])[0]['id']
        invitation = chosen_form(owner, admin+'?view=studio-clients', 'studio_invite', 'id', cid, {})
        link = soup(invitation).select_one('.notice.info a[href*="invite="]')['href']
        client, password = requests.Session(), 'Synthetic ticket client passphrase '+str(number)
        submit(client, link, 'activate', {'password': password, 'confirm_password': password})
        submit(client, portal, 'login', {'email': email, 'password': password})
        check('Activated ticket client has support navigation '+str(number), 'Support tickets' in client.get(portal).text)
        clients.append((cid, client))
    cid, client = clients[0]
    foreign_id, foreign = clients[1]
    staff_url = admin+'?view=staff'
    chosen_form(owner, staff_url, 'staff_save', 'id', 0, {'name': 'Ticket-only operator', 'email': 'ticket-only@example.invalid', 'role': 'administrator', 'enabled': '1', 'permissions[]': ['support.tickets'], 'owner_password': OWNER})
    staff_id = db("SELECT id FROM ci_users WHERE email='ticket-only@example.invalid'")[0]['id']
    invite = chosen_form(owner, staff_url, 'staff_invite', 'id', staff_id, {'owner_password': OWNER})
    link = soup(invite).select_one('[data-staff-invite]')['href']
    staff = requests.Session()
    code = parse_qs(urlparse(link).fragment)['token'][0]
    submit(staff, admin+'?view=staff-setup', 'staff_activate', {'token': code, 'password': STAFF, 'confirm_password': STAFF})
    r = submit(staff, admin+'?view=login', 'login', {'email': 'ticket-only@example.invalid', 'password': STAFF})
    check('Dedicated ticket grant lands staff on support queue', parse_qs(urlparse(r.url).query).get('view')==['tickets'])
    check('Ticket-only grant cannot administer clients', staff.get(admin+'?view=studio-clients').status_code==403)

    new = portal+'?ticket_new=1'
    opening = data(client, new, 'client_tickets_create')
    subject = 'Website design help <review>'
    body = 'Design question </textarea><script>window.ticketInjected=true</script>'
    before = snapshot()
    r = client.post(new, data=dict(opening, subject=subject, body=body, csrf='bad'))
    check('Bad create CSRF retains escaped draft without writes', snapshot()==before and soup(r).select_one('[name=body]').text==body and '<script>window.ticketInjected' not in r.text)
    client.post(new, data=dict(opening, subject=subject, body=body, related_project='1'))
    check('Ticket create refuses an existing project owned by another client', snapshot()==before)
    request = dict(opening, subject=subject, body=body)
    r = client.post(new, data=request)
    ticket = parse_qs(urlparse(r.url).query)['ticket'][0]
    client_url, owner_url = portal+'?ticket='+ticket, admin+'?view=ticket&ticket='+ticket
    check('Actual client opens a scoped escaped ticket', db('SELECT client_id FROM ci_support_tickets WHERE id=?', [ticket])==[{'client_id': cid}] and '&lt;script&gt;' in r.text)
    check('Client ticket page uses private headers and workspace footer', r.headers.get('Cache-Control')=='no-store, private' and r.headers.get('Referrer-Policy')=='no-referrer' and soup(r).select_one('#cta-title') is None)
    check('Client ticket page loads the registered ticket stylesheet', soup(r).select_one('link[href*="tickets.css"]') is not None)
    staff_page = owner.get(owner_url)
    check('Staff ticket page loads the registered ticket stylesheet', soup(staff_page).select_one('link[href*="tickets.css"]') is not None)
    saved = snapshot()
    client.post(new, data=request)
    check('Exact opening replay never duplicates a ticket', snapshot()==saved)
    for outsider in [foreign, requests.Session()]:
        r = outsider.get(client_url)
        check('Foreign or anonymous visitor cannot read ticket or composer', subject not in soup(r).get_text() and soup(r).select_one('[value=client_tickets_reply]') is None)
    check('Foreign client list excludes the conversation', subject not in soup(foreign.get(portal+'?tickets=1')).get_text())

    original_reply = data(client, client_url, 'client_tickets_reply')
    initial_count = count(client, 'client')
    private = submit(owner, owner_url, 'tickets_note', {'body': 'PRIVATE staff assessment <internal>'})
    private_id = db("SELECT id FROM ci_support_ticket_events WHERE ticket_id=? AND JSON_UNQUOTE(JSON_EXTRACT(input_json,'$.command'))='note'", [ticket])[0]['id']
    submit(owner, owner_url, 'tickets_assign', {'staff_id': str(staff_id)})
    check('Staff history explicitly labels escaped private notes', 'PRIVATE staff assessment &lt;internal&gt;' in private.text and 'Private staff note' in private.text)
    check('Private updates leave whole client projection version and bell unchanged', data(client, client_url, 'client_tickets_reply')['revision']==original_reply['revision'] and count(client, 'client')==initial_count and 'PRIVATE staff assessment' not in client.get(client_url).text)
    r = client.get(client_url+'&focus='+private_id)
    check('Client cannot focus a private event', 'PRIVATE staff assessment' not in r.text and soup(r).select_one('#ticket-event-'+private_id) is None)
    reply_request = dict(original_reply, body='Client reply after private staff changes')
    r = client.post(client_url, data=reply_request)
    check('Client original form survives intervening private changes', 'Your conversation was updated' in r.text and 'Client reply after private staff changes' in r.text)
    saved = snapshot()
    client.post(client_url, data=reply_request)
    check('Exact client reply replay does not duplicate history', snapshot()==saved)
    foreign.post(client_url, data=dict(reply_request, csrf=token(foreign.get(portal)), request_id='f'*32, body='Foreign client forged reply'))
    check('Foreign valid session CSRF cannot write into another ticket', snapshot()==saved)

    submit(owner, owner_url, 'tickets_reply', {'body': 'Studio response for your design'})
    check('Public staff reply increments client notification count', count(client, 'client')==initial_count+1)
    feed = client.get(portal+'?activity=1')
    notice = soup(feed).select_one('[data-ticket-event] h3 a')
    check('Existing activity center links to exact ticket message', notice is not None and 'focus=' in notice['href'] and '#ticket-event-' in notice['href'])
    r = client.get(urljoin(portal, notice['href']))
    check('Notification focus resolves a real message element', soup(r).select_one('#'+urlparse(r.url).fragment) is not None)
    saved = snapshot()
    client.get(client_url)
    check('GET viewing does not change explicit read cursors', snapshot()==saved)
    submit(client, client_url, 'client_tickets_read')
    check('Explicit displayed cursor clears ticket notices', count(client, 'client')==initial_count)

    stale = data(client, client_url, 'client_tickets_reply')
    submit(owner, owner_url, 'tickets_reply', {'body': 'A newer public update'})
    saved = snapshot()
    unsaved = 'Unsaved stale reply </textarea><script>window.ticketInjected=true</script>'
    r = client.post(client_url, data=dict(stale, body=unsaved))
    check('Stale public reply keeps original intent and escaped unsaved text', snapshot()==saved and 'conversation changed' in r.text and form_data(form_for(r, 'client_tickets_reply'))['revision']==stale['revision'] and soup(r).select_one('textarea[name=body]').text==unsaved and '<script>window.ticketInjected' not in r.text)
    for label, changes in [('bad CSRF', {'csrf': 'bad'}), ('route mismatch', {'ticket_id': 'e'*32}), ('array body', {'body': None, 'body[]': 'bad'}), ('future version', {'revision': '500'}), ('oversized body', {'body': 'é'*17000})]:
        attempt = dict(data(client, client_url, 'client_tickets_reply'), body='Rejected '+label)
        attempt.update(changes)
        client.post(client_url, data=attempt)
        check('Ticket HTTP refuses '+label+' without history or read changes', snapshot()==saved)
    status = data(client, client_url, 'client_tickets_status')
    client.post(client_url, data=dict(status, status='closed'))
    check('Ticket closure requires explicit confirmation', snapshot()==saved)
    submit(owner, owner_url, 'tickets_status', {'status': 'resolved', 'confirm': '1'})
    r = submit(client, client_url, 'client_tickets_status', {'status': 'open', 'confirm': '1'})
    check('Client can reopen resolved conversation via actual form', 'Your conversation was updated' in r.text)
    check('Ticket-only staff has activity without client-admin permission', 'Unread support conversations' in staff.get(admin+'?view=activity').text)
    check('Ticket-only staff cannot follow a project activity selector', staff.get(admin+'?view=activity&open=message:1').status_code==403)

    contexts, errors = [], []
    def browser_page(session):
        context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce')
        context.add_cookies([{'name': c.name, 'value': c.value, 'url': BASE+'/existing/'} for c in session.cookies])
        contexts.append(context)
        page = context.new_page()
        page.on('pageerror', lambda e: errors.append(str(e)))
        return page

    def browser_form(page, action):
        return page.locator('form').filter(has=page.locator('[name=action][value="'+action+'"]'))

    def screenshots(page, name):
        check(name+' desktop screenshot includes the complete footer',
              capture_ticket_page(page, QA/(name+'-Desktop.png')))
        page.set_viewport_size({'width': 320, 'height': 800})
        if page.locator('.sidebar-toggle').count():
            page.evaluate("window.scrollTo({top:0,left:0,behavior:'instant'})")
            expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            page.locator('.sidebar-toggle').click()
            expect(page.locator('.admin-sidebar')).to_be_in_viewport()
            page.keyboard.press('Escape')
            expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
        check(name+' mobile screenshot includes the complete footer',
              capture_ticket_page(page, QA/(name+'-Mobile.png')))
        check(name+' fits 320px without horizontal overflow', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        page.set_viewport_size({'width': 1440, 'height': 1000})

    try:
        page, operator = browser_page(client), browser_page(owner)
        page.goto(client_url)
        operator.goto(owner_url)
        form = browser_form(operator, 'tickets_reply')
        form.locator('[name=body]').fill('Actual browser staff reply')
        form.locator('button').click()
        expect(operator.get_by_text('Actual browser staff reply', exact=True)).to_be_visible()
        form = browser_form(page, 'client_tickets_reply')
        form.locator('[name=body]').fill('Browser unsaved reply <keep>')
        form.locator('button').click()
        expect(form.locator('[name=body]')).to_have_value('Browser unsaved reply <keep>')
        check('Actual browser preserves stale client draft', 'conversation changed' in page.content() and page.evaluate('window.ticketInjected') is None)
        screenshots(page, 'Support-Ticket-Conflict')
        page.goto(client_url)
        screenshots(page, 'Support-Ticket-Client')
        screenshots(operator, 'Support-Ticket-Staff')
        check('Actual ticket browser has no script errors', not errors)
    finally:
        for context in contexts: context.close()

    chosen_form(owner, admin+'?view=support', 'support_policy', 'client_id', cid, {'allowed': '1', 'owner_password': OWNER})
    response = submit(owner, owner_url, 'support_start')
    sid = parse_qs(urlparse(response.url).query)['support'][0]
    support_url = portal+'?support='+sid+'&ticket='+ticket
    r = owner.get(support_url)
    check('One-click support opens public ticket view with no client decisions or read marks', 'PRIVATE staff assessment' not in r.text and soup(r).select_one('[value=client_tickets_reply]') is not None and soup(r).select_one('[value=client_tickets_status],[value=client_tickets_read]') is None)
    read_before = db('SELECT * FROM ci_support_ticket_client_reads ORDER BY ticket_id,client_id')
    r = submit(owner, support_url, 'client_tickets_reply', {'body': 'Attributed Studio support reply'})
    check('Support reply remains attributed to real staff and support session', db("SELECT actor_staff_id,actor_client_id,support_session_id FROM ci_support_ticket_events WHERE ticket_id=? AND JSON_UNQUOTE(JSON_EXTRACT(input_json,'$.arguments.body'))=?", [ticket, 'Attributed Studio support reply'])==[{'actor_staff_id': 1, 'actor_client_id': None, 'support_session_id': sid}])
    check('Support reply preserves client read cursor', db('SELECT * FROM ci_support_ticket_client_reads ORDER BY ticket_id,client_id')==read_before)
    blocked = owner.get(BASE+'/existing/api/activity.php?audience=client&support='+sid)
    check('Support context cannot read personal ticket bell', blocked.status_code==204 and blocked.content==b'')
    submit(owner, portal+'?support='+sid, 'support_end')
    r = submit(client, client_url, 'client_tickets_status', {'status': 'closed', 'confirm': '1'})
    check('Terminal closure removes fresh reply and status controls', 'conversation is closed' in r.text and soup(r).select_one('[value=client_tickets_reply],[value=client_tickets_status]') is None)
    saved = snapshot()
    chosen_form(owner, staff_url, 'staff_save', 'id', staff_id, {'permissions[]': [], 'owner_password': OWNER})
    revoked = staff.get(owner_url, allow_redirects=False)
    check('Revoking ticket grant retires existing staff session', revoked.status_code==303 and 'view=login' in revoked.headers.get('Location', '') and snapshot()==saved)
    submit(staff, admin+'?view=login', 'login', {'email': 'ticket-only@example.invalid', 'password': STAFF})
    check('Renewed staff session cannot recover revoked ticket or activity access', staff.get(owner_url).status_code==403 and staff.get(admin+'?view=activity').status_code==403 and snapshot()==saved and db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?', [staff_id])==[{'permissions': '[]'}])
    for private in ['app/support/ticket_forms.php', 'app/support/ticket_choices.php', 'app/support/ticket_notifications.php', 'includes/layout/tickets.php', 'includes/layout/ticket_activity.php', 'admin/controllers/tickets.php', 'admin/views/tickets.php', 'portal/controllers/tickets.php', 'portal/views/tickets.php']:
        check('Direct HTTP denies ticket internal '+private, requests.get(BASE+'/existing/'+private).status_code==404)
    check('Ticket workflows create no invoices provider calls or email activity', db('SELECT * FROM ci_studio_invoices ORDER BY id')==invoices and provider_server.calls==calls and inbox_server.connections==connections and (QA/'mail-capture.txt').read_bytes()==capture)
