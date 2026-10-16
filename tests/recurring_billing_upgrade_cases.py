"""Retained 1.23 upgrade, genuine consent and recurring invoice recovery through HTTP."""
import datetime
import json
import os
from pathlib import Path
import subprocess
from urllib.parse import parse_qs, urlparse

from bs4 import BeautifulSoup
from release_version import RECOVERY


def run(env):
    url, label, check = (env[k] for k in ['url', 'label', 'check'])
    owner, client, records, action = (env[k] for k in ['session', 'client', 'records', 'action'])
    fields, submit, password, private = (env[k] for k in ['fields', 'submit', 'password', 'private'])
    admin, portal = url + '/admin/index.php', url + '/portal/index.php'
    listing = admin + '?view=recurring-billing'
    project = listing + '&job=1'
    client_project = portal + '?recurring=1&job=1'

    def command(session, target, name):
        response = session.get(target)
        assert response.status_code == 200, response.status_code
        for form in BeautifulSoup(response.text, 'html.parser').select('form'):
            marker = form.select_one('[name=command]')
            if marker is not None and marker.get('value') == name:
                # Recurring commands consist of text/hidden inputs and a reason textarea.
                return {n['name']: n.get_text() if n.name == 'textarea' else n.get('value', '')
                        for n in form.select('input[name], textarea[name]')
                        if n.get('type') not in ['submit', 'password', 'checkbox']}
        raise AssertionError('Missing recovered recurring command: ' + name)

    before = records()
    original_invoices = before['studio_invoices']
    keys = {name: (private / name).read_bytes()
            for name in ['mail-master.key', 'provider-master.key', 'retained-customer.php']}
    check(label + ' recurring upgrade requires explicit owner preparation',
          'Prepare recurring billing' in owner.get(listing).text)
    submit(owner, listing, 'recurring_setup', {'owner_password': password, 'confirm': '1'})
    empty = records('recurring')
    check(label + ' recurring additive setup preserves existing invoices and starts empty',
          all(not empty[name] for name in ['recurring_billing_sources', 'recurring_billing_schedules',
                                          'recurring_billing_events', 'recurring_billing_invoices'])
          and records()['studio_invoices'] == original_invoices)

    source = records('plans')['service_subscriptions'][0]['id']
    offer_url = project + '&source=' + source
    anchor = (datetime.datetime.now(datetime.timezone.utc).date() + datetime.timedelta(days=60)).isoformat()
    offer = dict(fields(owner, offer_url, 'recurring_offer'), title='Retained recurring website care',
                 terms='Exact recurring terms retained after 1.23 update', anchor=anchor,
                 alignment='day-of-month', timezone='America/Chicago', due_after_days='14',
                 intent='publish', confirm='1')
    response = owner.post(offer_url, data=offer)
    schedule = parse_qs(urlparse(response.url).query)['schedule'][0]
    detail = project + '&schedule=' + schedule
    client_detail = client_project + '&schedule=' + schedule
    consent = dict(command(client, client_detail, 'accept'), confirm='1')
    client.post(client_detail, data=consent)
    activation = dict(command(owner, detail, 'activate'), confirm='1')
    owner.post(detail, data=activation)
    initial = records('recurring')
    document = json.loads(initial['recurring_billing_schedules'][0]['journal_json'])
    check(label + ' upgraded actual client consents to exact recurring terms before activation',
          [e['command'] for e in document['events']] == ['accept', 'activate']
          and document['events'][0]['actor'] == 'client:1'
          and document['terms']['amount_cents'] == 12900
          and document['terms']['anchor'] == anchor
          and not initial['recurring_billing_invoices'])

    client_center, staff_center = portal+'?activity=1', admin+'?view=activity'
    offer_key, consent_key = 'recurring-offer:'+schedule, 'recurring-event:'+consent['request_id']
    activation_key = 'recurring-event:'+activation['request_id']
    for actor, center, key in [(client, client_center, offer_key), (owner, staff_center, consent_key)]:
        mark = dict(fields(actor, center, 'activity_mark'), keys=json.dumps([key]), state='read')
        actor.post(center, data=mark)
    reads = records('recurring')['activity_reads']
    check(label + ' upgraded recurring offer and consent retain separate personal reads',
          any(r['actor_type']=='client' and r['event_key']==offer_key for r in reads)
          and any(r['actor_type']=='admin' and r['event_key']==consent_key for r in reads)
          and not any(r['event_key']==activation_key for r in reads))

    def exact_activity():
        response = client.get(client_center+'&open='+activation_key)
        staff_response = owner.get(staff_center+'&open='+consent_key)
        return (response.status_code==200 and staff_response.status_code==200
                and urlparse(response.url).fragment=='recurring-event-'+activation['request_id']
                and urlparse(staff_response.url).fragment=='recurring-event-'+consent['request_id']
                and BeautifulSoup(response.text,'html.parser').find(id='recurring-event-'+activation['request_id']) is not None
                and BeautifulSoup(staff_response.text,'html.parser').find(id='recurring-event-'+consent['request_id']) is not None)

    # Only the historical dates are seeded. Invoice/payment actions remain real HTTP.
    # No clock override or future-period eligibility bypass enters the shipped code.
    fixture = subprocess.run([env['php'], str(Path(__file__).with_name('recurring_billing_browser_fixture.php')),
                              str(env['root']), '1', '1'], capture_output=True, text=True,
                             env={**os.environ, 'CWP_TEST_DATABASE': 'cwp_test'})
    assert fixture.returncode == 0, fixture.stdout + fixture.stderr
    historical = json.loads(fixture.stdout)['schedule_id']
    period = project + '&schedule=' + historical + '&period_date=2020-02-01'
    client_period = client_project + '&schedule=' + historical + '&period_date=2020-02-01'
    issue = dict(command(owner, period, 'issue'), confirm='1')
    response = owner.post(period, data=issue)
    invoice_id = parse_qs(urlparse(response.url).query)['invoice_id'][0]
    invoice = admin + '?view=invoice-payments&invoice_id=' + invoice_id
    receipt = dict(fields(owner, invoice, 'payments_ledger_receive'), amount='5.00', method='bank',
                   reference='Synthetic retained recurring receipt',
                   received_on=datetime.datetime.now(datetime.timezone.utc).date().isoformat(), confirm='1')
    owner.post(invoice, data=receipt)
    saved = records('recurring')
    check(label + ' historical recurring period issues one invoice with retained partial balance',
          len(saved['recurring_billing_invoices']) == 1
          and 'Recorded payments: $5.00 USD' in client.get(client_period).text
          and 'Invoice balance: $20.00 USD' in client.get(client_period).text)

    stable = records()
    earlier = {name: records(name) for name in ['financial', 'planning', 'progress', 'tickets', 'plans',
                                               'submissions', 'drafts', 'provider', 'mail-accounts', 'templates']}
    # Shared invoice/receipt rows are compared by the recurring snapshot, so a later
    # intentional receipt need not rewrite the baseline for unrelated financial terms.
    for name in ['studio_invoices', 'invoice_payments']:
        earlier['financial'].pop(name)

    def retained(expected):
        current = {name: records(name) for name in earlier}
        for name in ['studio_invoices', 'invoice_payments']:
            current['financial'].pop(name)
        return (records('recurring') == expected and current == earlier
                and all(records()[name] == stable[name] for name in ['studio_clients', 'studio_jobs'])
                and all((private / name).read_bytes() == data for name, data in keys.items()))

    journal = private / RECOVERY / 'journal.php'
    journal_before = journal.read_bytes()
    records('owner-rate-exhaust')
    response = action('recover')
    check(label + ' throttled recovery preserves recurring consent invoices receipts and keys',
          'Please wait before another owner authentication attempt.' in response.text
          and journal.read_bytes() == journal_before and retained(saved))
    records('owner-rate-reset')
    response = action('recover')
    check(label + ' old release retains recurring data without claiming recurring controls',
          '<h2>Rolled-back</h2>' in response.text and retained(saved)
          and 'Retained recurring website care' not in client.get(client_detail).text
          and BeautifulSoup(owner.get(detail).text, 'html.parser').select_one('[name=command]') is None)
    response = action('apply')
    check(label + ' reapplication restores recurring terms dates and partial invoice balance',
          '<h2>Complete</h2>' in response.text and retained(saved)
          and 'Retained recurring website care' in client.get(client_detail).text
          and anchor in client.get(client_detail).text
          and 'Invoice balance: $20.00 USD' in client.get(client_period).text)

    check(label + ' first reapplication restores exact recurring destinations and original read status',
          exact_activity() and retained(saved))

    owner.post(offer_url, data=offer)
    client.post(client_detail, data=consent)
    owner.post(detail, data=activation)
    check(label + ' original recurring offer consent and activation replay without new events', retained(saved))
    response = owner.post(period, data=issue)
    check(label + ' recovered original period request reopens the original unique invoice',
          retained(saved) and 'invoice_id=' + invoice_id in response.url)
    owner.post(invoice, data=receipt)
    check(label + ' original recurring receipt replay cannot duplicate payment', retained(saved))

    submit(owner, invoice, 'payments_ledger_receive', {
        'amount': '2.00', 'method': 'bank', 'reference': 'Synthetic recovered recurring receipt',
        'received_on': datetime.datetime.now(datetime.timezone.utc).date().isoformat(), 'confirm': '1'})
    paid = records('recurring')
    check(label + ' recovered recurring invoice accepts one additional authorized receipt',
          len(paid['invoice_payments']) == len(saved['invoice_payments']) + 1
          and paid['recurring_billing_events'] == saved['recurring_billing_events']
          and paid['recurring_billing_invoices'] == saved['recurring_billing_invoices']
          and retained(paid) and 'Invoice balance: $18.00 USD' in client.get(client_period).text)
    ending = dict(command(client, client_detail, 'end'), confirm='1', reason='End future recovered billing')
    client.post(client_detail, data=ending)
    ended = records('recurring')
    check(label + ' recovered actual client can end future billing with retained history',
          len(ended['recurring_billing_events']) == len(paid['recurring_billing_events']) + 1
          and ended['recurring_billing_invoices'] == paid['recurring_billing_invoices']
          and retained(ended) and 'End future recovered billing' in client.get(client_detail).text)
    response = action('recover')
    check(label + ' second recovery preserves ended schedule new receipt and earlier business data',
          '<h2>Rolled-back</h2>' in response.text and retained(ended))
    response = action('apply')
    check(label + ' second reapplication retains ended schedule and original invoice balance',
          '<h2>Complete</h2>' in response.text and retained(ended)
          and 'End future recovered billing' in client.get(client_detail).text
          and 'Invoice balance: $18.00 USD' in client.get(client_period).text)
    client.post(client_detail, data=ending)
    response = owner.post(period, data=issue)
    check(label + ' second recovered end and invoice replays retain exact original records',
          retained(ended) and 'invoice_id=' + invoice_id in response.url)

    check(label + ' second reapplication preserves original recurring notice destinations and personal reads',
          exact_activity() and retained(ended))
