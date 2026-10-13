"""Real retained 1.10 update and portfolio route/keyboard/history/no-JS acceptance."""
import json, shutil, subprocess, zipfile
from urllib.parse import urlencode
import requests
from playwright.sync_api import expect
from release_cases import db

def upgrade():
    dest=WEB/'portfolio-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.10.0',dest)
    seed=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-portfolio-upgrade'}),text=True,capture_output=True)
    (QA/'portfolio-upgrade-seed.log').write_text(seed.stdout+seed.stderr,encoding='utf-8');seed.check_returncode()
    shutil.copyfile(ROOT/'test-runtime/portfolio-upgrade-config.php',dest/'config.php')
    (dest/'.installed.php').write_text('<?php exit; ?>retained guard');(dest/'unrelated.txt').write_text('Retain unrelated file')
    private=ROOT/'test-runtime/portfolio-upgrade-private'
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',private/'media/retained.php',private/'payment-master.key',*list((private/'deliveries').glob('*'))]}
    tables=['staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_messages','studio_versions','studio_invoices','catalog_products','catalog_revisions','catalog_invoice_snapshots','payment_gateways','payment_requests','payment_attempts','payment_attempt_events','payment_notifications','invoice_payments','website_releases','delivery_events','activity_reads','settings','leads','projects']
    before={t:db('SELECT * FROM portfolioup_'+t) for t in tables}
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Actual 1.10 update preserves configuration guards private media ZIPs and master key',all(p.read_bytes()==b for p,b in preserved.items()))
    page=requests.get(BASE+'/portfolio-upgrade/index.php?page=work&cat=Construction')
    check('Retained 1.10 portfolio gains real server category filtering',page.status_code==200 and len(soup(page).select('.project-card:not([hidden])'))==1)
    check('Actual 1.10 update preserves read markers settings and all business records without migration',all(db('SELECT * FROM portfolioup_'+t)==rows for t,rows in before.items()))
    client=requests.Session();portal=BASE+'/portfolio-upgrade/portal/index.php'
    submit(client,portal,'login',{'email':'retained@example.invalid','password':'Synthetic retained client passphrase'})
    r=client.get(portal+'?activity=1');check('Retained client activity still renders after portfolio update',r.status_code==200 and 'Studio message' in r.text)
    check('Retained read marker survives client browsing',db('SELECT * FROM portfolioup_activity_reads')==before['activity_reads'] and len(before['activity_reads'])==1)
    published=next(v for v in before['website_releases'] if v['status']=='published')
    check('Retained client downloads identical website ZIP after portfolio update',client.get(portal+'?download_release='+published['id']).content==(ROOT/'test-runtime/portfolio-upgrade.zip').read_bytes())
    owner=requests.Session();admin=BASE+'/portfolio-upgrade/admin/index.php'
    submit(owner,admin+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    check('Retained invoice balance survives portfolio update','$375.00' in owner.get(admin+'?view=invoice-payments&invoice_id=3').text)
    r=submit(owner,admin+'?view=payment-settings','payments_save',{'owner_password':'Synthetic upgrade passphrase 2026'})
    check('Retained encrypted credentials still decrypt after portfolio update','Credentials protected and saved' in r.text)

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php']})
    upgrade();base=BASE+'/existing/index.php';work=base+'?page=work'
    for query in ['cat=Unknown-Category','cat=all','cat=ConstructionExtra','cat=construction']:
        r=requests.get(work+'&'+query);doc=soup(r)
        check('Exact unknown category hides every card '+query,r.status_code==200 and not doc.select('.project-card:not([hidden])') and not doc.select_one('[data-portfolio-empty]').has_attr('hidden'))
    r=requests.get(base+'?page=project&slug=draft-fixture');check('Draft case study returns 404 without its title',r.status_code==404 and 'Draft fixture' not in r.text)
    r=requests.get(base+'?page=project&slug=Magnolia-Built');check('Case study slug match is exact',r.status_code==404)
    doc=soup(requests.get(work));check('Draft is absent from category links and all public markup','Draft fixture' not in str(doc) and 'Internal' not in str(doc))
    check('Every synthetic concept retains its truthful badge',len(doc.select('.project-badge'))==3)
    context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=context.new_page();errors=[];p.on('pageerror',lambda error:errors.append(str(error)))
    p.goto(work,wait_until='networkidle');construction=p.locator('.filters a[data-filter="Construction"]');construction.focus();p.keyboard.press('Enter')
    expect(p.locator('.project-card:not([hidden])')).to_have_count(1)
    check('Keyboard filter preserves focus and reports result count',construction.evaluate('(e)=>e===document.activeElement') and p.locator('[data-portfolio-status]').inner_text()=='Showing 1 of 3 projects.')
    p.go_back();expect(p.locator('.project-card:not([hidden])')).to_have_count(3)
    p.go_forward();expect(p.locator('.project-card:not([hidden])')).to_have_count(1)
    check('Back and forward restore selection and count',construction.get_attribute('aria-current')=='true' and '1 of 3' in p.locator('[data-portfolio-status]').inner_text())
    modified=p.evaluate('''() => {const link=document.querySelector('.filters a[data-filter="Hospitality"]'); return ['ctrlKey','metaKey','shiftKey','altKey'].every(key => {let allowed=false;link.addEventListener('click',event=>{allowed=!event.defaultPrevented;event.preventDefault();},{once:true});const e=new MouseEvent('click',{bubbles:true,cancelable:true,[key]:true});link.dispatchEvent(e);return allowed;});}''')
    check('Modified filter clicks retain native navigation',modified)
    # The observer above prevents only the test event's browser default, after
    # recording the application handler's decision, so synthetic events cannot
    # race the next navigation. Exercise actual native Ctrl-click separately.
    with context.expect_page() as opened:
        p.locator('.filters a[data-filter="Hospitality"]').click(modifiers=['Control'])
    popup=opened.value;popup.wait_for_load_state('domcontentloaded')
    check('Real Ctrl-click opens filtered portfolio in a new tab','cat=Hospitality' in popup.url and popup.locator('.project-card:not([hidden])').count()==1 and 'cat=Construction' in p.url)
    popup.close()
    p.goto(work,wait_until='networkidle')
    for width,label in [(1440,'Desktop'),(320,'Mobile')]:
        p.set_viewport_size({'width':width,'height':1000});p.screenshot(path=str(QA/f'Portfolio-Reviewed-{label}.png'),full_page=True)
        check('Reviewed portfolio fits viewport '+label,p.evaluate('document.documentElement.scrollWidth <= innerWidth+1'))
    p.goto(base+'?page=project&slug=river-and-oak',wait_until='networkidle');p.screenshot(path=str(QA/'Portfolio-Case-Study-Mobile.png'),full_page=True)
    check('Case study fits 320 pixels',p.evaluate('document.documentElement.scrollWidth <= innerWidth+1'))
    # Existing real-media fixture contains distinct approved captures. The same
    # media reference on both sides must not masquerade as a redesign.
    setting=db("SELECT value FROM ci_settings WHERE name='growth_portfolio_1'")[0]['value'];pair=json.loads(setting)
    check('Genuine distinct synthetic pair still has comparison control',p.locator('[data-compare-slider]').count()==1)
    duplicate=dict(pair,before=pair['desktop']);db("UPDATE ci_settings SET value=? WHERE name='growth_portfolio_1'",[json.dumps(duplicate)])
    try:
        p.reload();check('Identical before and after capture does not present a comparison',p.locator('[data-compare-slider]').count()==0 and p.locator('[data-device-image]').count()==1)
    finally:db("UPDATE ci_settings SET value=? WHERE name='growth_portfolio_1'",[setting])
    originals=db('SELECT id,category FROM ci_projects WHERE id<=3 ORDER BY id')
    try:
        for value,id in [('all',1),('1',2),(' Café & Studio ',3)]:db('UPDATE ci_projects SET category=? WHERE id=?',[value,id])
        for category in ['all','1','Café & Studio']:
            target=work+'&'+urlencode({'cat':category});r=requests.get(target);doc=soup(r)
            check('Server exact special category '+category,len(doc.select('.project-card:not([hidden])'))==1 and doc.select_one('.project-card:not([hidden])')['data-category']==category)
            p.goto(work,wait_until='networkidle');p.get_by_role('link',name=category,exact=True).click();expect(p.locator('.project-card:not([hidden])')).to_have_count(1)
            check('Enhanced exact special category '+category,p.locator('.project-card:not([hidden])').get_attribute('data-category')==category)
        p.locator('.filters a[data-filter=""]').click();expect(p.locator('.project-card:not([hidden])')).to_have_count(3)
        check('All work remains distinct from a category named all',p.locator('.filters a[data-filter=""]').get_attribute('aria-current')=='true')
        p.goto(work+'&cat=%20all%20');p.locator('.filters a[data-filter="1"]').click();p.go_back();expect(p.locator('.project-card:not([hidden])')).to_have_count(1)
        check('History normalizes category whitespace like the server',p.locator('.project-card:not([hidden])').get_attribute('data-category')=='all')
    finally:
        for row in originals:db('UPDATE ci_projects SET category=? WHERE id=?',[row['category'],row['id']])
    nojs=browser.new_context(java_script_enabled=False,viewport={'width':320,'height':900});np=nojs.new_page();np.goto(work);np.get_by_role('link',name='Construction',exact=True).click()
    expect(np.locator('.project-card:not([hidden])')).to_have_count(1)
    check('No-JavaScript filter navigation remains usable at 320px',np.locator('.project-card:not([hidden])').is_visible() and np.evaluate('document.documentElement.scrollWidth <= innerWidth+1'))
    np.screenshot(path=str(QA/'Portfolio-No-JavaScript-Mobile.png'),full_page=True)
    np.locator('.project-card:not([hidden]) .project-art').click();check('No-JavaScript filtered card opens its case study','Magnolia Built' in np.title())
    check('Portfolio interactions have no uncaught browser errors',not errors)
    nojs.close();context.close()
