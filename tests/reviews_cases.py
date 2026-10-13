"""Actual public intake and private human-review workflows. No remote site fetching or mail."""
import json,time
from datetime import datetime,timezone
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php';public=BASE+'/existing/index.php?page=website-review'
    check('Fresh setup prepares empty review storage',db("SELECT value FROM fresh_settings WHERE name='website_reviews_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_website_reviews'))
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    lead=db("SELECT * FROM ci_leads WHERE service='Website review' ORDER BY id LIMIT 1")[0];detail=root+'?view=website-review&id='+str(lead['id'])
    r=submit(owner,detail,'reviews_setup',{'current_password':OWNER,'csrf':'invalid'})
    check('Review setup rejects CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='website_reviews_schema_version'"))
    r=submit(owner,detail,'reviews_setup',{'current_password':'incorrect'});check('Review setup requires owner password','current password' in r.text.lower())
    r=submit(owner,detail,'reviews_setup',{'current_password':OWNER});check('Review setup preserves existing inquiry and does not guess its URL',db('SELECT * FROM ci_leads WHERE id=?',[lead['id']])==[lead] and not db('SELECT * FROM ci_website_reviews') and 'Start this review' in r.text)
    r=submit(owner,detail,'reviews_adopt',{'website':'example.org/legacy'})
    check('Explicit HTTP legacy adoption starts review',r.status_code==200 and 'https://example.org/legacy' in r.text and db('SELECT stage FROM ci_website_reviews WHERE lead_id=?',[lead['id']])==[{'stage':'received'}])
    member_id=db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id'];original=db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[member_id])[0]['permissions']
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Review data denied without website grant',member.get(detail).status_code==403)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[json.dumps(json.loads(original)+['website.manage']),member_id])
    check('Current website staff can access review',member.get(detail).status_code==200 and 'https://example.org/legacy' in member.get(detail).text)
    r=submit(owner,detail,'reviews_stage',{'stage':'in-review'})
    def report(**changes):
        r=owner.get(detail);fields=form_data(soup(r).select_one('[data-review-editor]'));fields.update(changes);return owner.post(detail,data=fields)
    fields={'summary':'Preserved report summary','findings[0][title]':'<img src=x onerror=alert(1)>','findings[0][detail]':'Synthetic finding only','findings[0][evidence]':'lab-data','findings[0][source]':'','findings[0][observed_at]':''}
    r=report(**fields)
    check('Incomplete evidence preserves draft values without saving','Preserved report summary' in r.text and not db('SELECT * FROM ci_website_review_reports') and not soup(r).select('img[onerror]'))
    fields['findings[0][evidence]']='human-review';r=report(**fields)
    check('HTTP saved report stays undelivered and escaped',db('SELECT delivered_at FROM ci_website_review_reports')==[{'delivered_at':None}] and not soup(r).select('img[onerror]') and 'Record external delivery' not in r.text)
    stale=form_data(soup(owner.get(detail)).select_one('[data-review-editor]'));before_revision=stale['revision']
    r=submit(owner,detail,'reviews_stage',{'stage':'report-drafted'});stale['summary']='Uncommitted stale summary';r=owner.post(detail,data=stale)
    check('Stale review form keeps old revision and submitted content','review changed' in r.text and soup(r).select_one('[data-review-editor] [name=revision]')['value']==before_revision and 'Uncommitted stale summary' in r.text and db('SELECT summary FROM ci_website_review_reports')[0]['summary']=='Preserved report summary')
    before=db('SELECT * FROM ci_website_reviews');r=owner.post(detail,data={'csrf':token(owner.get(detail)),'action':'reviews_stage','id':lead['id'],'revision':before[0]['revision'],'stage':'delivered'})
    check('Forged delivery stage cannot bypass confirmation',db('SELECT * FROM ci_website_reviews')==before)
    r=submit(owner,detail,'reviews_delivery',{'delivered_at':datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%S'),'channel':'email','reference':'Synthetic external delivery reference','current_password':'wrong'})
    check('External delivery needs fresh password and never echoes it','current password' in r.text.lower() and all(n.get('value','')=='' for n in soup(r).select('[name=current_password]')))
    r=submit(owner,detail,'reviews_delivery',{'delivered_at':datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%S'),'channel':'email','reference':'Synthetic external delivery reference','current_password':OWNER})
    check('HTTP records external delivery without sending and locks report','External delivery recorded' in r.text and 'not a delivery confirmation from a mail provider' in r.text and not soup(r).select('[data-review-editor]'))
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[original,member_id]);check('Removed website grant immediately hides review',member.get(detail).status_code==403)
    prospect=requests.Session();r=prospect.get(public);input=soup(r).select_one('[name=current_url]');check('Public address input allows deliberate bare-host normalization',input['type']=='text' and input['maxlength']=='500')
    data=form_data(soup(r).select_one('[data-growth-form]'));data.update(name='Structured review prospect',email='structured-review@example.invalid',message='Please review this synthetic business website.',consent='1',current_url='Example.org/structured')
    time.sleep(2.1);r=prospect.post(public,data=data,headers={'Accept':'application/json'});check('HTTP bare hostname creates structured public review',r.status_code==200 and r.json()['ok'] and db("SELECT r.website FROM ci_website_reviews r JOIN ci_leads l ON l.id=r.lead_id WHERE l.email='structured-review@example.invalid'")==[{'website':'https://example.org/structured'}])
    original_reference=r.json()['result']['reference'];r=prospect.post(public,data=data,headers={'Accept':'application/json'});check('HTTP replay remains one inquiry and review',r.json()['result']['reference']==original_reference and len(db("SELECT id FROM ci_leads WHERE email='structured-review@example.invalid'"))==1)
    reject=requests.Session();data=form_data(soup(reject.get(public)).select_one('[data-growth-form]'));data.update(name='Rejected fragment',email='fragment@example.invalid',message='Please review this synthetic website.',consent='1',current_url='https://example.org/#fragment');time.sleep(2.1);r=reject.post(public,data=data,headers={'Accept':'application/json'});check('HTTP fragment rejection is explicit and writes no lead',r.status_code==422 and not db("SELECT id FROM ci_leads WHERE email='fragment@example.invalid'"))
    for path in ['app/marketing/reviews.php','app/marketing/review_content.php','database/schema/reviews_schema.php','database/migrations/reviews_migrate.php','admin/controllers/reviews.php','admin/views/reviews.php','includes/foundation/review-requests/ReviewReport.php']:
        check('Review internal HTTP denial '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    context=browser.new_context(viewport={'width':1440,'height':1000});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(public);page.locator('[name=current_url]').fill('example.org/browser-review');page.locator('[name=name]').fill('Browser review prospect');page.locator('[name=email]').fill('review-browser@example.invalid');page.locator('[name=message]').fill('Please review this synthetic browser website.');page.locator('[name=consent]').check();page.wait_for_timeout(2100);page.get_by_role('button',name='Send my request').click();expect(page.locator('[data-growth-success]')).to_be_visible()
    lid=db("SELECT id FROM ci_leads WHERE email='review-browser@example.invalid'")[0]['id'];url=root+'?view=website-review&id='+str(lid)
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard');page.goto(url)
    page.locator('[name=stage]').select_option('in-review');page.get_by_role('button',name='Change review stage').click();editor=page.locator('[data-review-editor]');expect(editor).to_be_visible()
    editor.locator('[name=summary]').fill('Synthetic human review summary.');editor.locator('[name="findings[0][title]"]').fill('Contact path');editor.locator('[name="findings[0][detail]"]').fill('Make the agreed contact route easy to find.');editor.get_by_role('button',name='Add another finding').click();expect(page.locator('[name="findings[1][title]"]')).to_be_visible();page.get_by_role('button',name='Remove this finding from draft').last.click();expect(page.locator('[name="findings[1][title]"]')).to_have_count(0);page.get_by_role('button',name='Save report draft',exact=True).click()
    expect(page.get_by_role('heading',name='Saved report',exact=True)).to_be_visible();check('Chromium saves human findings and add/remove rows preserves input',db('SELECT summary FROM ci_website_review_reports r JOIN ci_website_reviews w ON w.id=r.review_id WHERE w.lead_id=?',[lid])[0]['summary']=='Synthetic human review summary.')
    page.screenshot(path=str(QA/'Website-Review-Desktop.png'),full_page=True);page.set_viewport_size({'width':390,'height':844});expect(page.locator('.admin-sidebar')).not_to_be_in_viewport();page.screenshot(path=str(QA/'Website-Review-Mobile.png'),full_page=True)
    check('Website review form fits mobile',page.evaluate('document.documentElement.scrollWidth <= innerWidth'));check('Review workflow has no browser errors',not errors);context.close()
