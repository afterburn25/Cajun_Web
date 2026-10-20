"""Real 1.23-to-candidate agreement creation, client consent and file recovery."""
from urllib.parse import parse_qs, urlparse
import requests
from bs4 import BeautifulSoup
from release_version import RECOVERY


def run(env):
    url, label, check = (env[k] for k in ['url', 'label', 'check'])
    owner, records, action = (env[k] for k in ['session', 'records', 'action'])
    private, password = (env[k] for k in ['private', 'password'])
    admin, portal = url+'/admin/index.php', url+'/portal/index.php'
    listing = admin+'?view=service-plans'

    def fields(session, target, operation, client_id=None):
        response = session.get(target)
        assert response.status_code == 200, response.status_code
        for form in BeautifulSoup(response.text, 'html.parser').select('form'):
            marker = form.select_one('[name=action]')
            if marker is None or marker.get('value') != operation:
                continue
            if client_id is not None:
                identity = form.select_one('[name=id]')
                if identity is None or identity.get('value') != str(client_id):
                    continue
            result = {}
            for control in form.select('input[name], textarea[name], select[name]'):
                if control.has_attr('disabled') or control.get('type') in ['submit', 'password']:
                    continue
                if control.get('type') in ['checkbox', 'radio'] and not control.has_attr('checked'):
                    continue
                if control.name == 'textarea':
                    value = control.get_text()
                elif control.name == 'select':
                    option = control.select_one('option[selected]') or control.select_one('option')
                    value = option.get('value', option.get_text()) if option else ''
                else:
                    value = control.get('value', '')
                result[control['name']] = value
            return result
        found=[(form.select_one('[name=action]') or {}).get('value') for form in BeautifulSoup(response.text,'html.parser').select('form')]
        raise AssertionError('Missing expected agreement upgrade form: '+operation+' url='+response.url+' found='+repr(found))

    def submit(session, target, operation, changes, client_id=None):
        return session.post(target, data=dict(fields(session, target, operation, client_id), **changes))

    check(label+' updated owner must explicitly prepare service agreements',
          'Prepare service agreements' in owner.get(listing).text)
    response = submit(owner, admin+'?view=studio-upgrade', 'studio_migrate', {'confirm': '1'})
    check(label+' upgraded workspace schema precedes service agreement setup', 'Database update completed' in response.text)
    response = submit(owner, listing, 'service_plans_setup', {'current_password': password})
    if 'Service agreements are ready' not in response.text:
        notices = [n.get_text(' ', strip=True)[:200] for n in BeautifulSoup(response.text, 'html.parser').select('.notice.error')]
        raise AssertionError('service_plans_setup rendered no ready flash; notices='+repr(notices))
    drift = [name for name in ['studio_clients', 'studio_jobs', 'studio_invoices'] if records()[name] != env['before'][name]]
    if drift:raise AssertionError(label+' record drift before agreement check: '+repr(drift))
    check(label+' additive agreement setup preserves prior invoices and clients',
          'Service agreements are ready' in response.text
          and all(records()[name] == env['before'][name] for name in ['studio_clients', 'studio_jobs', 'studio_invoices']))
    new = admin+'?view=service-plan'
    response = submit(owner, new, 'service_plans_create', {
        'name': 'Retained website care', 'cadence': 'monthly', 'price': '129.00',
        'inclusions': 'Reviewed website updates\nMonthly report', 'exclusions': 'New features',
        'limits': 'updates = 4', 'support': 'Two business days', 'current_password': password})
    plan = parse_qs(urlparse(response.url).query)['record'][0]
    detail = admin+'?view=service-plan&record='+plan
    response = submit(owner, detail, 'service_plans_publish', {'confirm': '1', 'current_password': password})
    check(label+' upgraded owner publishes exact saved terms', 'Offer these terms to a client' in response.text)
    # Activate the already seeded retained client through the real invitation flow.
    clients = admin+'?view=studio-clients'
    response = submit(owner, clients, 'studio_invite', {}, client_id=1)
    invitation = BeautifulSoup(response.text, 'html.parser').select_one('.notice.info a[href*="invite="]')['href']
    client = requests.Session()
    client_password = 'Synthetic retained agreement client passphrase'
    submit(client, invitation, 'activate', {'password': client_password, 'confirm_password': client_password})
    submit(client, portal, 'login', {'email': 'organized-client@example.invalid', 'password': client_password})
    response = submit(owner, detail, 'service_plans_offer', {'client_id': '1', 'confirm': '1', 'current_password': password})
    agreement = parse_qs(urlparse(response.url).query)['record'][0]
    target = portal+'?agreement='+agreement
    consent = fields(client, target, 'service_agreements_consent')
    response = client.post(target, data=dict(consent, confirm='1'))
    saved = records('plans')
    check(label+' upgraded client independently accepts pinned terms without a payment mandate',
          'awaiting studio activation' in response.text and 'does not authorize automatic payments' in response.text
          and len(saved['service_plans']) == len(saved['service_subscriptions']) == 1
          and len(saved['service_subscription_events']) == 1
          and saved['service_subscription_events'][0]['actor_client_id'] == 1
          and saved['service_subscription_events'][0]['actor_staff_id'] is None)
    earlier = {name: records(name) for name in ['submissions', 'drafts', 'provider', 'mail-accounts', 'templates']}
    invoices = records()['studio_invoices']
    keys = {name: (private/name).read_bytes() for name in ['mail-master.key', 'provider-master.key']}

    def retained(expected):
        return (records('plans') == expected and records()['studio_invoices'] == invoices
                and all(records(name) == data for name, data in earlier.items())
                and all((private/name).read_bytes() == data for name, data in keys.items()))

    # The combined latest-baseline scenarios exceed ten updater authentications
    # in fifteen minutes. Prove the real HTTP throttle, then start an isolated
    # synthetic test window without changing the deployed security policy.
    journal = private/RECOVERY/'journal.php'
    journal_before = journal.read_bytes()
    records('owner-rate-exhaust')
    response = action('recover')
    check(label+' updater throttle refuses recovery without changing consent or private data',
          'Please wait before another owner authentication attempt.' in response.text
          and journal.read_bytes() == journal_before and retained(saved))
    records('owner-rate-reset')

    response = action('recover')
    old_client = client.get(target)
    check(label+' file recovery preserves consent journals and earlier private data',
          '<h2>Rolled-back</h2>' in response.text and retained(saved)
          and 'Retained website care' not in old_client.text
          and BeautifulSoup(old_client.text, 'html.parser').select_one('[name=action][value=service_agreements_consent]') is None)
    response = action('apply')
    restored = client.get(target)
    check(label+' reapplication reopens the same agreed terms without a second setup',
          '<h2>Complete</h2>' in response.text and retained(saved)
          and 'Retained website care' in restored.text and '$129.00 USD' in restored.text
          and 'awaiting studio activation' in restored.text)
    client.post(target, data=dict(consent, confirm='1'))
    check(label+' original client consent replay after recovery adds no agreement or event', retained(saved))
    owner_target = admin+'?view=service-agreement&record='+agreement
    submit(owner, owner_target, 'service_plans_activate', {'confirm': '1', 'current_password': password})
    active = records('plans')
    check(label+' recovered agreement still supports explicit owner activation',
          'Active agreement' in client.get(target).text and len(active['service_subscription_events']) == 2
          and retained(active))
    response = action('recover')
    check(label+' recovery retains activated agreement and immutable history', '<h2>Rolled-back</h2>' in response.text and retained(active))
    response = action('apply')
    check(label+' reapplication keeps active agreement without generating an invoice',
          '<h2>Complete</h2>' in response.text and retained(active) and 'Active agreement' in client.get(target).text)

    return {'client': client, 'fields': fields, 'submit': submit}
