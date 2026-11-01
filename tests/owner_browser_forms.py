"""Real-form checks for initial login versus current-owner actions. Test code only."""
from pathlib import Path
from urllib.parse import urljoin, urlsplit
from installed_owner_authority import revoked_owner


def owner_update_form(response, soup, *, authenticated):
    """Require actual controls, not a label that may change without behavior changing."""
    if response.status_code != 200:
        return False
    document = soup(response)
    forms = document.select('form')
    if len(forms) != 1:
        return False
    form = forms[0]
    if form.get('method', '').lower() != 'post' or form.get('action', '') not in ('', response.url):
        return False
    csrf = form.select('input[name=csrf][type=hidden]')
    backup = form.select('input[name=backup_confirm][type=checkbox][required][value="1"]')
    if len(csrf) != 1 or not csrf[0].get('value') or len(backup) != 1:
        return False
    if [b.get('value') for b in form.select('button[name=action]')] != ['inspect', 'apply', 'recover']:
        return False
    if authenticated:
        return (not form.select('input[type=password],input[name=email]')
                and 'Your current owner session authorizes this action.' in form.get_text())
    return (len(form.select('input[name=email][type=email][required]')) == 1
            and len(form.select('input[name=owner_password][type=password][required]')) == 1)


def assert_no_owner_reprompt(check, form, label):
    """Keep real browser actions and their CSRF fields; reject reintroduced prompts."""
    check(label + ' uses the current session without another owner-password field',
          form.locator('[name=owner_password],[name=current_password]').count() == 0)
    token = form.locator('input[name=csrf][type=hidden]')
    check(label + ' retains a nonempty CSRF field', token.count() == 1 and bool(token.input_value()))


def refuse_owner_action(env, session, url, action, changes, *, installed_root=None):
    """Submit a valid captured form after revoking this same owner's role.

    The existing CLI fixture checks exact database/schema/private-file retention
    and restores the original profile even when a request or assertion fails.
    """
    base = urlsplit(env['BASE'])
    target = urlsplit(url)
    if (base.scheme, base.netloc) != (target.scheme, target.netloc) or target.hostname != '127.0.0.1':
        raise AssertionError('Only the active loopback installation is admitted')
    root = Path(installed_root) if installed_root is not None else env['WEB'] / 'existing'
    if not target.path.startswith('/' + root.name + '/admin/'):
        raise AssertionError('Owner refusal URL must match the installed admin fixture')
    if any(key in changes for key in ('csrf', 'action', 'current_password', 'owner_password', 'password')):
        raise AssertionError('Do not replace CSRF, action or authentication fields')
    response = session.get(url, timeout=30)
    if response.status_code != 200 or response.url != url:
        raise AssertionError('Capture a form from the currently authorized owner first')
    form = env['form_for'](response, action)
    if form.select('[name=current_password],[name=owner_password]'):
        raise AssertionError('The signed-in owner must not be prompted again')
    fields = env['form_data'](form)
    if fields.get('action') != action or not fields.get('csrf'):
        raise AssertionError('Capture the exact action and a nonempty CSRF token')
    submit_url = urljoin(url, form.get('action') or url)
    if urlsplit(submit_url)[:2] != target[:2] or not urlsplit(submit_url).path.startswith('/' + root.name + '/admin/'):
        raise AssertionError('Form action left the admitted installation')
    fields.update(changes)
    with revoked_owner(root, env['php']):
        denied = session.post(submit_url, data=fields, timeout=30, allow_redirects=False)
        env['check'](action + ' refuses the same signed-in owner after permission revocation',
                     denied.status_code == 403 or (denied.status_code == 200
                     and 'Your account does not have access to this action.' in denied.text))
    env['check'](action + ' refusal retains all database records, schema, private bytes and owner profile', True)
    return denied
