"""Portable checks for the real-upgrade comparator and exact form extraction, not HTTP acceptance."""
from copy import deepcopy
from pathlib import Path
from types import SimpleNamespace
import json

from release_125_retention import retained, exact_business
from release_125_upgrade_cases import fields


def main(output):
    checks = []

    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        assert condition, name

    before = {'version': '1.24.0', 'tables': {
        'users': {'count': 1, 'rows': {'1' * 64: {'password_hash': 'a' * 64, 'auth_version': 'b' * 64}}},
        'settings': {'count': 1, 'rows': {'2' * 64: {'name': 'c' * 64, 'value': 'd' * 64}}},
        'audit': {'count': 1, 'rows': {'3' * 64: {'detail': 'e' * 64}}}},
        'private_files': {'provider-master.key': {'bytes': 32, 'sha256': 'f' * 64}}}
    check('Exact original columns and private bytes pass', retained(before, deepcopy(before)))
    added = deepcopy(before); added['version'] = '1.25.0'
    added['tables']['users']['rows']['1' * 64]['new_column'] = '6' * 64
    added['tables']['licenses'] = {'count': 1, 'rows': {'4' * 64: {'client_id': '5' * 64}}}
    check('Explicit later columns and families do not impersonate original records', retained(before, added))

    def refuses(name, mutation, comparator=retained):
        after = deepcopy(before); mutation(after)
        try: comparator(before, after)
        except (AssertionError, KeyError, TypeError): check(name, True)
        else: check(name, False)

    refuses('Missing original table fails', lambda a: a['tables'].pop('users'))
    refuses('Missing original row fails', lambda a: a['tables']['users'].update({'count': 0, 'rows': {}}))
    refuses('Removed original authority column fails', lambda a: a['tables']['users']['rows']['1' * 64].pop('auth_version'))
    refuses('Changed original password fails', lambda a: a['tables']['users']['rows']['1' * 64].update(password_hash='0' * 64))
    refuses('Changed original session authority fails', lambda a: a['tables']['users']['rows']['1' * 64].update(auth_version='0' * 64))
    refuses('Changed original audit evidence fails', lambda a: a['tables']['audit']['rows']['3' * 64].update(detail='0' * 64), exact_business)
    refuses('Changed original vault key fails', lambda a: a['private_files']['provider-master.key'].update(sha256='0' * 64))
    refuses('Missing original private file fails', lambda a: a['private_files'].clear())
    refuses('Inconsistent row counts fail', lambda a: a['tables']['users'].update(count=0))
    refuses('Boolean fake row count fails', lambda a: a['tables']['users'].update(count=True))
    refuses('Nonhash private contents cannot enter proof', lambda a: a['private_files']['provider-master.key'].update(sha256='raw private bytes'))
    changed = deepcopy(before); changed['tables']['settings']['rows']['2' * 64]['value'] = '7' * 64
    cell = ('settings', '2' * 64, 'value')
    check('One named original migration cell may transition', retained(before, changed, changed_cells=[cell]))
    try: retained(before, changed, changed_cells=[('settings', '0' * 64, 'value')])
    except AssertionError: check('Unrelated migration exception cannot suppress a changed cell', True)
    else: check('Unrelated migration exception cannot suppress a changed cell', False)
    added_audit = deepcopy(before); added_audit['tables']['audit']['rows']['8' * 64] = {'detail': '9' * 64}; added_audit['tables']['audit']['count'] = 2
    check('Retry permits new audit without replacing old audit', exact_business(before, added_audit))
    refuses('Retry rejects duplicate business row', lambda a: a['tables']['users'].update(count=2, rows={**a['tables']['users']['rows'], '8' * 64: {'auth_version': 'b' * 64}}), exact_business)
    refuses('Retry rejects newly created business table', lambda a: a['tables'].update(new_family={'count': 0, 'rows': {}}), exact_business)
    refuses('Retry rejects new private key', lambda a: a['private_files'].update({'replacement.key': {'bytes': 32, 'sha256': '0' * 64}}), exact_business)

    response = lambda body, status=200: SimpleNamespace(text=body, status_code=status, url='http://127.0.0.1/subfolder/owner-update.php')
    updater = '<form method="post"><input name="csrf" value="captured"><input name="owner_password" type="password"><input name="backup_confirm" type="checkbox" value="1"><button name="action" value="inspect">Inspect</button><button name="action" value="apply">Apply</button><button name="action" value="recover">Recover</button></form>'
    for action in ['inspect', 'apply', 'recover']:
        target, values = fields(response(updater), action)
        check('Actual multi-button updater selects only ' + action, values == {'csrf': 'captured', 'action': action} and target.endswith('/owner-update.php'))
    recurring = '<form><input name="action" value="recurring_command"><input name="command" value="accept"><input name="revision" value="7"><textarea name="reason">Saved reason</textarea><input name="confirm" type="checkbox" value="1"></form>'
    check('Recurring selected command retains captured action and revision', fields(response(recurring), 'accept', field='command')[1]
          == {'action': 'recurring_command', 'command': 'accept', 'revision': '7', 'reason': 'Saved reason'})
    distinct = '<form><input name="action" value="studio_invite"><input name="id" value="1"></form><form><input name="action" value="studio_invite"><input name="id" value="2"></form>'
    check('Exact selector uses intended original client', fields(response(distinct), 'studio_invite', {'id': 2})[1]['id'] == '2')
    for name, page, action, selector in [
        ('Ambiguous forms refuse rather than choosing first', response(distinct), 'studio_invite', None),
        ('Missing current selector refuses', response(distinct), 'studio_invite', {'id': 3}),
        ('Unexpected HTTP failure cannot stand in for a form', response(updater, 500), 'apply', None),
        ('Missing submit action refuses', response(updater), 'erase', None)]:
        try: fields(page, action, selector)
        except AssertionError: check(name, True)
        else: check(name, False)
    Path(output).write_text(json.dumps({'passed': True, 'scope': 'Portable comparator/form source tests; no installed DB/Apache/browser execution', 'checks': checks}, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'portable release-upgrade guards')


if __name__ == '__main__':
    import sys
    main(sys.argv[1])
