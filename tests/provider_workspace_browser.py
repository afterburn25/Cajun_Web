"""Loopback-only browser checks of actual PHP templates with synthetic model reads.
No installed database, actual authentication, provider access, or form submission.
"""
from pathlib import Path
import json, os, shutil, socket, subprocess, time, urllib.request
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
OUT=Path(os.environ.get('CWP_UI_REPORT_DIR',str(ROOT/'qa'/'provider-workspace')))
OUT.mkdir(parents=True,exist_ok=True)
checks=[]
def check(name: str, condition: bool) -> None:
    checks.append({'name':name,'passed':bool(condition)})
    if not condition: raise AssertionError(name)
with socket.socket() as sock:
    sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
base=f'http://127.0.0.1:{port}'
log=(OUT/'php-server.log').open('w')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(ROOT)],env={**os.environ,'CWP_UI_FIXTURE':'1'},stdout=log,stderr=log)
try:
    for _ in range(50):
        try:
            urllib.request.urlopen(base+'/tests/provider_workspace_fixture.php',timeout=1).read();break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        chromium=os.environ.get('CWP_BROWSER') or shutil.which('chromium')
        browser=p.chromium.launch(headless=True,executable_path=chromium,args=['--no-sandbox'])
        ctx=browser.new_context(viewport={'width':1440,'height':1000})
        blocked=[]
        def route(r):
            url=r.request.url
            if url.startswith(base+'/fixture-disabled/'):
                blocked.append('unmodeled local activity endpoint');r.fulfill(status=200,content_type='application/json',body='{"available":false}');return
            if url.startswith(base+'/'):r.continue_()
            else:blocked.append('external resource');r.abort()
        ctx.route('**/*',route)
        page=ctx.new_page();errors=[];page.on('pageerror',lambda error: errors.append(str(error)))
        def visit(view='provider-settings',section='overview',scenario='connected'):
            response=page.goto(base+f'/tests/provider_workspace_fixture.php?view={view}&section={section}&scenario={scenario}',wait_until='networkidle')
            check(f'{view}/{section}/{scenario} renders HTTP 200',response.status==200)
            check(f'{view}/{section}/{scenario} has no PHP diagnostics',not any(x in page.locator('body').inner_text() for x in ['Fatal error:','Warning:','Parse error:','Uncaught ']))
            check(f'{view}/{section}/{scenario} has no repeated-password input',page.locator('input[name="current_password"], input[name="owner_password"]').count()==0)
        for scenario,expect in [('unconfigured','Connect your 20i account'),('paused','Enable your saved connection'),('stale','Verify your saved connection'),('connected','Load the plans you created in 20i')]:
            visit(scenario=scenario)
            check('Accurate next-step label: '+scenario,page.get_by_role('heading',name=expect,exact=True).count()==1)
            check('Overview does not contain hidden provider mutation forms: '+scenario,page.locator('main form').count()==0)
        check('Workspace nav exposes seven distinct destinations',page.locator('.provider-console-nav a').count()==7)
        check('Public and admin catalog destinations are separate',page.locator('.provider-console-shortcuts a').count()==4)
        check('Desktop overview does not overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        page.screenshot(path=str(OUT/'20i-workspace-desktop.png'),full_page=True)
        page.get_by_role('link',name='Connection',exact=True).click();page.wait_for_load_state('networkidle')
        check('Clicking Connection loads only connection workspace','section=connection' in page.url and page.locator('input[name="general_key"]').count()==1)
        check('Connection does not render cloud/domain inventories',page.get_by_role('heading',name='Cloud servers',exact=True).count()==0 and page.get_by_role('heading',name='Hosting package inventory',exact=True).count()==0)
        check('Connection still exposes real API-key credential',page.locator('input[name="general_key"][type="password"]').count()==1)
        check('Connection forms retain CSRF',page.locator('main form').count()==page.locator('main form input[name="csrf"]').count())
        page.screenshot(path=str(OUT/'20i-connection-desktop.png'),full_page=True)
        visit(section='../advanced')
        check('Unknown section falls back to overview',page.get_by_role('heading',name='Connect once',exact=True).count()==1)
        visit(view='catalog')
        check('Local products precede the importer and create form',page.locator('#catalog-products').evaluate('(el)=>el.getBoundingClientRect().top')<page.locator('#hosting-import').evaluate('(el)=>el.getBoundingClientRect().top')<page.get_by_role('heading',name='New product',exact=True).evaluate('(el)=>el.getBoundingClientRect().top'))
        check('Importer defaults to direct package-type catalog',page.locator('nav[aria-label="Hosting import source"] a[aria-current="page"]').inner_text()=='20i hosting catalog')
        check('Uninstalled provider plan appears as import choice',page.get_by_role('heading',name='Business hosting (synthetic)',exact=True).count()==1)
        check('Previously imported product links to existing draft',page.get_by_role('link',name='Review draft product',exact=True).count()==1)
        check('Batch and individual import forms remain explicit',page.get_by_role('button',name='Import this batch',exact=True).count()==1 and page.get_by_role('button',name='Create draft',exact=True).count()==1)
        check('Catalog forms retain CSRF',page.locator('main form').count()==page.locator('main form input[name="csrf"]').count())
        page.screenshot(path=str(OUT/'catalog-desktop.png'),full_page=True)
        visit(view='catalog',scenario='empty')
        check('Empty local products explain next action','No local products yet.' in page.locator('#catalog-products').inner_text())
        check('Empty provider response is not a fabricated successful import','No named plans are available in this saved response.' in page.locator('#hosting-import').inner_text())
        for view in ['provider-settings','catalog']:
            page.set_viewport_size({'width':390,'height':844});visit(view=view)
            check('Mobile '+view+' has no horizontal overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            check('Mobile '+view+' has a navigation toggle',page.get_by_role('button',name='Toggle administration navigation').is_visible())
            page.screenshot(path=str(OUT/('20i-workspace-mobile.png' if view=='provider-settings' else 'catalog-mobile.png')),full_page=True)
        check('No uncaught JavaScript errors in modeled page interactions',not errors)
        browser.close()
    result={'passed':True,'scope':__doc__,'checks':checks,'unmodeled_requests_intercepted':blocked,'browser_errors':errors}
except Exception as error:
    result={'passed':False,'scope':__doc__,'checks':checks,'error':f'{type(error).__name__}: {error}'}
finally:
    server.terminate();server.wait(timeout=5);log.close()
(OUT/'browser-results.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({'passed':result['passed'],'checks':len(checks),'error':result.get('error')}))
raise SystemExit(0 if result['passed'] else 1)
