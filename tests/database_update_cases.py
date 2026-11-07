"""Unified owner-reviewed database update HTTP and browser acceptance."""
import json
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from staff_cases import OWNER
from database_update_forms import database_apply, database_plan, form_pairs


def run_browser(env,browser):
    from release_cases import db
    globals().update({key:env[key] for key in ['BASE','QA','check','soup','token','form_for','submit']})
    admin=BASE+'/existing/admin/index.php';upgrade=admin+'?view=studio-upgrade'

    def marker():return db("SELECT value FROM ci_settings WHERE name='contacts_schema_version'")
    def contacts_tables():return db("SHOW TABLES LIKE 'ci_studio_contacts'")
    def login_owner():
        session=requests.Session();submit(session,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER});return session

    owner=login_owner();before_marker=marker();before_tables=contacts_tables();before_audit=db("SELECT COUNT(*) AS n FROM ci_audit WHERE action LIKE 'database.update.%'")[0]['n']
    page=owner.get(upgrade)
    check('Database overview GET is read-only and complete',page.status_code==200 and 'Check database updates' in page.text and 'Registered feature schemas' in page.text and 'Database update history' in page.text and marker()==before_marker and contacts_tables()==before_tables and db("SELECT COUNT(*) AS n FROM ci_audit WHERE action LIKE 'database.update.%'")[0]['n']==before_audit)

    csrf=token(page)
    for action in ['studio_migrate','studio_contacts_setup']:
        response=owner.post(upgrade,data={'csrf':csrf,'action':action,'confirm':'1'})
        check('Legacy database action is refused: '+action,'Use Version &amp; database' in response.text and marker()==before_marker and contacts_tables()==before_tables)

    planned,location,review=database_plan(owner,upgrade,['contacts_schema_version'],soup,form_for)
    review_hash=parse_qs(urlparse(location).query).get('database_review',[''])[0]
    apply_form=form_for(review,'studio_database_apply')
    check('Contacts review is exact, non-mutating and passwordless',planned.status_code==303 and len(review_hash)==64 and marker()==before_marker and contacts_tables()==before_tables and apply_form.select_one('[name="database_updates[]"][value="contacts_schema_version"]') is not None and apply_form.select_one('[name=review_hash][value="'+review_hash+'"]') is not None and apply_form.select_one('[name=confirm]') is not None and not apply_form.select('[name=current_password],[name=owner_password]'))
    response=database_apply(owner,location,review,form_for,True,{'csrf':'invalid'})
    check('Database apply rejects invalid CSRF without mutation','expired' in response.text.lower() and marker()==before_marker and contacts_tables()==before_tables)
    response=database_apply(owner,location,review,form_for,False)
    check('Database apply requires exact backup confirmation','reviewed the exact ordered database plan' in response.text and marker()==before_marker and contacts_tables()==before_tables)

    db("INSERT INTO ci_settings (name,value) VALUES ('contacts_schema_version','bogus') ON DUPLICATE KEY UPDATE value='bogus'")
    response=database_apply(owner,location,review,form_for,True)
    check('Changed database state expires review before migration',('changed' in response.text.lower() or 'unsupported' in response.text.lower()) and not contacts_tables())
    db("DELETE FROM ci_settings WHERE name='contacts_schema_version'")

    changed=login_owner();_,changed_location,changed_review=database_plan(changed,upgrade,['contacts_schema_version'],soup,form_for)
    old_auth=db("SELECT auth_version FROM ci_users WHERE email='owner@example.invalid'")[0]['auth_version'];db("UPDATE ci_users SET auth_version=? WHERE email='owner@example.invalid'",['b'*32])
    response=database_apply(changed,changed_location,changed_review,form_for,True)
    check('Changed owner session refuses reviewed apply',('Sign in' in response.text or 'login' in response.url) and not marker() and not contacts_tables())
    db("UPDATE ci_users SET auth_version=? WHERE email='owner@example.invalid'",[old_auth]);changed.close()

    delegated=login_owner();_,delegated_location,delegated_review=database_plan(delegated,upgrade,['contacts_schema_version'],soup,form_for)
    owner_id=db("SELECT id FROM ci_users WHERE email='owner@example.invalid'")[0]['id'];profile=db('SELECT role,permissions FROM ci_staff_profiles WHERE user_id=?',[owner_id])[0]
    db("UPDATE ci_staff_profiles SET role='administrator',permissions='[\"staff.manage\"]' WHERE user_id=?",[owner_id])
    response=database_apply(delegated,delegated_location,delegated_review,form_for,True)
    check('Delegated staff management cannot replace owner authority','Only the active owner' in response.text and not marker() and not contacts_tables())
    db('UPDATE ci_staff_profiles SET role=?,permissions=? WHERE user_id=?',[profile['role'],profile['permissions'],owner_id]);delegated.close()

    support=login_owner();support_page=support.get(admin+'?view=support');support_form=None
    for candidate in soup(support_page).select('form'):
        action=candidate.select_one('[name=action][value=support_start]');client=candidate.select_one('[name=client_id][value="1"]')
        if action and client:support_form=candidate;break
    if support_form is None:raise AssertionError('Missing owner support-start form')
    support.post(admin+'?view=support',data=form_pairs(support_form),timeout=30)
    plan_page=support.get(upgrade);plan_form=form_for(plan_page,'studio_database_plan');plan_data=[pair for pair in form_pairs(plan_form) if pair[0]!='database_updates[]']+[('database_updates[]','contacts_schema_version')]
    response=support.post(upgrade,data=plan_data,timeout=30)
    check('Bound support review cannot plan a database mutation','End client review' in response.text and not marker() and not contacts_tables())
    support.close()

    planned,location,review=database_plan(owner,upgrade,['contacts_schema_version'],soup,form_for)
    response=database_apply(owner,location,review,form_for,True)
    history=db("SELECT action,detail FROM ci_audit WHERE action IN ('database.update.started','database.update.completed') ORDER BY id")
    decoded=[(entry['action'],json.loads(entry['detail'])['marker']) for entry in history]
    check('Unified owner apply prepares contacts and records ordered outcomes','Database updates completed: Contacts' in response.text and marker()==[{'value':'1'}] and bool(contacts_tables()) and decoded[-2:]==[('database.update.started','contacts_schema_version'),('database.update.completed','contacts_schema_version')])
    current=owner.get(upgrade)
    check('Completed feature rechecks current and is no longer selectable','Contacts</strong> — Current' in current.text and soup(current).select_one('[name="database_updates[]"][value="contacts_schema_version"]') is None)

    context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');browser_page=context.new_page();errors=[];browser_page.on('pageerror',lambda error:errors.append(str(error)))
    browser_page.goto(admin+'?view=login');browser_page.locator('[name=email]').fill('owner@example.invalid');browser_page.locator('[name=password]').fill(OWNER);browser_page.locator('button[type=submit]').click();browser_page.wait_for_url('**/admin/index.php')
    browser_page.goto(upgrade,wait_until='networkidle');browser_page.locator('[name="database_updates[]"][value="notification_feed_version"]').check();browser_page.get_by_role('button',name='Review selected updates').click();browser_page.wait_for_url('**database_review=*')
    expect(browser_page.locator('[data-database-review]')).to_be_visible();expect(browser_page.locator('[data-database-history]')).to_contain_text('Contacts')
    browser_page.screenshot(path=str(QA/'Database-Updates-Desktop.png'),full_page=True);check('Database review desktop fits viewport',browser_page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    browser_page.set_viewport_size({'width':390,'height':844});browser_page.screenshot(path=str(QA/'Database-Updates-Mobile.png'),full_page=True);check('Database review mobile fits viewport',browser_page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1') and not errors)
    context.close();owner.close()
