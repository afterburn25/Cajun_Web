"""Actual retained 1.23 update/recovery with populated public/private tickets."""
from urllib.parse import parse_qs, urlparse
import requests
from bs4 import BeautifulSoup
from release_version import RECOVERY


def run(env):
    url, label, check = (env[k] for k in ['url', 'label', 'check'])
    owner, records, action = (env[k] for k in ['session', 'records', 'action'])
    password, private = (env[k] for k in ['password', 'private'])
    admin, portal = url+'/admin/index.php', url+'/portal/index.php'
    listing = admin+'?view=tickets'

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
        raise AssertionError('Missing expected ticket upgrade form: '+operation)

    def submit(session, target, operation, changes, client_id=None):
        return session.post(target, data=dict(fields(session, target, operation, client_id), **changes))

    original = records()
    earlier = {name: records(name) for name in ['submissions', 'drafts', 'provider', 'mail-accounts', 'templates']}
    keys = {name: (private/name).read_bytes() for name in ['mail-master.key', 'provider-master.key', 'retained-customer.php']}
    check(label+' updated owner must explicitly prepare support tickets', 'Prepare support tickets' in owner.get(listing).text)
    response = submit(owner, admin+'?view=studio-upgrade', 'studio_migrate', {'confirm': '1'})
    check(label+' upgraded workspace schema precedes support ticket setup', 'Database update completed' in response.text)
    response = submit(owner, listing, 'tickets_setup', {'current_password': password})
    check(label+' additive ticket setup preserves existing projects clients and invoices',
          'Support tickets are ready' in response.text and all(records()[name]==original[name] for name in ['studio_clients', 'studio_jobs', 'studio_invoices']) and all(not rows for rows in records('tickets').values()))
    # Activate the already seeded retained client through the real invitation flow.
    clients = admin+'?view=studio-clients'
    response = submit(owner, clients, 'studio_invite', {}, client_id=1)
    invitation = BeautifulSoup(response.text, 'html.parser').select_one('.notice.info a[href*="invite="]')['href']
    client = requests.Session()
    client_password = 'Synthetic retained ticket client passphrase'
    submit(client, invitation, 'activate', {'password': client_password, 'confirm_password': client_password})
    submit(client, portal, 'login', {'email': 'organized-client@example.invalid', 'password': client_password})
    # Invitation and activation legitimately rewrite the seeded studio_clients row
    # (invite_hash, auth_version, password_hash). The retention baseline must be the
    # activated state so ticket and recovery checks do not compare against it.
    baseline = records()
    new = portal+'?ticket_new=1'
    opening = dict(fields(client, new, 'client_tickets_create'), subject='Retained website support', body='Retained client ticket opening', related_project='1')
    response = client.post(new, data=opening)
    ticket = parse_qs(urlparse(response.url).query)['ticket'][0]
    target, staff_target = portal+'?ticket='+ticket, admin+'?view=ticket&ticket='+ticket
    public_before = fields(client, target, 'client_tickets_reply')['revision']
    submit(owner, staff_target, 'tickets_note', {'body': 'RETAINED_PRIVATE_TICKET_NOTE'})
    check(label+' private note after upgrade preserves client public version and projection',
          fields(client, target, 'client_tickets_reply')['revision']==public_before and 'RETAINED_PRIVATE_TICKET_NOTE' not in client.get(target).text)
    reply = dict(fields(client, target, 'client_tickets_reply'), body='Retained client ticket reply')
    client.post(target, data=reply)
    submit(owner, staff_target, 'tickets_reply', {'body': 'Retained Studio public reply'})
    submit(client, target, 'client_tickets_read', {})
    submit(owner, staff_target, 'tickets_read', {})
    submit(owner, staff_target, 'tickets_note', {'body': 'RETAINED_PRIVATE_AFTER_READ'})
    submit(owner, staff_target, 'tickets_reply', {'body': 'Retained unread Studio update'})
    saved = records('tickets')
    check(label+' upgraded ticket retains real actors history and two independent read cursors',
          len(saved['support_tickets'])==1 and len(saved['support_ticket_events'])==5
          and len(saved['support_ticket_client_reads'])==len(saved['support_ticket_staff_reads'])==1
          and saved['support_tickets'][0]['client_id']==1
          and saved['support_ticket_client_reads'][0]['revision']==3
          and saved['support_ticket_staff_reads'][0]['revision']==3)
    feed = client.get(portal+'?activity=1')
    notice = BeautifulSoup(feed.text, 'html.parser').select_one('[data-ticket-event] h3 a')
    check(label+' upgraded client sees exact public unread update without private notes',
          notice is not None and 'focus=' in notice['href'] and ticket in notice['href']
          and 'RETAINED_PRIVATE' not in feed.text)

    def retained(expected):
        return (records('tickets')==expected and all(records(name)==data for name, data in earlier.items())
                and all(records()[name]==baseline[name] for name in ['studio_clients', 'studio_jobs', 'studio_invoices'])
                and all((private/name).read_bytes()==data for name, data in keys.items()))

    journal = private/RECOVERY/'journal.php'
    journal_before = journal.read_bytes()
    records('owner-rate-exhaust')
    response = action('recover')
    check(label+' throttled recovery refuses owner authentication and leaves the journal',
          'Please wait before another owner authentication attempt.' in response.text and journal.read_bytes()==journal_before)
    check(label+' throttled recovery cannot change populated tickets or private records', retained(saved))
    records('owner-rate-reset')
    response = action('recover')
    old = client.get(target)
    check(label+' recovery preserves private ticket history and read states while old files omit ticket UI',
          '<h2>Rolled-back</h2>' in response.text and retained(saved) and 'Retained client ticket opening' not in old.text
          and BeautifulSoup(old.text, 'html.parser').select_one('[value=client_tickets_reply]') is None)
    response = action('apply')
    restored = client.get(target)
    check(label+' reapplication restores public conversation without a second setup or read reset',
          '<h2>Complete</h2>' in response.text and retained(saved) and 'Retained unread Studio update' in restored.text
          and 'RETAINED_PRIVATE' not in restored.text and 'Prepare support tickets' not in owner.get(listing).text)
    client.post(new, data=opening)
    client.post(target, data=reply)
    check(label+' original opening and reply replays after recovery cannot duplicate ticket history', retained(saved))
    submit(client, target, 'client_tickets_reply', {'body': 'Reply after ticket recovery'})
    active = records('tickets')
    check(label+' recovered ticket accepts new currently authorized public reply',
          len(active['support_ticket_events'])==6 and active['support_ticket_events'][-1:]!=saved['support_ticket_events'][-1:]
          and 'Reply after ticket recovery' in owner.get(staff_target).text and retained(active))
    response = action('recover')
    check(label+' second recovery retains appended ticket history and original read cursors', '<h2>Rolled-back</h2>' in response.text and retained(active))
    response = action('apply')
    check(label+' second reapplication restores ticket without mail charge or provider mutation',
          '<h2>Complete</h2>' in response.text and retained(active) and 'Reply after ticket recovery' in client.get(target).text)
