"""Exercise the actual Apache sign-in assertion and its exact predecessor inverse."""
from pathlib import Path
from types import SimpleNamespace
import ast, copy, hashlib, importlib.util, json, tempfile
from bs4 import BeautifulSoup
ROOT=Path(__file__).resolve().parents[1]
checks=[]
def check(name,ok):
    checks.append({'name':name,'passed':bool(ok)})
    if not ok:raise AssertionError(name)
def digest(value):return hashlib.sha256(value).hexdigest()
try:
    source=(ROOT/'tests/apache_acceptance.py').read_text()
    tree=ast.parse(source)
    assignments=[n for n in ast.walk(tree) if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='recovery_form' for t in n.targets)]
    calls=[n for n in ast.walk(tree) if isinstance(n,ast.Expr) and isinstance(n.value,ast.Call) and isinstance(n.value.func,ast.Name) and n.value.func.id=='check' and 'owner recovery sign-in remains reachable in maintenance' in ast.unparse(n)]
    check('The corrected Apache assertion has one exact execution site',len(assignments)==len(calls)==1)
    code=compile(ast.Module(body=[assignments[0],calls[0]],type_ignores=[]),'actual-apache-signin-assertion','exec')
    valid='<form method="post"><input name="email" type="email"><input name="owner_password" type="password"><input name="csrf" type="hidden"></form>'
    examples=[('valid new label',200,valid,True),('HTTP refusal',403,valid,False),('no email',200,valid.replace('<input name="email" type="email">',''),False),('no password',200,valid.replace('<input name="owner_password" type="password">',''),False),('no CSRF',200,valid.replace('<input name="csrf" type="hidden">',''),False),('wrong email type',200,valid.replace('type="email"','type="text"'),False),('wrong password type',200,valid.replace('type="password"','type="text"'),False),('GET form',200,valid.replace('method="post"','method="get"'),False),('absent form',200,'',False)]
    for name,status,html,expected in examples:
        outcome=[]
        exec(code,{'BeautifulSoup':BeautifulSoup,'r':SimpleNamespace(status_code=status,text=html),'label':'synthetic','check':lambda n,v:outcome.append(bool(v))})
        check('Actual assertion: '+name,outcome==[expected])
    contract=json.loads((ROOT/'tools/acceptance-contract.json').read_text())
    spec=importlib.util.spec_from_file_location('reviewed_cloud_wiring',ROOT/'tests/cloud_site_requests_wiring.py')
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    original=module.predecessor_bytes(ROOT,'tests/apache_acceptance.py',contract)
    check('Inverse restores the exact previous complete Apache source',digest(original)==contract['owner_update_signin_test']['before_sha256'])
    check('Inverse restores exactly one old assertion',original.count(contract['owner_update_signin_test']['before'].encode())==1)
    with tempfile.TemporaryDirectory() as temp:
        root=Path(temp);(root/'tests').mkdir();path=root/'tests/apache_acceptance.py'
        for name,text,alter in [('extra change',source+'\n# unexpected\n',None),('missing correction',source.replace(contract['owner_update_signin_test']['after'],''),None),('duplicated correction',source+contract['owner_update_signin_test']['after'],None),('wrong original hash',source,'before_sha256'),('wrong output hash',source,'after_sha256')]:
            path.write_text(text);c=copy.deepcopy(contract)
            if alter:c['owner_update_signin_test'][alter]='0'*64
            refused=False
            try:module.predecessor_bytes(root,'tests/apache_acceptance.py',c)
            except AssertionError:refused=True
            check('Exact inverse rejects '+name,refused)
    check('Wiring source remains pinned by the existing contract',digest((ROOT/'tests/cloud_site_requests_wiring.py').read_bytes())==contract['cloud_site_requests']['source_sha256']['tests/cloud_site_requests_wiring.py'])
except Exception as exc:
    failure=type(exc).__name__+': '+str(exc)
report={'passed':not globals().get('failure'),'scope':'Actual assertion over synthetic HTML and exact source-inverse guards; not installed Apache acceptance','checks':checks,'failure':globals().get('failure')}
print(json.dumps(report,indent=2));raise SystemExit(0 if report['passed'] else 1)
