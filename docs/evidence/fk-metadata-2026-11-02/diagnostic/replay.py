#!/usr/bin/env python3
"""One unchanged saved-mapping browser block, with external diagnostic observers."""
from pathlib import Path
import ast, datetime, hashlib, importlib.metadata, json, os, re, socket, subprocess, sys, time, traceback
from urllib.parse import parse_qs, urlparse
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import Browser, sync_playwright

RUNTIME=Path(__file__).resolve().parents[1]
SOURCE=RUNTIME/'source'
WEB=RUNTIME/'web'
EVIDENCE=RUNTIME/'evidence'
BASE='http://127.0.0.1:8092'
PHP='/usr/bin/php8.3'
sys.path.insert(0,str(SOURCE/'tests'))
from owner_browser_forms import assert_no_owner_reprompt

def sha_bytes(data): return hashlib.sha256(data).hexdigest()
def safe_url(value):
    u=urlparse(value);q=parse_qs(u.query);r={'path':u.path}
    for k in ('view','audience'):
        if k in q and len(q[k])==1 and re.fullmatch('[a-z-]{1,80}',q[k][0]):r[k]=q[k][0]
    for k in ('record','agreement'):
        if k in q:r[k+'_present']=True
    if u.hostname not in (None,'127.0.0.1'):r['external_host']=u.hostname
    return r

if os.geteuid()==0:raise SystemExit('Run browser and HTTP as the isolated unprivileged worker')
marker=EVIDENCE/'replay-started.json'
with marker.open('x') as f:json.dump({'started_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'scope':'one corrected diagnostic comparison'},f)
for port in (8092,):
    with socket.socket() as s:
        if s.connect_ex(('127.0.0.1',port))==0:raise SystemExit('Owned HTTP port is already occupied')
state=json.loads((RUNTIME/'private/fixture-state.json').read_text())
secret=json.loads((RUNTIME/'private/fixture-input.json').read_text())
text=(SOURCE/'tests/provider_plan_catalog_cases.py').read_text()
tree=ast.parse(text)
function=next(n for n in tree.body if isinstance(n,ast.FunctionDef) and n.name=='prepare')
block=[n for n in function.body if 94<=n.lineno<=103]
if not block or block[0].lineno!=94 or block[-1].lineno!=103:raise SystemExit('Exact saved-mapping source boundary changed')
selected='\n'.join(text.splitlines()[93:103])+'\n'
preparation=json.loads((RUNTIME/'control/preparation.json').read_text())
binding={'browser_fixture_commit':'84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd','application_base_commit':'84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd','application_tree':preparation['tree'],'application_commit':None,'source_state':'frozen Git tree before commit','source_file':'tests/provider_plan_catalog_cases.py','source_sha256':sha_bytes(text.encode()),'source_lines':[94,103],'selected_source_sha256':sha_bytes(selected.encode()),'selected_ast_sha256':sha_bytes(ast.dump(ast.Module(body=block,type_ignores=[]),include_attributes=False).encode()),'assertions_and_timeouts_modified':False,'full_suite_execution':False,'fixture_population_equivalent_to_hosted':False,'python':sys.version.split()[0],'playwright':importlib.metadata.version('playwright')}
(EVIDENCE/'source-binding.json').write_text(json.dumps(binding,indent=2)+'\n')
(EVIDENCE/'executed-source-block.py').write_text(selected)

checks=[];events=[];start=time.monotonic_ns();pages={};contexts={};external=[]
def event(kind,**data):events.append({'monotonic_ms':(time.monotonic_ns()-start)/1e6,'event':kind,**data})
def check(name,condition):
    checks.append({'name':name,'passed':bool(condition)});event('check',name=name,passed=bool(condition))
    if not condition:raise AssertionError(name)
def watch_page(page,cid):
    pid=len(pages)+1;pages[id(page)]=pid;event('page',page_id=pid,context_id=cid)
    def request_seen(req):
        u=urlparse(req.url)
        if u.scheme in ('http','https') and (u.hostname!='127.0.0.1' or u.port!=8092):
            external.append({'event':'external_request_attempt','url':safe_url(req.url)});raise RuntimeError('Unexpected external browser request')
        event('request',page_id=pid,method=req.method,url=safe_url(req.url),resource_type=req.resource_type,navigation=req.is_navigation_request())
    page.on('request',request_seen)
    page.on('response',lambda response:event('response',page_id=pid,url=safe_url(response.url),status=response.status,resource_type=response.request.resource_type,redirect=safe_url(response.headers['location']) if 'location' in response.headers else None))
    page.on('requestfailed',lambda req:event('requestfailed',page_id=pid,url=safe_url(req.url),failure=req.failure,resource_type=req.resource_type))
    page.on('framenavigated',lambda frame:event('navigation',page_id=pid,url=safe_url(frame.url)) if frame==page.main_frame else None)
    page.on('domcontentloaded',lambda:event('domcontentloaded',page_id=pid))
    page.on('load',lambda:event('load',page_id=pid))
    page.on('console',lambda msg:event('console',page_id=pid,console_type=msg.type,text_sha256=sha_bytes(msg.text.encode())))
    page.on('pageerror',lambda error:event('pageerror',page_id=pid,text_sha256=sha_bytes(str(error).encode())))
orig_new_context=Browser.new_context
def observed_context(self,*args,**kwargs):
    context=orig_new_context(self,*args,**kwargs);cid=len(contexts)+1;contexts[id(context)]=cid
    context.on('page',lambda page:watch_page(page,cid));return context
Browser.new_context=observed_context
driver=(SOURCE/'tests/browser_acceptance.py').read_text()
functions=[n for n in ast.parse(driver).body if isinstance(n,ast.FunctionDef) and n.name in ('soup','form_data','form_for')]
ns={'BeautifulSoup':BeautifulSoup}
exec(compile(ast.Module(body=functions,type_ignores=[]),str(SOURCE/'tests/browser_acceptance.py'),'exec'),ns)
owner=requests.Session();owner.trust_env=False
failure=None;trace=None;browser=None;http=None
serverlog=(EVIDENCE/'http-server.log').open('x')
try:
    http=subprocess.Popen([PHP,'-d','session.save_path='+str(RUNTIME/'sessions'),'-d','auto_prepend_file='+str(RUNTIME/'control/observe.php'),'-S','127.0.0.1:8092','-t',str(WEB)],stdout=serverlog,stderr=subprocess.STDOUT)
    (EVIDENCE/'owned-http.json').write_text(json.dumps({'pid':http.pid,'port':8092,'started_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat()})+'\n')
    login=BASE+'/existing/admin/index.php?view=login'
    for _ in range(50):
        if http.poll() is not None:raise RuntimeError('Owned HTTP server exited before browser baseline')
        try:
            response=owner.get(login,timeout=1);break
        except requests.ConnectionError:time.sleep(.1)
    else:raise RuntimeError('Owned HTTP server did not start')
    fields=ns['form_data'](ns['form_for'](response,'login'));fields.update({'email':'catalog-owner@example.invalid','password':secret['owner_password']})
    response=owner.post(login,data=fields,timeout=25)
    if response.status_code!=200 or 'Sales pipeline' not in response.text:raise RuntimeError('Actual synthetic owner login did not succeed')
    del fields,secret
    event('real_owner_login_completed',status=response.status_code)
    with sync_playwright() as p:
        browser=p.chromium.launch(headless=True,args=['--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1, EXCLUDE localhost'])
        binding['chromium']=browser.version;(EVIDENCE/'source-binding.json').write_text(json.dumps(binding,indent=2)+'\n')
        scope={'browser':browser,'owner':owner,'base':BASE,'qa':EVIDENCE,'check':check,'assert_no_owner_reprompt':assert_no_owner_reprompt,'staff_url':state['staff_url']}
        event('exact_browser_block_start')
        exec(compile(ast.Module(body=block,type_ignores=[]),str(SOURCE/'tests/provider_plan_catalog_cases.py'),'exec'),scope)
        event('exact_browser_block_completed')
        browser.close();browser=None
except BaseException as exc:
    failure=repr(exc);trace=traceback.format_exc();event('exception',class_name=type(exc).__name__)
finally:
    if browser is not None:
        try:browser.close()
        except Exception:pass
    if http is not None:
        http.terminate();http.wait(timeout=10)
    serverlog.close();Browser.new_context=orig_new_context;owner.close()
    (EVIDENCE/'browser-events.jsonl').write_text(''.join(json.dumps(e,separators=(',',':'))+'\n' for e in events))
    if trace:
        trace=re.sub(r'([?&](?:record|agreement)=)[^&\s\'\"]+',r'\1[synthetic-record]',trace)
        (EVIDENCE/'exception-traceback.txt').write_text(trace)
    result={'scope':'One corrected diagnostic comparison of the unchanged saved-mapping block, not full acceptance','passed':failure is None,'failure':re.sub(r'[a-f0-9]{32,}', '[redacted-identifier]',failure) if failure else None,'checks':checks,'external_request_attempts':external,'elapsed_ms':(time.monotonic_ns()-start)/1e6,'source_tree':binding['application_tree'],'source_binding':'source-binding.json'}
    (EVIDENCE/'baseline-result.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result,indent=2))
if failure:raise SystemExit(1)
