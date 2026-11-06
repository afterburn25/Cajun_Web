"""Loopback-only browser checks of actual PHP templates with synthetic model reads.
No installed database, actual authentication, provider access, or form submission.
"""
from pathlib import Path
import json, os, shutil, socket, subprocess, tempfile, time, urllib.request
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
media_storage=Path(tempfile.mkdtemp(prefix='cwp-provider-workspace-browser-'))
media_directory=media_storage/'media';media_directory.mkdir(mode=0o700)
media_id='d'*32;media_file=media_directory/(media_id+'.php')
media_file.write_bytes(b'<?php http_response_code(404); exit; ?>\n'+(ROOT/'site'/'images'/'icons'/'favicon.png').read_bytes())
media_file.chmod(0o600)
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(ROOT)],env={**os.environ,'CWP_UI_FIXTURE':'1','CWP_UI_MEDIA_STORAGE':str(media_storage)},stdout=log,stderr=log)
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
        page=ctx.new_page();errors=[];media_responses=[];page.on('pageerror',lambda error: errors.append(str(error)))
        page.on('response',lambda response: media_responses.append((response.url,response.status)) if 'fixture_media=1' in response.url else None)
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
        check('Catalog and licensing uses one selected navigation group',page.locator('details[data-nav-group="catalog"][data-active="true"] summary').filter(has_text='Catalog & Licensing').count()==1)
        check('Catalog exposes the three primary owner tasks',page.get_by_role('link',name='Import 20i products').count()==1 and page.get_by_role('link',name='Add software').count()==1 and page.get_by_role('link',name='Issue customer license').count()==1)
        check('Add software carries the bounded software-family suggestion','new_family=software' in page.get_by_role('link',name='Add software').get_attribute('href'))
        visit(view='catalog',scenario='catalog-limited')
        check('Catalog-only staff do not receive a forbidden license shortcut',page.get_by_role('link',name='Issue customer license').count()==0 and page.get_by_role('link',name='Import 20i products').count()==1 and page.get_by_role('link',name='Add software').count()==1)
        visit(view='catalog')
        check('Local products precede the importer and create form',page.locator('#catalog-products').evaluate('(el)=>el.getBoundingClientRect().top')<page.locator('#hosting-import').evaluate('(el)=>el.getBoundingClientRect().top')<page.get_by_role('heading',name='New product',exact=True).evaluate('(el)=>el.getBoundingClientRect().top'))
        check('Importer defaults to direct package-type catalog',page.locator('nav[aria-label="Hosting import source"] a[aria-current="page"]').inner_text()=='20i hosting catalog')
        check('Uninstalled provider plan appears as import choice',page.get_by_role('heading',name='Business hosting (synthetic)',exact=True).count()==1)
        check('Previously imported product links to existing draft',page.get_by_role('link',name='Review draft product',exact=True).count()==1)
        check('Missing provider artwork is explicit and locally actionable','No provider image available' in page.locator('#catalog-products').inner_text() and page.locator('#catalog-products a[href*="id=12"][href$="#product-image"]').count()==1)
        check('Unpublished import has an exact publication-review link',page.get_by_role('link',name='Review for publication',exact=True).count()==1)
        check('Batch and individual import forms remain explicit',page.get_by_role('button',name='Import this batch',exact=True).count()==1 and page.get_by_role('button',name='Create draft',exact=True).count()==1)
        check('Catalog mutation forms retain CSRF',page.locator('main form[method="post"]').count()==page.locator('main form[method="post"] input[name="csrf"]').count())
        page.screenshot(path=str(OUT/'catalog-desktop.png'),full_page=True)
        visit(view='catalog',scenario='ready-media-file')
        thumbnail=page.locator('#catalog-products img.catalog-product-thumb')
        check('A guarded managed file is shown ready with its thumbnail','Image ready' in page.locator('#catalog-products').inner_text() and 'Image missing' not in page.locator('#catalog-products').inner_text() and thumbnail.count()==1)
        check('The guarded thumbnail is served by the real managed-media boundary',any('fixture_media=1' in url and status==200 for url,status in media_responses) and thumbnail.evaluate('(img)=>img.complete&&img.naturalWidth>0'))
        visit(view='catalog',scenario='missing-media-file')
        check('Physical managed artwork is required before the catalog calls it ready','Image missing' in page.locator('#catalog-products').inner_text() and 'Image ready' not in page.locator('#catalog-products').inner_text() and page.locator('#catalog-products img.catalog-product-thumb').count()==0)
        visit(view='catalog',scenario='empty')
        check('Empty local products explain next action','No local products yet.' in page.locator('#catalog-products').inner_text())
        check('Empty provider response is not a fabricated successful import','No named plans are available in this saved response.' in page.locator('#hosting-import').inner_text())
        for view in ['provider-settings','catalog']:
            page.set_viewport_size({'width':390,'height':844});visit(view=view)
            check('Mobile '+view+' has no horizontal overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            if view=='catalog':
                check('Mobile catalog row and offer action fit the product card',page.get_by_role('link',name='Review for publication',exact=True).is_visible() and page.locator('#catalog-products tbody tr').evaluate('(r)=>{const x=r.getBoundingClientRect(),p=r.closest("section").getBoundingClientRect();return x.left>=p.left-1&&x.right<=p.right+1;}'))
            check('Mobile '+view+' has a navigation toggle',page.get_by_role('button',name='Toggle administration navigation').is_visible())
            page.screenshot(path=str(OUT/('20i-workspace-mobile.png' if view=='provider-settings' else 'catalog-mobile.png')),full_page=True)
        check('No uncaught JavaScript errors in modeled page interactions',not errors)
        browser.close()
    result={'passed':True,'scope':__doc__,'checks':checks,'unmodeled_requests_intercepted':blocked,'browser_errors':errors}
except Exception as error:
    result={'passed':False,'scope':__doc__,'checks':checks,'error':f'{type(error).__name__}: {error}'}
finally:
    server.terminate();server.wait(timeout=5);log.close();shutil.rmtree(media_storage,ignore_errors=True)
(OUT/'browser-results.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({'passed':result['passed'],'checks':len(checks),'error':result.get('error')}))
raise SystemExit(0 if result['passed'] else 1)
