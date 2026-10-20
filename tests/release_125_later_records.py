"""Create candidate-only agreement/recurring/license records AFTER the real1.24 upgrade."""
import datetime
import hashlib
import json
import os
import subprocess
from urllib.parse import parse_qs, urlparse

from bs4 import BeautifulSoup
from release_125_retention import exact_business, retained


def one_record(response, key):
    values = parse_qs(urlparse(response.url).query).get(key, [])
    assert response.status_code == 200 and len(values) == 1 and values[0], ('Missing current redirect identity', key)
    return values[0]


def prepare(repo, installed, base, php, owner, client, other, client_id, job_id, password, fields, submit, snapshot, ok):
    admin, portal = base + '/admin/index.php', base + '/portal/index.php'
    before = snapshot()
    plans = admin + '?view=service-plans'
    response = submit(owner, plans, 'service_plans_setup', {'current_password': password})
    ok('Candidate explicitly prepares agreements without changing original rows',
       response.status_code == 200 and 'Service agreements are ready' in response.text and retained(before, snapshot()))
    response = submit(owner, admin + '?view=service-plan', 'service_plans_create',
                      {'name': 'Retained release website care', 'cadence': 'monthly', 'price': '129.00',
                       'inclusions': 'Reviewed updates\nMonthly report', 'exclusions': 'New features',
                       'limits': 'updates = 4', 'support': 'Two business days', 'current_password': password})
    plan = one_record(response, 'record')
    detail = admin + '?view=service-plan&record=' + plan
    response = submit(owner, detail, 'service_plans_publish', {'confirm': '1', 'current_password': password})
    ok('Candidate owner publishes exact service terms', response.status_code == 200 and 'Offer these terms to a client' in response.text)
    response = submit(owner, detail, 'service_plans_offer', {'client_id': str(client_id), 'confirm': '1', 'current_password': password})
    agreement = one_record(response, 'record')
    client_agreement = portal + '?agreement=' + agreement
    consent_target, consent = fields(client.get(client_agreement, timeout=120), 'service_agreements_consent')
    consent['confirm'] = '1'
    response = client.post(consent_target, data=consent, timeout=120)
    ok('Original activated client consents to candidate terms without a payment mandate',
       response.status_code == 200 and 'awaiting studio activation' in response.text and 'does not authorize automatic payments' in response.text)
    agreement_owner = admin + '?view=service-agreement&record=' + agreement
    activation_target, activation = fields(owner.get(agreement_owner, timeout=120), 'service_plans_activate')
    activation.update({'confirm': '1', 'current_password': password})
    owner.post(activation_target, data=activation, timeout=120)
    ok('Original client sees activated candidate agreement', 'Active agreement' in client.get(client_agreement, timeout=120).text)

    listing = admin + '?view=recurring-billing'
    project = listing + '&job=' + str(job_id)
    client_project = portal + '?recurring=1&job=' + str(job_id)
    payment_marker = hashlib.sha256(json.dumps({'name': 'payments_schema_version'}, separators=(',', ':')).encode()).hexdigest()
    marker_cell = ('settings', payment_marker, 'value')
    pre_payment = snapshot()
    requests_before = pre_payment['tables']['payment_requests']['rows']
    ok('Retained upgrade still carries payment schema 1 before explicit owner migration',
       pre_payment['tables']['settings']['rows'][payment_marker]['value'] == hashlib.sha256(b'"1"').hexdigest()
       and all('link_hash' not in row and 'link_secret' not in row for row in requests_before.values()))
    response = submit(owner, admin + '?view=payments', 'payments_migrate', {'owner_password': password, 'confirm': '1'})
    migrated = snapshot()
    null_cell = hashlib.sha256(b'null').hexdigest()
    requests_after = migrated['tables']['payment_requests']['rows']
    ok('Owner payment migration sets schema 2 with nullable link fields and retained rows keys receipts',
       response.status_code == 200 and 'Payment preparation enabled' in response.text
       and migrated['tables']['settings']['rows'][payment_marker]['value'] == hashlib.sha256(b'"2"').hexdigest()
       and retained(pre_payment, migrated, changed_cells=[marker_cell])
       and all(requests_after[key]['link_hash'] == null_cell and requests_after[key]['link_secret'] == null_cell
               for key in requests_before))
    response = submit(owner, listing, 'recurring_setup', {'owner_password': password, 'confirm': '1'})
    ok('Candidate recurring preparation is explicit and keeps original invoices', response.status_code == 200
       and retained(before, snapshot(), changed_cells=[marker_cell]))
    anchor = (datetime.datetime.now(datetime.timezone.utc).date() + datetime.timedelta(days=60)).isoformat()
    offer_target, offer = fields(owner.get(project + '&source=' + agreement, timeout=120), 'recurring_offer')
    offer.update({'title': 'Retained release recurring care', 'terms': 'Exact candidate terms; invoice only, no automatic charge.',
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

    # This is explicitly a NEW candidate-only historical fixture, never original1.24 data.
    # It uses retained production documents to establish historical eligibility; real HTTP issues the invoice.
    fixture = subprocess.run([php, str(repo / 'tests/recurring_billing_browser_fixture.php'),
                              str(installed), str(client_id), str(job_id)], capture_output=True, text=True,
                             env={**os.environ, 'CWP_TEST_DATABASE': 'cwp_test'}, timeout=120)
    assert fixture.returncode == 0, ('Historical candidate fixture', fixture.stderr)
    historical = json.loads(fixture.stdout)['schedule_id']
    period = project + '&schedule=' + historical + '&period_date=2020-02-01'
    client_period = client_project + '&schedule=' + historical + '&period_date=2020-02-01'
    issue_target, issue = fields(owner.get(period, timeout=120), 'issue', field='command')
    issue['confirm'] = '1'
    response = owner.post(issue_target, data=issue, timeout=120)
    invoice_id = one_record(response, 'invoice_id')
    invoice = admin + '?view=invoice-payments&invoice_id=' + invoice_id
    receipt_target, receipt = fields(owner.get(invoice, timeout=120), 'payments_ledger_receive')
    receipt.update({'amount': '5.00', 'method': 'bank', 'reference': 'Synthetic candidate recurring receipt',
                    'received_on': datetime.datetime.now(datetime.timezone.utc).date().isoformat(), 'confirm': '1'})
    response = owner.post(receipt_target, data=receipt, timeout=120)
    ok('Candidate-only historical period issues one invoice and one actual HTTP receipt', response.status_code == 200
       and snapshot()['tables']['recurring_billing_invoices']['count'] == 1
       and 'Invoice balance: $20.00 USD' in client.get(client_period, timeout=120).text)

    licenses = admin + '?view=studio-licenses'
    response = submit(owner, licenses, 'studio_license_setup', {'current_password': password})
    ok('Candidate explicitly prepares licensing while keeping original records', response.status_code == 200
       and 'Website licensing storage prepared' in response.text and retained(before, snapshot(), changed_cells=[marker_cell]))
    response = submit(owner, licenses, 'studio_license_create', {'customer_id': str(client_id), 'product_id': 'generic'})
    forms = BeautifulSoup(response.text, 'html.parser').select('input[name=action][value=studio_domain_add]')
    ok('Candidate creates one normal client license without building another product', response.status_code == 200
       and len(forms) == 1 and snapshot()['tables']['website_licenses']['count'] == 1)
    license_id = forms[0].find_parent('form').select_one('[name=license_id]')['value']
    domain = 'release-retained.example'
    response = submit(owner, licenses, 'studio_domain_add', {'domain': domain}, {'license_id': license_id})
    ok('Candidate normal license records exact client domain locally', response.status_code == 200 and domain in response.text)

    def verify():
        stable = snapshot()
        own = client.get(client_agreement, timeout=120)
        recurring = client.get(recurring_client, timeout=120)
        statement = client.get(client_period, timeout=120)
        ok('Reapplied candidate keeps actual client consent and active terms', own.status_code == 200 and 'Active agreement' in own.text
           and recurring.status_code == 200 and anchor in recurring.text and 'Retained release recurring care' in recurring.text)
        ok('Reapplied candidate retains exact recurring invoice balance', statement.status_code == 200 and 'Invoice balance: $20.00 USD' in statement.text)
        foreign_agreement = other.get(client_agreement, timeout=120)
        foreign_period = other.get(client_period, timeout=120)
        ok('Other client cannot read retained agreement or recurring invoice', foreign_agreement.status_code == 200
           and 'Subscription unavailable.' in foreign_agreement.text and 'Retained release website care' not in foreign_agreement.text
           and foreign_period.status_code == 200 and 'Project unavailable.' in foreign_period.text
           and 'Invoice balance:' not in foreign_period.text)
        # Do not save this HTML: the current licensing view may display the client's full key.
        own_license = client.get(portal, timeout=120); foreign_license = other.get(portal, timeout=120)
        ok('Reapplied license/domain remains visible only to its assigned client', own_license.status_code == 200
           and domain in own_license.text and foreign_license.status_code == 200 and domain not in foreign_license.text)
        for session, target, data in [(client, consent_target, consent), (owner, activation_target, activation),
                                      (owner, offer_target, offer), (client, accept_target, accept), (owner, activate_target, activate),
                                      (owner, issue_target, issue), (owner, receipt_target, receipt)]:
            response = session.post(target, data=data, timeout=120)
            ok('Captured candidate action replays without changing business records ' + data.get('command', data['action']),
               response.status_code == 200 and exact_business(stable, snapshot()))
        ok('Reapplied licensing and recurring GETs leave original and new journals unchanged', exact_business(stable, snapshot()))

    verify()
    return {'verify': verify}
