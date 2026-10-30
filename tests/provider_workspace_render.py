"""Offline Chromium rendering of actual PHP templates and local CSS/admin menu script.
Synthetic rows/readiness only. No HTTP server, browser navigation, SQL or provider IO.
"""
from pathlib import Path
import base64,json,os,re,shutil,subprocess
from urllib.parse import urlencode, urlparse, parse_qsl
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
OUT=Path(os.environ.get('CWP_UI_REPORT_DIR',str(ROOT/'qa'/'workspace-render')));OUT.mkdir(parents=True,exist_ok=True)
checks=[]
def check(name,pass_):
    checks.append({'name':name,'passed':bool(pass_)})
    if not pass_:raise AssertionError(name)
def markup(query):
    proc=subprocess.run(['php','-r','parse_str($argv[1],$_GET);require $argv[2];',urlencode(query),str(ROOT/'tests/provider_workspace_fixture.php')],capture_output=True,text=True,env={**os.environ,'CWP_UI_FIXTURE':'1'},timeout=10)
    check('PHP template renders '+str(query),proc.returncode==0 and not proc.stderr)
    check('No PHP diagnostic output '+str(query),not any(v in proc.stdout for v in ['Fatal error:','Warning:','Parse error:']))
    soup=BeautifulSoup(proc.stdout,'html.parser')
    for script in soup.select('script'):script.decompose()
    for link in soup.select('link'):
        if link.get('rel')==['stylesheet']:
            path=(ROOT/link['href'].lstrip('/')).resolve();assert path.is_relative_to(ROOT/'site/assets/css')
            style=soup.new_tag('style');style.string=path.read_text();link.replace_with(style)
        else:link.decompose()
    for image in soup.select('img[src]'):
        path=(ROOT/image['src'].lstrip('/')).resolve();assert path.is_relative_to(ROOT/'site/images')
        image['src']='data:image/png;base64,'+base64.b64encode(path.read_bytes()).decode()
    return str(soup)
try:
    with sync_playwright() as p:
        b=p.chromium.launch(headless=True,executable_path=shutil.which('chromium'),args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':1000})
        ctx.route('**/*',lambda r:r.abort());page=None;errors=[]
        def render(view='provider-settings',section='overview',scenario='connected',mobile=False):
            global page
            if page:page.close()
            page=ctx.new_page();page.set_viewport_size({'width':390,'height':844} if mobile else {'width':1440,'height':1000});page.on('pageerror',lambda e: errors.append(str(e)))
            page.set_content(markup({'view':view,'section':section,'scenario':scenario}),wait_until='domcontentloaded')
            page.add_script_tag(content=(ROOT/'site/assets/js/admin.js').read_text())
            check('No repeated-password controls '+view+'/'+section+'/'+scenario+('/mobile' if mobile else ''),page.locator('input[name="current_password"],input[name="owner_password"]').count()==0)
        for scenario,title in [('unconfigured','Connect your 20i account'),('paused','Enable your saved connection'),('stale','Verify your saved connection'),('connected','Load the plans you created in 20i')]:
            render(scenario=scenario);check('Next action is truthful for '+scenario,page.get_by_role('heading',name=title,exact=True).count()==1);check('Overview has no concealed provider forms '+scenario,page.locator('main form').count()==0)
        check('Seven allowlisted task workspaces shown',page.locator('.provider-console-nav a').count()==7)
        href=page.get_by_role('link',name='Connection',exact=True).get_attribute('href');check('Connection link preserves exact workspace',dict(parse_qsl(urlparse(href).query)).get('section')=='connection')
        check('Desktop overview no horizontal overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        page.screenshot(path=str(OUT/'20i-workspace-desktop.png'),full_page=True)
        render(section='connection')
        check('Actual connection credential still rendered',page.locator('input[name="general_key"][type="password"]').count()==1)
        check('Connection forms retain CSRF',page.locator('main form[method="post"]').count()==page.locator('main form[method="post"] input[name="csrf"]').count())
        check('Connection does not render unrelated hosting/cloud inventories',page.get_by_role('heading',name='Hosting package inventory',exact=True).count()==0 and page.get_by_role('heading',name='Cloud servers',exact=True).count()==0)
        page.screenshot(path=str(OUT/'20i-connection-desktop.png'),full_page=True)
        render(section='../../config')
        check('Traversal-like section safely renders overview',page.get_by_role('heading',name='Connect once',exact=True).count()==1)
        render(view='catalog')
        check('Saved local products precede importer and new form',page.locator('#catalog-products').evaluate('(e)=>e.getBoundingClientRect().top')<page.locator('#hosting-import').evaluate('(e)=>e.getBoundingClientRect().top')<page.get_by_role('heading',name='New product',exact=True).evaluate('(e)=>e.getBoundingClientRect().top'))
        check('Product search, type and status filters exist',page.locator('input[name="product_search"],select[name="product_family"],select[name="product_status"]').count()==3)
        check('Direct catalog selected by default',page.locator('nav[aria-label="Hosting import source"] a[aria-current="page"]').inner_text()=='20i hosting catalog')
        check('Type without hosting instance is visible',page.get_by_role('heading',name='Business hosting (synthetic)',exact=True).count()==1)
        check('Already imported type links to existing draft',page.get_by_role('link',name='Review draft product',exact=True).count()==1)
        check('Import batch and single product confirmations exist',page.get_by_role('button',name='Import this batch',exact=True).count()==1 and page.get_by_role('button',name='Create draft',exact=True).count()==1)
        check('Mutation forms retain CSRF; search remains read-only',page.locator('main form[method="post"]').count()==page.locator('main form[method="post"] input[name="csrf"]').count())
        page.screenshot(path=str(OUT/'catalog-desktop.png'),full_page=True)
        render(view='catalog',scenario='empty')
        check('Empty local table has explicit next action','No local products yet.' in page.locator('#catalog-products').inner_text())
        check('Empty provider catalog is not described as success','No named plans are available in this saved response.' in page.locator('#hosting-import').inner_text())
        for view in ['provider-settings','catalog']:
            render(view=view,mobile=True)
            check('Mobile '+view+' no page overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            toggle=page.get_by_role('button',name='Toggle administration navigation')
            check('Mobile '+view+' collapsed accessible menu',toggle.get_attribute('aria-expanded')=='false')
            toggle.click();check('Mobile '+view+' menu opens',toggle.get_attribute('aria-expanded')=='true')
            page.keyboard.press('Escape');check('Mobile '+view+' Escape closes menu',toggle.get_attribute('aria-expanded')=='false')
            page.screenshot(path=str(OUT/('20i-workspace-mobile.png' if view=='provider-settings' else 'catalog-mobile.png')),full_page=True)
        check('Rendered admin menu has no uncaught script errors',not errors);b.close()
    result={'passed':True,'scope':__doc__,'checks':checks,'errors':errors}
except Exception as e:result={'passed':False,'scope':__doc__,'checks':checks,'error':str(e)}
(OUT/'render-results.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps({'passed':result['passed'],'checks':len(checks),'error':result.get('error')}));raise SystemExit(0 if result['passed'] else 1)
