"""Test the real HTTP authority fixture orchestration; no SQL is modeled as acceptance."""
from pathlib import Path
import contextlib
import copy
import json
import os
import tempfile
from types import SimpleNamespace
from unittest.mock import patch
import installed_owner_authority as target

checks = []
def check(name, value):
    checks.append({'name': name, 'passed': bool(value)})
    if not value:
        raise AssertionError(name)

with tempfile.TemporaryDirectory(prefix='cwp-owner-http-') as tmp:
    root = Path(tmp)
    installed = root / 'test-runtime' / 'web' / 'existing'
    installed.mkdir(parents=True)
    (installed / '.installed.php').write_text('synthetic marker')
    original = {'user_id': 1, 'role': 'owner', 'enabled': 1, 'permissions': '[]'}
    base = {'profile': original, 'snapshot': {'tables': {'fixture_data': 'unchanged'}, 'files': {'key': 'original-hash'}}}
    env = {'CWP_TEST_DATABASE': 'cwp_test', 'CWP_TEST_HOST': '127.0.0.1'}
    for case in ('normal', 'http-error', 'data-write', 'private-write', 'revoke-error'):
        state = copy.deepcopy(base); calls = []
        def fake_run(args, **kwargs):
            operation = args[-1]; calls.append(operation)
            check(case + ' forwards only a bounded fixture request', args[1] == str(root / 'tests/installed_owner_authority.php') and args[2] == str(installed) and kwargs['timeout'] == 90)
            if operation == 'revoke':
                state['profile'] = {**original, 'role': 'administrator', 'permissions': '[]'}
                if case == 'revoke-error':
                    return SimpleNamespace(returncode=1, stdout='', stderr='Synthetic probe failure after mutation')
            elif operation == 'restore':
                check(case + ' restores captured original identity', json.loads(kwargs['input'])['original'] == original)
                state['profile'] = copy.deepcopy(original)
            return SimpleNamespace(returncode=0, stdout=json.dumps(state), stderr='')
        error = None
        with patch.dict(os.environ, env), patch.object(target, 'ROOT', root), patch.object(target.subprocess, 'run', fake_run):
            try:
                with target.revoked_owner(installed, '/synthetic/php'):
                    check(case + ' really revokes before the HTTP request', state['profile']['role'] == 'administrator')
                    if case == 'http-error':
                        raise ValueError('Synthetic HTTP failure')
                    if case == 'data-write':
                        state['snapshot']['tables']['fixture_data'] = 'changed'
                    if case == 'private-write':
                        state['snapshot']['files']['key'] = 'changed'
            except Exception as exc:
                error = exc
        check(case + ' never leaves the owner demoted', state['profile'] == original and calls[-1] == 'restore')
        check(case + ' accepts only unchanged successful probe execution', error is None if case == 'normal' else error is not None)
        if case == 'http-error':
            check('Original HTTP failure is preserved after successful restoration', isinstance(error, ValueError) and str(error) == 'Synthetic HTTP failure')
    # Actor-specific recovery belongs to the dedicated owner, not user 1.
    # Exercise the real orchestration with both profiles present in the double.
    dedicated = {**original, 'user_id': 12}
    for case in ('normal', 'http-error', 'data-write', 'private-write', 'revoke-error'):
        state = {'profile': copy.deepcopy(dedicated), 'snapshot': copy.deepcopy(base['snapshot'])}
        other = copy.deepcopy(original); calls = []; error = None
        def actor_run(args, **kwargs):
            operation = args[-1]; calls.append(operation)
            request = json.loads(kwargs['input'])
            check('Dedicated ' + case + ' pins the selected actor for ' + operation,
                  request['owner_id'] == 12 and args[2] == str(installed) and kwargs['timeout'] == 90)
            if operation == 'revoke':
                state['profile'] = {**dedicated, 'role': 'administrator', 'permissions': '[]'}
                if case == 'revoke-error':
                    return SimpleNamespace(returncode=1, stdout='', stderr='Synthetic selected-owner probe failure')
            elif operation == 'restore':
                check('Dedicated ' + case + ' restores its captured profile', request['original'] == dedicated)
                state['profile'] = copy.deepcopy(dedicated)
            return SimpleNamespace(returncode=0, stdout=json.dumps(state), stderr='')
        with patch.dict(os.environ, env), patch.object(target, 'ROOT', root), patch.object(target.subprocess, 'run', actor_run):
            try:
                with target.revoked_owner(installed, '/synthetic/php', owner_id=12):
                    check('Dedicated ' + case + ' revokes the selected owner only',
                          state['profile']['role'] == 'administrator' and other == original)
                    if case == 'http-error': raise ValueError('Synthetic selected-owner HTTP failure')
                    if case == 'data-write': state['snapshot']['tables']['fixture_data'] = 'changed'
                    if case == 'private-write': state['snapshot']['files']['key'] = 'changed'
            except Exception as exc:
                error = exc
        check('Dedicated ' + case + ' restores the selected profile without changing the setup owner',
              state['profile'] == dedicated and other == original and calls[-1] == 'restore')
        check('Dedicated ' + case + ' retains strict state and failure checks',
              error is None if case == 'normal' else error is not None)
        if case == 'http-error':
            check('Selected-owner HTTP failure remains visible after restoration',
                  isinstance(error, ValueError) and str(error) == 'Synthetic selected-owner HTTP failure')
    for owner_id in (0, -1, True, False, '12', 12.0, None, 2147483648):
        with patch.dict(os.environ, env), patch.object(target, 'ROOT', root), patch.object(target.subprocess, 'run') as runner:
            denied = False
            try:
                with target.revoked_owner(installed, '/synthetic/php', owner_id=owner_id): pass
            except AssertionError:
                denied = True
            check('Rejects invalid selected actor before subprocess: ' + repr(owner_id), denied and not runner.called)
    with patch.dict(os.environ, env), patch.object(target, 'ROOT', root), patch.object(target.subprocess, 'run',
            return_value=SimpleNamespace(returncode=0, stdout=json.dumps(base), stderr='')) as runner:
        denied = False
        try:
            with target.revoked_owner(installed, '/synthetic/php', owner_id=12): pass
        except AssertionError:
            denied = True
        check('Rejects a different returned actor before revoking anyone', denied and runner.call_count == 1)
    for case in ('database', 'host', 'outside', 'uninstalled'):
        current_env = dict(env); location = installed
        if case == 'database': current_env['CWP_TEST_DATABASE'] = 'production'
        if case == 'host': current_env['CWP_TEST_HOST'] = 'example.invalid'
        if case == 'outside': location = root
        if case == 'uninstalled': (installed / '.installed.php').unlink()
        with patch.dict(os.environ, current_env), patch.object(target, 'ROOT', root), patch.object(target.subprocess, 'run') as runner:
            denied = False
            try:
                with target.revoked_owner(location, '/synthetic/php'):
                    pass
            except (AssertionError, ValueError):
                denied = True
            check('Refuses unsafe context before a subprocess: ' + case, denied and not runner.called)
print(json.dumps({'passed': True, 'scope': 'Actual Python fixture orchestration with bounded process doubles; not SQL or HTTP acceptance', 'checks': checks}, indent=2))
