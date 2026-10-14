"""Actual packaged client forms, conflicts and invitation activation; synthetic identities."""
import json
from urllib.parse import parse_qs, urlparse
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['BASE', 'QA', 'check', 'soup', 'form_data', 'submit', 'inbox_server', 'provider_server']})
    db('DELETE FROM ci_rate_limits')
    root = BASE+'/existing/admin/index.php'
    url = root+'?view=studio-clients'
    portal = BASE+'/existing/portal/index.php'
    owner = requests.Session()
    submit(owner, root+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    connections, calls = inbox_server.connections, provider_server.calls
    capture = (QA/'mail-capture.txt').read_bytes()

    def fields(session, action, client_id):
        r = session.get(url)
        for f in soup(r).select('form'):
            a, i = f.select_one('[name=action]'), f.select_one('[name=id]')
            if a and i and a['value'] == action and i['value'] == str(client_id):
                return form_data(f)
        raise AssertionError('Missing current client form: '+action)

    def current():
        return db('SELECT * FROM ci_studio_clients WHERE id=?', [cid])[0]

    def edit(**changes):
        data = fields(owner, 'studio_client', cid)
        data.update(changes)
        return owner.post(url, data=data)

    def invite():
        r = owner.post(url, data=fields(owner, 'studio_invite', cid))
        link = soup(r).select_one('.notice.info a[href*="invite="]')
        check('Client invitation displays a private one-time URL', link is not None and 'No email was sent' in r.text)
        return link['href']

    data = fields(owner, 'studio_client', 0)
    data.update(name='Authority fixture café', email='authority-client@example.invalid')
    r = owner.post(url, data=data)
    cid = db('SELECT id FROM ci_studio_clients WHERE email=?', [data['email']])[0]['id']
    check('Real client creation remains active and has no credentials', 'Client saved' in r.text and current()['active'] == 1 and current()['password_hash'] == '')
    check('Client administration prevents caching and referrer disclosure', r.headers.get('Cache-Control') == 'no-store, private' and r.headers.get('Referrer-Policy') == 'no-referrer')
    a, b = fields(owner, 'studio_client', cid), fields(owner, 'studio_invite', cid)
    check('Edit and invitation forms bind the same current account version', a['expected'] == b['expected'] == current()['auth_version'])
    before = current()
    for label, bad in [('CSRF', dict(a, csrf='invalid')), ('missing version', {k: v for k, v in a.items() if k != 'expected'}), ('newline version', dict(a, expected=a['expected']+'\n'))]:
        owner.post(url, data=bad)
        check('HTTP refuses client mutation with '+label, current() == before)
    edit(name='Current reviewed client')
    retained = current()
    hostile = '</dd><script>window.clientConflictExecuted=true</script>'
    a.update(name=hostile, email='unsaved-client@example.invalid')
    r = owner.post(url, data=a)
    unsaved = soup(r).select_one('[data-client-unsaved]')
    check('Stale edit preserves escaped unsaved details beside fresh saved forms', 'This client changed' in r.text and unsaved is not None and hostile in unsaved.get_text() and unsaved.find('script') is None and current() == retained and fields(owner, 'studio_client', cid)['name'] == 'Current reviewed client')
    r = owner.post(url, data=b)
    check('Stale invitation form cannot replace current client credentials', 'This client changed' in r.text and current() == retained)
    first = invite()
    first_state = current()
    check('Invitation is not shown again on refresh', first not in owner.get(url).text)
    check('Invitation URL respects subfolder and stores only token hash', urlparse(first).path == '/existing/portal/index.php' and parse_qs(urlparse(first).query)['invite'][0] not in json.dumps(first_state))
    older = fields(owner, 'studio_client', cid)
    second = invite()
    latest = current()
    r = owner.post(url, data=dict(older, name='Stale replacement'))
    check('Edit captured before a replacement invitation cannot revoke it', 'This client changed' in r.text and current() == latest)
    password = 'Synthetic authority client passphrase 2026'

    def activation(session, link, **changes):
        data = form_data(soup(session.get(link)).select_one('form'))
        data.update(action='activate', password=password, confirm_password=password, **changes)
        return session.post(link, data=data)

    visitor = requests.Session()
    r = activation(visitor, first)
    check('Superseded invitation cannot set a password over HTTP', current() == latest and 'already been used' in r.text)
    raw = parse_qs(urlparse(second).query)['invite'][0]
    r = activation(visitor, second, invite=raw+'\n')
    check('Public activation rejects raw malformed token without truncating it', 'Invalid invitation' in r.text and current() == latest)
    r = activation(visitor, second, csrf='invalid')
    check('Public activation retains CSRF protection', current() == latest and 'session expired' in r.text.lower())
    r = activation(visitor, second)
    check('Current public invitation establishes credentials once', 'Your password is saved' in r.text and current()['invite_hash'] is None and current()['password_hash'] != '' and current()['auth_version'] != latest['auth_version'])
    activated = current()
    r = activation(requests.Session(), second)
    check('Used invitation cannot replace the saved password', current() == activated and 'already been used' in r.text)
    submit(visitor, portal, 'login', {'email': data['email'], 'password': password})
    check('Activated client can actually open their portal', soup(visitor.get(portal)).select_one('[name=action][value=logout]') is not None)
    disabled = fields(owner, 'studio_client', cid)
    disabled.pop('active', None)
    owner.post(url, data=disabled)
    check('Current admin deactivation immediately closes existing client session', current()['active'] == 0 and current()['password_hash'] == activated['password_hash'] and soup(visitor.get(portal)).select_one('[name=action][value=login]') is not None)
    disabled_state = current()
    r = owner.post(url, data=fields(owner, 'studio_invite', cid))
    check('Disabled client cannot receive new invitation through real form', 'Active client not found' in r.text and current() == disabled_state)
    edit(active='1')
    submit(visitor, portal, 'login', {'email': data['email'], 'password': password})
    check('Reenabled client retains their own password', soup(visitor.get(portal)).select_one('[name=action][value=logout]') is not None)
    third = invite()
    check('New invitation revokes established client session', soup(visitor.get(portal)).select_one('[name=action][value=login]') is not None)
    edit(name='Current reviewed client')
    after_edit = current()
    r = activation(requests.Session(), third)
    check('Account edit revokes outstanding invitation without changing password', after_edit['invite_hash'] is None and after_edit['password_hash'] == activated['password_hash'] and current() == after_edit and 'already been used' in r.text)
    member_id = db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id']
    original = db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?', [member_id])[0]['permissions']
    member = requests.Session()
    submit(member, root+'?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', [json.dumps(['clients.manage']), member_id])
    granted = fields(member, 'studio_client', cid)
    member.post(url, data=dict(granted, name='Delegated reviewed client'))
    check('Delegated client manager retains real form access', current()['name'] == 'Delegated reviewed client' and member.get(url).status_code == 200)
    pending = fields(member, 'studio_client', cid)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', ['[]', member_id])
    retained = current()
    r = member.post(url, data=dict(pending, name='Revoked staff write'))
    check('Revoked client grant refuses a previously opened form', r.status_code == 403 and current() == retained)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', [original, member_id])
    pending = fields(owner, 'studio_client', cid)
    r = owner.post(url+'&support=fixture', data=dict(pending, name='Support write'))
    check('Support selector cannot mutate client identities', current() == retained)
    check('Support selector hides client administration with an explicit refusal', r.status_code == 403 and 'Leave client support' in r.text and soup(r).select_one('[name=expected]') is None)
    r = owner.get(url+'&support=fixture')
    check('Read-only support selector also excludes client edit and invitation forms', r.status_code == 403 and 'Delegated reviewed client' not in r.text and soup(r).select_one('[name=expected]') is None)
    r = requests.Session().post(url, data=dict(pending, name='Anonymous write'))
    check('Anonymous request cannot use another session client form', current() == retained and 'Delegated reviewed client' not in r.text)
    # Capture the actual stale-form response through Chromium at desktop and mobile widths.
    context = browser.new_context(viewport={'width': 1440, 'height': 1000})
    context.add_cookies([{'name': c.name, 'value': c.value, 'url': BASE+'/existing/'} for c in owner.cookies])
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.goto(url)
    form = page.locator('form').filter(has=page.locator('[name=action][value=studio_client]')).filter(has=page.locator('[name=id][value="'+str(cid)+'"]'))
    form.locator('[name=name]').fill(hostile)
    edit(name='Fresh browser conflict winner')
    form.get_by_role('button', name='Save / revoke old sessions', exact=True).click()
    expect(page.locator('[data-client-unsaved]')).to_be_visible()
    check('Browser conflict cannot execute unsaved markup or overwrite current data', page.evaluate('window.clientConflictExecuted!==true') and page.locator('[data-client-unsaved] script').count() == 0 and current()['name'] == 'Fresh browser conflict winner')
    page.screenshot(path=str(QA/'Client-Conflict-Desktop.png'), full_page=True)
    page.set_viewport_size({'width': 320, 'height': 800})
    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
    page.evaluate("window.scrollTo({top:0,left:0,behavior:'instant'})")
    expect(page.locator('.sidebar-toggle')).to_be_in_viewport()
    check('Client conflict details and current forms fit a narrow viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    page.screenshot(path=str(QA/'Client-Conflict-Mobile.png'), full_page=True)
    check('Client conflict page has no browser errors', not errors)
    context.close()
    for path in ['app/clients/accounts.php', 'app/clients/identity.php', 'admin/views/studio/clients.php', 'portal/controllers/actions.php']:
        check('Direct HTTP access denies client identity internal '+path, requests.get(BASE+'/existing/'+path).status_code == 404)
    check('All client administration and invitation fixtures send no mail or provider requests', inbox_server.connections == connections and provider_server.calls == calls and (QA/'mail-capture.txt').read_bytes() == capture)
