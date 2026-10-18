"""Synthetic fixture helpers that preserve every saved cloud intent and its authority."""
import time
from urllib.parse import parse_qs, urlparse
import requests
from release_cases import db, chosen_form
from staff_cases import OWNER


def age_unprotected_refreshes(seconds, profile_id=None):
    """Retain old fixture aging for other reads; never rewrite a cloud source."""
    if seconds not in (60, 120, 7200):
        raise AssertionError('Unexpected fixture interval')
    before = db('SELECT * FROM ci_provider_refreshes WHERE operation=? ORDER BY seq', ['cloudservers.list'])
    where = '(operation IS NULL OR operation<>?)'
    args = [seconds, 'cloudservers.list']
    if profile_id is not None:
        where += ' AND profile_id=?'; args.append(profile_id)
    db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-? WHERE ' + where, args)
    after = db('SELECT * FROM ci_provider_refreshes WHERE operation=? ORDER BY seq', ['cloudservers.list'])
    if before != after:
        raise AssertionError('Fixture changed retained cloud intent bytes')
    rows = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes' + (' WHERE profile_id=?' if profile_id else ''), [profile_id] if profile_id else [])
    time.sleep(max(0, int(rows[0]['epoch'] or 0) + 31 - time.time()))


def dedicated_cloud_owner(env, setup, email, label):
    """Use real staff invite/activation; never rewrite an existing owner's role or budget."""
    check, soup, token, submit = (env[k] for k in ('check', 'soup', 'token', 'submit'))
    admin = env['BASE'] + '/existing/admin/index.php'; staff = admin + '?view=staff'
    original = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
    authority = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']])
    intents = db('SELECT * FROM ci_provider_refreshes ORDER BY seq')
    audits = db('SELECT * FROM ci_audit ORDER BY id')
    check(label + ' starts with no preexisting owner account', not db('SELECT id FROM ci_users WHERE email=?', [email]))
    submit(setup, staff, 'staff_save', {'id': '0', 'name': label, 'email': email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    actor = db('SELECT * FROM ci_users WHERE email=?', [email])[0]
    invited = chosen_form(setup, staff, 'staff_invite', 'id', actor['id'], {'owner_password': OWNER})
    link = soup(invited).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(link).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(link)
    result = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    activation.close()
    check(label + ' activates through the real invitation boundary', result.status_code == 200 and urlparse(result.url).path == urlparse(admin).path and urlparse(result.url).query == 'view=login'
          and actor['id'] != original['id'] and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [actor['id']]) == [{'role': 'owner', 'enabled': 1}]
          and db('SELECT password_hash FROM ci_users WHERE id=?', [actor['id']])[0]['password_hash'] != '')
    owner = requests.Session(); login = submit(owner, admin + '?view=login', 'login', {'email': email, 'password': OWNER})
    check(label + ' has its own authenticated unused read budget', login.status_code == 200 and urlparse(login.url).path == urlparse(admin).path and urlparse(login.url).query == 'view=dashboard' and not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [actor['id']]))
    check(label + ' setup preserves original authority and every inherited read and audit', db('SELECT * FROM ci_users WHERE id=?', [original['id']]) == [original]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']]) == authority
          and db('SELECT * FROM ci_provider_refreshes ORDER BY seq') == intents
          and db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id', [audits[-1]['id'] if audits else 0]) == audits)
    return owner, actor['id']
