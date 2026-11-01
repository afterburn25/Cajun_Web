"""Actual updater template plus strict HTTP-orchestration doubles, not installed acceptance."""
from pathlib import Path
from types import SimpleNamespace
from contextlib import contextmanager
from unittest.mock import patch
import copy
import json
import subprocess
from bs4 import BeautifulSoup
import owner_browser_forms as target

ROOT = Path(__file__).resolve().parents[1]
checks = []
def check(name, passed):
    checks.append({'name': name, 'passed': bool(passed)})
    if not passed:
        raise AssertionError(name)
def soup(response):
    return BeautifulSoup(response.text, 'html.parser')
def response(text, status=200):
    return SimpleNamespace(text=text, status_code=status, url='http://127.0.0.1:8091/upgrade/stage/owner-update.php')

try:
    for authenticated in (False, True):
        # Execute the real PHP template, replacing only its app/bootstrap dependencies.
        code = r'''define('CWP_VERSION','synthetic');function esc($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}function csrf_field(){return '<input type="hidden" name="csrf" value="synthetic-form-token">';}function admin_url(){return '/admin/index.php';}$error='';$result=null;$actor=$argv[2]==='1'?['email'=>'owner@example.invalid']:null;$source=file_get_contents($argv[1]);$offset=strpos($source,'?><!doctype html>');if($offset===false)exit(2);eval(substr($source,$offset));'''
        run = subprocess.run(['php', '-r', code, str(ROOT/'tools/update/owner-update.php'), '1' if authenticated else '0'], capture_output=True, text=True, check=True)
        check('Actual updater template has no PHP diagnostics '+str(authenticated), not run.stderr)
        html = run.stdout
        check('Actual updater controls match current authentication state '+str(authenticated), target.owner_update_form(response(html), soup, authenticated=authenticated))
        check('Authentication states cannot be interchanged '+str(authenticated), not target.owner_update_form(response(html), soup, authenticated=not authenticated))
        mutations = [
            ('CSRF absent', lambda d: d.select_one('[name=csrf]').decompose()),
            ('CSRF empty', lambda d: d.select_one('[name=csrf]').attrs.update(value='')),
            ('backup absent', lambda d: d.select_one('[name=backup_confirm]').decompose()),
            ('backup not required', lambda d: d.select_one('[name=backup_confirm]').attrs.pop('required')),
            ('backup wrong value', lambda d: d.select_one('[name=backup_confirm]').attrs.update(value='0')),
            ('GET form', lambda d: d.select_one('form').attrs.update(method='get')),
            ('offsite action', lambda d: d.select_one('form').attrs.update(action='https://example.invalid/')),
            ('apply absent', lambda d: d.select_one('button[value=apply]').decompose()),
        ]
        if not authenticated:
            mutations.extend([
                ('initial password absent', lambda d: d.select_one('[name=owner_password]').decompose()),
                ('initial password optional', lambda d: d.select_one('[name=owner_password]').attrs.pop('required')),
                ('initial email absent', lambda d: d.select_one('[name=email]').decompose()),
            ])
        for label, mutation in mutations:
            doc = BeautifulSoup(html, 'html.parser'); mutation(doc)
            check('Reject '+label+' '+str(authenticated), not target.owner_update_form(response(str(doc)), soup, authenticated=authenticated))
        check('An error page cannot impersonate a valid updater '+str(authenticated), not target.owner_update_form(response(html,500), soup, authenticated=authenticated))

    url='http://127.0.0.1:8091/existing/admin/index.php?view=staff'
    body='<form method="post"><input type="hidden" name="action" value="staff_save"><input type="hidden" name="csrf" value="captured-token"><input name="id" value="0"></form>'
    def form_for(r, action):
        form=soup(r).select_one('form')
        if form.select_one('[name=action]').get('value') != action:
            raise AssertionError('Missing exact form')
        return form
    env={'BASE':'http://127.0.0.1:8091','WEB':ROOT/'test-runtime/web','php':'php','check':check,'form_for':form_for,
         'form_data':lambda f:{x['name']:x.get('value','') for x in f.select('input[name]')}}
    for scenario in ('403','permission-notice','success','login-redirect','server-error','mutated-state','HTTP-error'):
        state={'revoked':False,'restored':False,'posts':0}
        @contextmanager
        def authority(root, php):
            state['revoked']=True
            try:
                yield
                if scenario=='mutated-state':raise AssertionError('Synthetic changed private state')
            finally:
                state['revoked']=False;state['restored']=True
        class Session:
            def get(self, dest, **kwargs):
                check(scenario+' captures an authorized owner form before revocation',not state['revoked'] and dest==url)
                return SimpleNamespace(status_code=200,url=url,text=body)
            def post(self, dest, **kwargs):
                state['posts']+=1
                check(scenario+' posts original CSRF under revoked permission',state['revoked'] and kwargs['data']['csrf']=='captured-token' and kwargs['data']['action']=='staff_save' and kwargs['data']['name']=='Synthetic member' and kwargs['allow_redirects'] is False)
                if scenario=='HTTP-error':raise OSError('Synthetic HTTP failure')
                status={'403':403,'login-redirect':303,'server-error':500}.get(scenario,200)
                return SimpleNamespace(status_code=status,text='Your account does not have access to this action.' if scenario in ('permission-notice','mutated-state') else 'Synthetic response')
        failed=None
        def protocol_check(name, passed):
            if not passed:raise AssertionError(name)
        trial_env={**env,'check':protocol_check}
        with patch.object(target,'revoked_owner',authority):
            try:target.refuse_owner_action(trial_env,Session(),url,'staff_save',{'name':'Synthetic member'})
            except (AssertionError,OSError) as e:failed=e
        expected=scenario not in ('403','permission-notice')
        check(scenario+' has the intended result',(failed is not None)==expected)
        check(scenario+' always restores original owner authority',state['restored'] and not state['revoked'] and state['posts']==1)
    for override in ({'csrf':'bad'},{'action':'other'},{'owner_password':'wrong'},{'current_password':'wrong'},{'password':'wrong'}):
        failed=False
        try:target.refuse_owner_action(env,Session(),url,'staff_save',override)
        except AssertionError:failed=True
        check('Protocol refuses authentication/CSRF override '+next(iter(override)),failed)
except Exception as exc:
    failure=type(exc).__name__+': '+str(exc)
print(json.dumps({'passed': 'failure' not in globals(), 'scope':'Actual PHP updater template and strict test-helper protocol doubles; no real SQL/HTTP/browser run', 'checks':checks,'failure':globals().get('failure')},indent=2))
raise SystemExit(1 if 'failure' in globals() else 0)
