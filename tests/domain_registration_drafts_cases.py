"""Real packaged owner/client private drafts; synthetic contacts, zero provider calls."""
import hashlib
import json
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    base, qa, check, provider = (env[k] for k in ['BASE', 'QA', 'check', 'provider_server'])
    soup, token, form_data, form_for, submit = (env[k] for k in ['soup', 'token', 'form_data', 'form_for', 'submit'])
    admin, portal = base+'/existing/admin/index.php', base+'/existing/portal/index.php'
    owner_url, client_url = admin+'?view=registration-drafts', portal+'?registration_drafts=1'
    calls, posts = provider.calls, list(provider.posts)
    mail = (qa/'mail-capture.txt').read_bytes()
    inherited_tables = ['provider_profiles', 'provider_refreshes', 'provider_refresh_results',
                        'provider_operations', 'provider_operation_grants', 'studio_invoices',
                        'invoice_payments', 'service_subscriptions', 'service_subscription_events',
                        'domain_renewal_requests', 'domain_renewal_events']
    optional_tables = ['provider_package_links', 'provider_package_link_events',
                       'provider_domain_links', 'provider_domain_link_events',
                       'provider_directory_changes', 'provider_directory_dispatch_ends',
                       'provider_directory_change_checks', 'provider_stackcp_auth',
                       'provider_stackcp_assignments']

    def digest(value):
        return hashlib.sha256(json.dumps(value, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

    def optional_snapshot():
        result = {}
        for name in optional_tables:
            exists = db('SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', ['ci_'+name])
            result[name] = {'exists': bool(exists), 'sha256': digest(db('SELECT * FROM ci_'+name+' ORDER BY id')) if exists else None}
        return result

    inherited = {name: digest(db('SELECT * FROM ci_'+name+' ORDER BY id')) for name in inherited_tables}
    optional = optional_snapshot()
    inherited_audits = db('SELECT * FROM ci_audit ORDER BY id')
    fresh_tables = [r['TABLE_NAME'] for r in db("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,6)='fresh_' ORDER BY TABLE_NAME")]
    fresh = {name: digest(db('SELECT * FROM `'+name+'`')) for name in fresh_tables}
    before_owner = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
    original_profile = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [before_owner['id']])[0]
    sessions = []

    def session():
        s = requests.Session(); sessions.append(s); return s

    def drafts():
        return db('SELECT * FROM ci_domain_registration_drafts ORDER BY id')

    def snapshot():
        return digest({name: db('SELECT * FROM ci_'+name+' ORDER BY id') for name in ['domain_registration_drafts', 'domain_registration_revisions', 'audit']})

    def captured(s, url, action):
        return form_data(form_for(s.get(url), action))

    def buttons_fit(page, selector, label):
        check(label+' action labels fit their forms without clipping', page.locator(selector+' button').evaluate_all('''buttons => buttons.length > 0 && buttons.every(button => {
            const bounds = button.getBoundingClientRect();
            const form = button.closest('form').getBoundingClientRect();
            const text = document.createRange();
            text.selectNodeContents(button);
            return bounds.left >= form.left - 1 && bounds.right <= form.right + 1
                && button.scrollWidth <= button.clientWidth + 1
                && [...text.getClientRects()].every(rect => rect.left >= bounds.left - 1
                    && rect.right <= bounds.right + 1 && rect.top >= bounds.top - 1
                    && rect.bottom <= bounds.bottom + 1);
        })'''))

    setup = session()
    logged = submit(setup, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    original_owner = db('SELECT * FROM ci_users WHERE id=?', [before_owner['id']])[0]
    diagnostics = {'login': {'status': logged.status_code, 'dashboard': urlparse(logged.url).query == 'view=dashboard',
        'changed_fields': sorted(k for k in original_owner if original_owner[k] != before_owner[k]),
        'profile_unchanged': db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile]}}
    (qa/'registration-drafts-setup.json').write_text(json.dumps(diagnostics, indent=2), encoding='utf-8')
    check('Private draft setup login changes only normal login metadata', logged.status_code == 200 and diagnostics['login']['dashboard']
          and {k: v for k, v in before_owner.items() if k != 'last_login'} == {k: v for k, v in original_owner.items() if k != 'last_login'}
          and original_owner['last_login'] is not None and diagnostics['login']['profile_unchanged'])
    owner_email = 'registration-draft-owner@example.invalid'
    staff_url = admin+'?view=staff'
    check('Private draft dedicated owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [owner_email]))
    submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'Registration draft owner', 'email': owner_email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    owner_id = db('SELECT id FROM ci_users WHERE email=?', [owner_email])[0]['id']
    invited = chosen_form(setup, staff_url, 'staff_invite', 'id', owner_id, {'owner_password': OWNER})
    invitation = soup(invited).select_one('[data-staff-invite]')['href']
    code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = session(); page = activation.get(invitation)
    activated = activation.post(admin+'?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(page), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    diagnostics['activation'] = {'status': activated.status_code, 'login_page': urlparse(activated.url).query == 'view=login',
        'distinct_actor': owner_id != original_owner['id'], 'authorized_owner': db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [owner_id]) == [{'role': 'owner', 'enabled': 1}],
        'password_set': bool(db('SELECT password_hash FROM ci_users WHERE id=?', [owner_id])[0]['password_hash'])}
    (qa/'registration-drafts-setup.json').write_text(json.dumps(diagnostics, indent=2), encoding='utf-8')
    check('Private draft dedicated owner activates normally with original authority intact', activated.status_code == 200
          and all(diagnostics['activation'][k] for k in ['login_page', 'distinct_actor', 'authorized_owner', 'password_set'])
          and db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    owner = session(); submit(owner, admin+'?view=login', 'login', {'email': owner_email, 'password': OWNER})
    member = session(); submit(member, admin+'?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client, foreign = session(), session()
    for s, email in [(client, 'alice@example.invalid'), (foreign, 'bob@example.invalid')]:
        response = submit(s, portal, 'login', {'email': email, 'password': 'Synthetic client passphrase 2026'})
        check('Private draft direct client login '+email, response.status_code == 200 and 'Welcome, ' in response.text)
    cid = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
    client_before = db('SELECT * FROM ci_studio_clients WHERE id=?', [cid])[0]
    try:
        absent = lambda: not db("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ci_domain_registration_drafts','ci_domain_registration_revisions')")
        response = client.get(client_url)
        check('Private draft client GET before explicit setup is usable without DDL', response.status_code == 200 and 'has not enabled private registration drafts' in response.text and absent())
        response = owner.get(owner_url)
        check('Private draft owner GET exposes explicit setup only', response.status_code == 200 and 'Enable private draft storage' in response.text and 'Enable drafts' in response.text and absent())
        for device, width in [('Desktop', 1440), ('Mobile', 320)]:
            context = browser.new_context(viewport={'width': width, 'height': 740})
            try:
                context.add_cookies([{'name': c.name, 'value': c.value, 'url': base+'/existing/'} for c in owner.cookies])
                page = context.new_page(); page.goto(owner_url, wait_until='domcontentloaded'); page.evaluate('document.fonts.ready')
                setup_selector = 'form:has(input[name=action][value=registration_drafts_prepare])'
                expect(page.locator(setup_selector)).to_be_visible()
                buttons_fit(page, setup_selector, 'Private draft setup '+device)
            finally:
                context.close()
        setup_form = captured(owner, owner_url, 'registration_drafts_prepare')
        for label, fields, message in [('CSRF', {'csrf': 'invalid'}, 'Your session expired. Refresh the page and try again.'),
                                       ('owner password', {'owner_password': 'wrong'}, 'Your current password is not correct.')]:
            denied = owner.post(owner_url, data={**setup_form, 'owner_password': OWNER, **fields})
            check('Private draft setup refuses invalid '+label+' without tables', denied.status_code == 200
                  and message in denied.text and absent() and provider.calls == calls)
        response = submit(owner, owner_url, 'registration_drafts_prepare', {'owner_password': OWNER})
        check('Private draft owner explicitly prepares local empty family', response.status_code == 200 and not absent() and drafts() == [] and provider.calls == calls)
        check('Private draft delegated owner and support access are refused', member.get(owner_url).status_code == 403 and owner.get(owner_url+'&support=fixture').status_code == 403)
        values = {'domain': 'private-draft.example.com', 'requested_years': '2', 'privacy_service': '0',
                  'contact_organisation': 'Synthetic <Private Studio>', 'contact_name': 'Synthetic Private Registrant',
                  'contact_address': 'Synthetic private registration street', 'contact_telephone': '+1.2025550101',
                  'contact_email': 'synthetic-private@example.invalid', 'contact_cc': 'US', 'contact_pc': '00000',
                  'contact_sp': 'Synthetic State', 'contact_city': 'Fixture City', 'nameservers': 'ns1.example.invalid\nns2.example.invalid'}
        form = captured(client, client_url, 'registration_drafts_client_save'); rid = form['draft_id']; target = client_url+'&draft='+rid
        response = client.post(client_url, data={**form, **values})
        check('Private draft actual client save retains requested years and false privacy', response.status_code == 200 and drafts()[0]['id'] == rid and drafts()[0]['client_id'] == cid and drafts()[0]['revision'] == 1 and drafts()[0]['state'] == 'draft')
        check('Private draft contact page sends no-store and no-referrer', 'no-store' in response.headers.get('Cache-Control', '') and response.headers.get('Referrer-Policy') == 'no-referrer')
        check('Private draft saved contact HTML is escaped', 'Synthetic &lt;Private Studio&gt;' in response.text and 'Synthetic <Private Studio>' not in response.text)
        row = db('SELECT * FROM ci_domain_registration_revisions WHERE draft_id=?', [rid])[0]
        check('Private draft actual DB keeps contact out of public envelope and audit', all(v not in row['document_json'] and v not in row['ciphertext'] and v not in json.dumps(db('SELECT * FROM ci_audit')) for v in ['Synthetic Private Registrant', 'synthetic-private@example.invalid', 'Synthetic private registration street']))
        before = snapshot(); replay = client.post(client_url, data={**form, **values})
        check('Private draft captured exact save replay has no second event', replay.status_code == 200 and snapshot() == before)
        foreign_page = foreign.get(target)
        check('Private draft other client cannot see saved contacts', foreign_page.status_code == 200 and 'Registration draft unavailable.' in foreign_page.text and values['contact_name'] not in foreign_page.text)
        foreign_form = captured(foreign, client_url, 'registration_drafts_client_save')
        denied = foreign.post(target, data={**form, **values, 'csrf': foreign_form['csrf']})
        check('Private draft foreign captured save is refused without mutation', denied.status_code == 200 and 'Registration draft unavailable.' in denied.text and snapshot() == before)
        stale = captured(client, target, 'registration_drafts_client_save')
        submit(client, target, 'registration_drafts_client_submit', {'confirm': '1'})
        check('Private draft direct submit retains pending studio review state', drafts()[0]['state'] == 'submitted' and drafts()[0]['revision'] == 2)
        response = client.post(target, data={**stale, **values, 'contact_name': 'Stale contact must not replace'})
        html = soup(response); retained = html.select_one('.registration-draft-save input[name=expected_revision]')
        check('Private draft stale actual form retains captured revision and refuses replacement', response.status_code == 200 and 'This draft changed' in response.text and retained is not None and retained['value'] == '1' and drafts()[0]['revision'] == 2)
        check('Private draft save error preserves posted contact nameservers and explicit false preference',
              html.select_one('input[name=contact_name]')['value'] == 'Stale contact must not replace'
              and html.select_one('input[name=contact_address]')['value'] == values['contact_address']
              and html.select_one('textarea[name=nameservers]').get_text() == values['nameservers']
              and html.select_one('select[name=privacy_service] option[selected]')['value'] == '0'
              and html.select_one('input[name=command_id]')['value'] == stale['command_id'])
        owner_target = owner_url+'&draft='+rid
        response = submit(owner, owner_target, 'registration_drafts_owner_save', {**values, 'contact_name': 'Owner revised private registrant', 'owner_password': OWNER})
        check('Private draft real owner edit requires new client submission', response.status_code == 200 and drafts()[0]['state'] == 'draft' and drafts()[0]['revision'] == 3)
        submit(client, target, 'registration_drafts_client_withdraw', {'confirm': '1'})
        submit(client, target, 'registration_drafts_client_save', {**values, 'contact_name': 'Withdrawn private registrant'})
        check('Private draft edit does not silently reopen withdrawal', drafts()[0]['state'] == 'withdrawn' and drafts()[0]['revision'] == 5)
        submit(client, target, 'registration_drafts_client_reopen', {'confirm': '1'})
        check('Private draft client reopens with all six original revisions retained', drafts()[0]['state'] == 'draft' and drafts()[0]['revision'] == 6 and len(db('SELECT id FROM ci_domain_registration_revisions WHERE draft_id=?', [rid])) == 6)
        before = snapshot(); malformed = captured(client, target, 'registration_drafts_client_save'); malformed.pop('draft_id')
        response = client.post(target, data={**malformed, **values})
        check('Private draft malformed selector cannot redisplay fresh action authority', response.status_code == 200 and 'Reload the current draft' in response.text and not soup(response).select('.registration-draft-editor form') and snapshot() == before)
        for audience, s, url in [('Owner', owner, owner_target), ('Client', client, target)]:
            for device, width in [('Desktop', 1440), ('Mobile', 320)]:
                context = browser.new_context(viewport={'width': width, 'height': 900 if device == 'Desktop' else 740})
                try:
                    context.add_cookies([{'name': c.name, 'value': c.value, 'url': base+'/existing/'} for c in s.cookies])
                    page = context.new_page(); page.goto(url, wait_until='domcontentloaded'); page.evaluate('document.fonts.ready')
                    expect(page.locator('.registration-draft-editor')).to_be_visible()
                    expect(page.locator('input[name=contact_name]')).to_have_value('Withdrawn private registrant')
                    check('Private draft '+audience+' '+device+' editor fits viewport', page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))
                    buttons_fit(page, '.registration-draft-editor', 'Private draft '+audience+' '+device)
                    page.screenshot(path=str(qa/('Registration-Drafts-'+audience+'-'+device+'.png')), full_page=True)
                finally:
                    context.close()
        history = db('SELECT revision,state,actor_kind,actor_id,action FROM ci_domain_registration_revisions WHERE draft_id=? ORDER BY revision', [rid])
        check('Private draft exact six local revisions preserve client and owner authors', [r['state'] for r in history] == ['draft', 'submitted', 'draft', 'withdrawn', 'withdrawn', 'draft'] and [r['action'] for r in history] == ['save', 'submit', 'save', 'withdraw', 'save', 'reopen'] and [r['actor_kind'] for r in history] == ['client', 'client', 'staff', 'client', 'client', 'client'] and history[2]['actor_id'] == owner_id)
        check('Private drafts perform zero provider calls writes or mail', provider.calls == calls and provider.posts == posts and (qa/'mail-capture.txt').read_bytes() == mail)
        check('Private drafts retain original account authority and client identity', db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile] and db('SELECT * FROM ci_studio_clients WHERE id=?', [cid]) == [client_before])
        inherited_after = {name: digest(db('SELECT * FROM ci_'+name+' ORDER BY id')) for name in inherited_tables}
        optional_after = optional_snapshot()
        old_audits = db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id', [inherited_audits[-1]['id']])
        fresh_after = {name: digest(db('SELECT * FROM `'+name+'`')) for name in fresh_tables}
        check('Private drafts preserve inherited provider invoice agreement renewal and fresh histories', inherited_after == inherited and old_audits == inherited_audits and fresh_after == fresh)
        check('Private drafts preserve optional directory StackCP and assignment history or absence', optional_after == optional)
        (qa/'registration-drafts-history.json').write_text(json.dumps({'states': [r['state'] for r in history], 'actions': [r['action'] for r in history], 'actor_kinds': [r['actor_kind'] for r in history], 'revisions': len(history), 'provider_calls_added': provider.calls-calls, 'provider_writes_added': len(provider.posts)-len(posts), 'inherited_hashes': inherited, 'inherited_after_hashes': inherited_after, 'optional_histories': optional, 'optional_after_histories': optional_after, 'fresh_hashes': fresh, 'fresh_after_hashes': fresh_after, 'audit_before_sha256': digest(inherited_audits), 'audit_after_sha256': digest(old_audits)}, indent=2), encoding='utf-8')
    finally:
        for s in sessions:
            s.close()
