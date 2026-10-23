"""Create existing agreement/recurring/license records on actual published1.25 BEFORE update."""
import datetime
import hashlib
import json
import os
import subprocess
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from bs4 import BeautifulSoup
from release_126_retention import exact_business, retained


def one_record(response, key):
    values = parse_qs(urlparse(response.url).query).get(key, [])
    assert response.status_code == 200 and len(values) == 1 and values[0], ('Missing current redirect identity', key)
    return values[0]


def prepare(repo, installed, base, php, owner, client, other, client_id, job_id, password, fields, submit, snapshot, ok):
    admin, portal = base + '/admin/index.php', base + '/portal/index.php'
    before = snapshot()
    plans = admin + '?view=service-plans'
    response = submit(owner, plans, 'service_plans_setup', {'current_password': password})
    ok('Original1.25 explicitly prepares agreements without changing original rows',
       response.status_code == 200 and 'Service agreements are ready' in response.text and retained(before, snapshot()))
    response = submit(owner, admin + '?view=service-plan', 'service_plans_create',
                      {'name': 'Retained release website care', 'cadence': 'monthly', 'price': '129.00',
                       'inclusions': 'Reviewed updates\nMonthly report', 'exclusions': 'New features',
                       'limits': 'updates = 4', 'support': 'Two business days', 'current_password': password})
    plan = one_record(response, 'record')
    detail = admin + '?view=service-plan&record=' + plan
    response = submit(owner, detail, 'service_plans_publish', {'confirm': '1', 'current_password': password})
    ok('Original1.25 owner publishes exact service terms', response.status_code == 200 and 'Offer these terms to a client' in response.text)
    response = submit(owner, detail, 'service_plans_offer', {'client_id': str(client_id), 'confirm': '1', 'current_password': password})
    agreement = one_record(response, 'record')
    client_agreement = portal + '?agreement=' + agreement
    consent_target, consent = fields(client.get(client_agreement, timeout=120), 'service_agreements_consent')
    consent['confirm'] = '1'
    response = client.post(consent_target, data=consent, timeout=120)
    ok('Original activated client consents to original1.25 terms without a payment mandate',
       response.status_code == 200 and 'awaiting studio activation' in response.text and 'does not authorize automatic payments' in response.text)
    agreement_owner = admin + '?view=service-agreement&record=' + agreement
    activation_target, activation = fields(owner.get(agreement_owner, timeout=120), 'service_plans_activate')
    activation.update({'confirm': '1', 'current_password': password})
    owner.post(activation_target, data=activation, timeout=120)
    ok('Original client sees activated original1.25 agreement', 'Active agreement' in client.get(client_agreement, timeout=120).text)

    listing = admin + '?view=recurring-billing'
    project = listing + '&job=' + str(job_id)
    client_project = portal + '?recurring=1&job=' + str(job_id)
    response = submit(owner, listing, 'recurring_setup', {'owner_password': password, 'confirm': '1'})
    ok('Original1.25 recurring preparation is explicit and keeps original invoices', response.status_code == 200 and retained(before, snapshot()))
    anchor = (datetime.datetime.now(datetime.timezone.utc).date() + datetime.timedelta(days=60)).isoformat()
    offer_target, offer = fields(owner.get(project + '&source=' + agreement, timeout=120), 'recurring_offer')
    offer.update({'title': 'Retained release recurring care', 'terms': 'Exact original1.25 terms; invoice only, no automatic charge.',
                  'anchor': anchor, 'alignment': 'day-of-month', 'timezone': 'America/Chicago',
                  'due_after_days': '14', 'intent': 'publish', 'confirm': '1'})
    response = owner.post(offer_target, data=offer, timeout=120)
    schedule = one_record(response, 'schedule')
    recurring_client = client_project + '&schedule=' + schedule
    recurring_owner = project + '&schedule=' + schedule
    accept_target, accept = fields(client.get(recurring_client, timeout=120), 'accept', field='command')
    accept['confirm'] = '1'
    response = client.post(accept_target, data=accept, timeout=120)
    ok('Original direct client separately accepts future invoice terms', response.status_code == 200 and anchor in response.text)
    activate_target, activate = fields(owner.get(recurring_owner, timeout=120), 'activate', field='command')
    activate['confirm'] = '1'
    response = owner.post(activate_target, data=activate, timeout=120)
    ok('Owner activates accepted recurring terms without issuing a future invoice', response.status_code == 200
       and snapshot()['tables']['recurring_billing_invoices']['count'] == 0)

    # This is explicitly a NEW original1.25 historical fixture, created before any1.26 files are applied.
    # It uses retained production documents to establish historical eligibility; real HTTP issues the invoice.
    fixture = subprocess.run([php, str(repo / 'tests/recurring_billing_browser_fixture.php'),
                              str(installed), str(client_id), str(job_id)], capture_output=True, text=True,
                             env={**os.environ, 'CWP_TEST_DATABASE': 'cwp_test'}, timeout=120)
    assert fixture.returncode == 0, ('Historical original1.25 fixture', fixture.stderr)
    historical = json.loads(fixture.stdout)['schedule_id']
    period = project + '&schedule=' + historical + '&period_date=2020-02-01'
    client_period = client_project + '&schedule=' + historical + '&period_date=2020-02-01'
    issue_target, issue = fields(owner.get(period, timeout=120), 'issue', field='command')
    issue['confirm'] = '1'
    response = owner.post(issue_target, data=issue, timeout=120)
    invoice_id = one_record(response, 'invoice_id')
    invoice = admin + '?view=invoice-payments&invoice_id=' + invoice_id
    receipt_target, receipt = fields(owner.get(invoice, timeout=120), 'payments_ledger_receive')
    receipt.update({'amount': '5.00', 'method': 'bank', 'reference': 'Synthetic original1.25 recurring receipt',
                    'received_on': datetime.datetime.now(datetime.timezone.utc).date().isoformat(), 'confirm': '1'})
    response = owner.post(receipt_target, data=receipt, timeout=120)
    ok('Original1.25 historical period issues one invoice and one actual HTTP receipt', response.status_code == 200
       and snapshot()['tables']['recurring_billing_invoices']['count'] == 1
       and 'Invoice balance: $20.00 USD' in client.get(client_period, timeout=120).text)

    licenses = admin + '?view=studio-licenses'
    response = owner.get(licenses, timeout=120)
    ok('Fresh original1.25 already has prepared empty licensing and its current create form', response.status_code == 200
       and BeautifulSoup(response.text, 'html.parser').select_one('[name=action][value=studio_license_create]') is not None
       and snapshot()['tables']['website_licenses']['count'] == 0 and retained(before, snapshot()))
    response = submit(owner, licenses, 'studio_license_create', {'customer_id': str(client_id), 'product_id': 'generic'})
    forms = BeautifulSoup(response.text, 'html.parser').select('input[name=action][value=studio_domain_add]')
    ok('Original1.25 creates one normal client license without building another product', response.status_code == 200
       and len(forms) == 1 and snapshot()['tables']['website_licenses']['count'] == 1)
    license_id = forms[0].find_parent('form').select_one('[name=license_id]')['value']
    domain = 'release-retained.example'
    response = submit(owner, licenses, 'studio_domain_add', {'domain': domain}, {'license_id': license_id})
    ok('Original1.25 normal license records exact client domain locally', response.status_code == 200 and domain in response.text)

    verify_calls = [0]

    def verify():
        stable = snapshot()
        own = client.get(client_agreement, timeout=120)
        recurring = client.get(recurring_client, timeout=120)
        statement = client.get(client_period, timeout=120)
        verify_calls[0] += 1
        diag = {
            'install': str(installed), 'call': verify_calls[0],
            'agreement': {'status': own.status_code, 'markers': {m: (m in own.text) for m in
                          ['Active agreement', 'Agreement history', 'Sign in', 'unavailable', 'terms']},
                          'title': BeautifulSoup(own.text, 'html.parser').find('title').get_text() if BeautifulSoup(own.text, 'html.parser').find('title') else None},
            'recurring': {'status': recurring.status_code, 'markers': {m: (m in recurring.text) for m in
                          [anchor, 'Retained release recurring care', 'Billing history', 'unavailable', 'Sign in']},
                          'title': BeautifulSoup(recurring.text, 'html.parser').find('title').get_text() if BeautifulSoup(recurring.text, 'html.parser').find('title') else None},
        }
        (Path(repo) / 'qa' / ('r126-consent-' + hashlib.sha256(str(installed).encode()).hexdigest()[:8] + '-' + str(verify_calls[0]) + '.json')).write_text(json.dumps(diag, indent=2) + '\n', encoding='utf-8')
        ok('Retained original1.25 keeps actual client consent and active terms', own.status_code == 200 and 'Active agreement' in own.text
           and recurring.status_code == 200 and anchor in recurring.text and 'Retained release recurring care' in recurring.text)
        ok('Retained original1.25 retains exact recurring invoice balance', statement.status_code == 200 and 'Invoice balance: $20.00 USD' in statement.text)
        foreign_agreement = other.get(client_agreement, timeout=120)
        foreign_period = other.get(client_period, timeout=120)
        ok('Other client cannot read retained agreement or recurring invoice', foreign_agreement.status_code == 200
           and 'Subscription unavailable.' in foreign_agreement.text and 'Retained release website care' not in foreign_agreement.text
           and foreign_period.status_code == 200 and 'Project unavailable.' in foreign_period.text
           and 'Invoice balance:' not in foreign_period.text)
        # Do not save this HTML: the current licensing view may display the client's full key.
        own_license = client.get(portal, timeout=120); foreign_license = other.get(portal, timeout=120)
        ok('Retained license/domain remains visible only to its assigned client', own_license.status_code == 200
           and domain in own_license.text and foreign_license.status_code == 200 and domain not in foreign_license.text)
        for session, target, data in [(client, consent_target, consent), (owner, activation_target, activation),
                                      (owner, offer_target, offer), (client, accept_target, accept), (owner, activate_target, activate),
                                      (owner, issue_target, issue), (owner, receipt_target, receipt)]:
            response = session.post(target, data=data, timeout=120)
            ok('Captured original1.25 action replays without changing business records ' + data.get('command', data['action']),
               response.status_code == 200 and exact_business(stable, snapshot()))
        ok('Retained licensing and recurring GETs leave original and new journals unchanged', exact_business(stable, snapshot()))

    verify()
    return {'verify': verify}
