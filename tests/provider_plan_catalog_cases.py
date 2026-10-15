"""Actual owner catalog forms and existing order preparation; provider fixture is untouched."""
import json
from release_cases import db
from staff_cases import OWNER


def prepare(env, browser, owner, client, source_id, staff_url, client_url, capture):
    base, qa = env['BASE'], env['QA']
    check, form_for, form_data, soup = (env[k] for k in ['check', 'form_for', 'form_data', 'soup'])
    plan = db('SELECT plan_id FROM ci_service_subscriptions WHERE id=?', [source_id])[0]['plan_id']
    url = base+'/existing/admin/index.php?view=service-plan&record='+plan
    before_calls = env['provider_server'].calls
    response = owner.get(url)
    check('Catalog HTTP GET does not create storage', not db("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ci_provider_plan_catalog'"))
    setup = form_data(form_for(response, 'service_plans_catalog_setup'))
    for delta, label in [({'csrf': 'bad', 'confirm': '1', 'current_password': OWNER}, 'CSRF'), ({'confirm': '1', 'current_password': 'wrong'}, 'password'), ({'current_password': OWNER}, 'confirmation')]:
        rejected = owner.post(url, data={**setup, **delta})
        check('Catalog setup rejects '+label, not db("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ci_provider_plan_catalog'") and OWNER not in rejected.text)
    owner.post(url, data={**setup, 'confirm': '1', 'current_password': OWNER})
    fields = form_data(form_for(owner.get(url), 'service_plans_catalog_save'))
    data = {**fields, 'reseller_id': '42', 'package_type': '811', 'package_label': 'Reviewed <catalog package>', 'package_region': 'Reviewed fixture location', 'review_days': '7', 'limit_websites': '1', 'cost_known': '1', 'cost_amount': '12.5000', 'cost_currency': 'GBP', 'cost_period': '12', 'cost_tax': 'excluded', 'cost_basis': 'shared-account', 'cost_reference': 'Synthetic account reference', 'catalog_enabled': '1', 'reviewed_defaults': '1', 'confirm': '1', 'current_password': OWNER}
    for delta, label in [({'csrf': 'bad'}, 'CSRF'), ({'current_password': 'wrong'}, 'password'), ({'reviewed_defaults': ''}, 'defaults review'), ({'plan_hash': 'a'*64}, 'retail plan fingerprint'), ({'cost_amount': '12e2'}, 'ambiguous cost units'), ({'cost_reference': 'x'*1000}, 'oversized reference'), ({'package_label[]': 'array', 'package_label': ''}, 'array field')]:
        rejected = owner.post(url, data={**data, **delta})
        check('Catalog review refuses '+label, rejected.status_code==200 and not db('SELECT id FROM ci_provider_plan_catalog') and OWNER not in rejected.text and 'service_plans_catalog_save' in rejected.text)
    response = owner.post(url, data=data)
    row = db('SELECT * FROM ci_provider_plan_catalog WHERE id=?', [data['catalog_id']])[0]
    document = json.loads(row['binding_json'])
    check('Catalog HTTP saves typed cost evidence without changing retail price', document['configuration']['cost']['amount']=='12.5000' and document['configuration']['cost']['currency']=='GBP' and document['evidence']=='owner-account-review')
    check('Catalog owner view escapes private package name', 'Reviewed &lt;catalog package&gt;' in response.text and '<catalog package>' not in response.text and '12.5000 GBP' in response.text)
    listing = owner.get(base+'/existing/admin/index.php?view=service-plans')
    check('Owner plan list shows bounded catalog readiness without private cost', 'data-catalog-readiness="ready"' in listing.text and '12.5000' not in listing.text and 'Review provider mapping' in listing.text)
    for suffix in ['&catalog_revision=0', '&catalog_revision=128', '&catalog_revision[]=1']:
        invalid = owner.get(url+suffix)
        check('Invalid historical selection is a safe inline error '+suffix, invalid.status_code==200 and soup(invalid).select_one('.notice.error') is not None and not soup(invalid).select_one('[data-catalog-review]'))
    unauthorized = client.get(url+'&catalog_revision=1')
    check('Client cannot read owner mapping history or cost', not soup(unauthorized).select_one('[data-catalog-review]') and '12.5000' not in unauthorized.text)
    before = db('SELECT * FROM ci_provider_plan_catalog')
    owner.post(url, data=data)
    check('Catalog actual submission replay keeps one immutable mapping', db('SELECT * FROM ci_provider_plan_catalog')==before)
    capture(owner, url, 'provider-plan-catalog-owner')
    context = browser.new_context(viewport={'width': 1440, 'height': 1000})
    context.add_cookies([{'name': cookie.name, 'value': cookie.value, 'url': base} for cookie in owner.cookies])
    page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e))); page.goto(staff_url)
    form = page.locator('form[data-catalog-preparation]')
    form.locator('[name=confirm]').check(); form.locator('[name=current_password]').fill(OWNER)
    with page.expect_navigation():
        form.get_by_role('button', name='Prepare from saved mapping', exact=True).click()
    page.wait_for_load_state('networkidle'); page.screenshot(path=str(qa/'provider-plan-catalog-prepared.png'), full_page=True)
    check('Chromium catalog selection prepares an existing hosting record', not errors and 'Hosting record prepared' in page.content() and not page.locator('.notice.error').count())
    context.close()
    order = db('SELECT id FROM ci_service_orders WHERE agreement_id=?', [source_id])[0]
    binding = json.loads(db('SELECT binding_json FROM ci_hosting_order_links WHERE id=?', [order['id']])[0]['binding_json'])
    check('Catalog HTTP preparation retains exact original mapping provenance', binding['format']==2 and binding['catalog']=={'id': row['id'], 'sha256': row['binding_sha256']})
    progress = soup(client.get(client_url)).select_one('[data-hosting-progress]')
    check('Catalog client progress excludes private cost and provider type', progress and '12.5000' not in progress.get_text() and '811' not in progress.get_text() and row['id'] not in str(progress))
    replacement = form_data(form_for(owner.get(url), 'service_plans_catalog_save'))
    changed = {**data, **replacement, 'package_label': 'Replacement <review>', 'limit_websites': '2', 'limit_disk_mb': '0', 'cost_amount': '19.2500', 'reviewed_defaults': '1', 'confirm': '1', 'current_password': OWNER}
    changed.pop('catalog_enabled', None)
    owner.post(url, data=changed)
    revised = db('SELECT * FROM ci_provider_plan_catalog WHERE plan_id=? ORDER BY revision', [plan])
    check('Owner withdrawal appends a distinct review without rewriting preparation', len(revised)==2 and revised[0]==row and json.loads(revised[1]['binding_json'])['enabled'] is False and json.loads(db('SELECT binding_json FROM ci_hosting_order_links WHERE id=?', [order['id']])[0]['binding_json'])==binding)
    historical_url = url+'&catalog_revision=1'
    historical = owner.get(historical_url)
    history = soup(historical).select_one('[data-catalog-review="1"]')
    check('Historical owner screen shows original cost and limits with current withdrawal status', history is not None and '12.5000 GBP' in history.get_text() and '19.2500' not in history.get_text() and 'Websites: 1' in history.get_text() and 'Mailboxes: not recorded' in history.get_text() and 'Historical review.' in history.get_text() and soup(historical).select_one('[data-catalog-readiness="withdrawn"]') is not None)
    newest = soup(owner.get(url)).select_one('[data-catalog-review="2"]')
    check('Latest review distinguishes zero limits and replacement cost', newest is not None and 'Disk space (MB): 0' in newest.get_text() and 'Websites: 2' in newest.get_text() and '19.2500 GBP' in newest.get_text())
    check('Historical display never projects authentication fingerprints', document['credential_sha256'] not in historical.text and document['owner_auth'] not in historical.text)
    capture(owner, historical_url, 'provider-plan-catalog-history')
    capture(owner, base+'/existing/admin/index.php?view=service-plans', 'provider-plan-catalog-list')
    check('History and readiness navigation keep stored reviews unchanged', db('SELECT * FROM ci_provider_plan_catalog WHERE plan_id=? ORDER BY revision', [plan])==revised)
    check('Catalog setup review and preparation make no provider call', env['provider_server'].calls==before_calls)
