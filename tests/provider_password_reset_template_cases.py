"""Installed owner template review and one write through closed synthetic HTTPS.

The independent reads confirm exact stored text, never email rendering or delivery.
"""
import base64
import hashlib
import json
import time
from types import SimpleNamespace
from urllib.parse import urlparse

import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER


CAPTURES = ('Password-Reset-Template-Review-Desktop.png',
            'Password-Reset-Template-Review-Mobile.png')


def run_browser(env, browser):
    base, qa, provider = (env[k] for k in ('BASE', 'QA', 'provider_server'))
    soup, token, submit, form_data = (env[k] for k in ('soup', 'token', 'submit', 'form_data'))
    checks = []

    def check(name, condition):
        name = 'Reset template ' + name
        assert not any(c['name'] == name for c in checks), 'Duplicate template outcome'
        checks.append({'name': name, 'passed': bool(condition)})
        env['check'](name, condition)

    url = base + '/existing/admin/index.php?view=provider-settings'
    route = '/reseller/*/passwordResetEmail'
    table = 'provider_password_reset_template_events'
    owner = requests.Session()
    response = submit(owner, base + '/existing/admin/index.php?view=login', 'login',
                      {'email': 'owner@example.invalid', 'password': OWNER})
    check('actual owner login succeeds', response.status_code == 200 and urlparse(response.url).query == 'view=dashboard')
    profile = db('SELECT * FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    calls, posts, paths = provider.calls, list(provider.posts), list(provider.get_paths)
    mail_capture = qa / 'mail-capture.txt'
    mail_before = mail_capture.read_bytes() if mail_capture.exists() else None
    smtp_before = (env['submission_server'].connections, len(env['submission_server'].attempts))
    original_tables = [r['TABLE_NAME'] for r in db('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,12)=? ORDER BY TABLE_NAME', ['ci_provider_'])]

    def rows(name):
        return sorted(json.dumps(r, sort_keys=True, separators=(',', ':')) for r in db('SELECT * FROM ' + name))

    inherited = {name: rows(name) for name in original_tables}

    def snapshot():
        names = [r['TABLE_NAME'] for r in db('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,12)=? ORDER BY TABLE_NAME', ['ci_provider_'])]
        value = {name: rows(name) for name in names}
        value['audit'] = rows('ci_audit')
        value['settings'] = sorted(json.dumps(r, sort_keys=True) for r in db('SELECT * FROM ci_settings WHERE LEFT(name,?)=?', [len('provider_password_reset_template_'), 'provider_password_reset_template_']))
        return hashlib.sha256(json.dumps(value, sort_keys=True).encode()).hexdigest(), provider.calls, list(provider.posts)

    def form(response, marker):
        result = soup(response).select_one('[data-reset-template-' + marker + ']')
        assert response.status_code == 200 and result is not None, 'Missing template ' + marker + ' form'
        return result.select_one('form') if marker == 'review' else result

    def post_form(marker, **overrides):
        response = owner.get(url, timeout=25)
        data = form_data(form(response, marker)); data.update(overrides)
        return owner.post(url, data=data, timeout=35)

    def cool():
        last = db('SELECT MAX(recorded_epoch) AS epoch FROM ci_' + table + ' WHERE profile_id=? AND kind=?', [profile['id'], 'read_start'])[0]['epoch']
        time.sleep(max(0, int(last or 0) + 31 - time.time()))

    before = snapshot(); response = owner.get(url, timeout=25)
    check('cold GET has explicit preparation and no storage or provider side effect',
          response.status_code == 200 and soup(response).select_one('[data-reset-template-prepare]') is not None
          and not db('SHOW TABLES LIKE "ci_provider_password_reset_template_events"') and snapshot() == before)
    for label, fields in [('CSRF', {'csrf': 'invalid', 'current_password': OWNER}),
                          ('password', {'current_password': 'wrong'})]:
        before = snapshot(); response = post_form('prepare', **fields)
        check('preparation refuses ' + label + ' without writes', response.status_code == 200 and bool(soup(response).select('.notice.error')) and snapshot() == before)
    response = post_form('prepare', current_password=OWNER)
    check('explicit owner preparation has no provider call', response.status_code == 200 and soup(response).select_one('[data-reset-template-refresh]') is not None and provider.calls == calls)
    before_text = '\nPRIVATE_TEMPLATE_HTTP_BEFORE\r\n</textarea><script>window.templateLeak=1</script> & "quoted" — {{reset_url}}\t'
    desired = '\nPRIVATE_TEMPLATE_HTTP_AFTER\n<strong>Opaque email source</strong> & "quoted" — {{reset_url}}\t'
    provider.reset_template = before_text; provider.reset_template_mode = 'success'; provider.reset_template_write_mode = 'accepted'
    response = post_form('refresh', current_password=OWNER)
    check('first real GET returns an editor without write intent', response.status_code == 200 and provider.calls == calls + 1 and provider.get_paths[-1] == route and provider.posts == posts)
    edit = form(response, 'edit'); draft = form_data(edit); draft['content'] = desired
    before = snapshot(); response = owner.post(url, data=draft, timeout=25)
    review = form(response, 'review'); approved = form_data(review)
    rendered = soup(response).select_one('[data-reset-template-review]')
    check('raw review preserves exact escaped before and after text without mutations',
          [n.get_text().removeprefix('\n') for n in rendered.select('pre')] == [before_text, desired]
          and not rendered.select('script,strong') and snapshot() == before
          and base64.b64decode(approved['content_base64']).decode() == desired)
    original = {k: approved[k] for k in ('request_id', 'profile_id', 'revision', 'observation_id', 'review_sha256', 'content_base64')}
    for label, overrides in [('CSRF', {'csrf': 'invalid', 'current_password': OWNER, 'reviewed': '1'}),
                              ('password', {'current_password': 'wrong', 'reviewed': '1'}),
                              ('consent', {'current_password': OWNER})]:
        data = dict(approved); data.update(overrides); before = snapshot()
        response = owner.post(url, data=data, timeout=25)
        retry = form_data(form(response, 'review'))
        check('failed ' + label + ' preserves original approved bytes and clears secrets',
              response.status_code == 200 and bool(soup(response).select('.notice.error'))
              and all(retry.get(k) == v for k, v in original.items())
              and not retry.get('current_password') and 'reviewed' not in retry and snapshot() == before)
    for label, overrides in [('proof', {'review_sha256': '0' * 64}),
                              ('scope', {'revision': str(int(approved['revision']) + 1)}),
                              ('text', {'content_base64': base64.b64encode((desired + ' changed').encode()).decode()})]:
        data = dict(approved); data.update({'current_password': OWNER, 'reviewed': '1', **overrides}); before = snapshot()
        response = owner.post(url, data=data, timeout=25)
        check('altered ' + label + ' does not render a replacement approval or send',
              response.status_code == 200 and soup(response).select_one('[data-reset-template-review]') is None
              and 'needs a current review' in response.text and snapshot() == before)
    before = snapshot(); response = owner.get(url + '&support=fixture', timeout=25)
    check('support route cannot expose private editor', response.status_code == 403 and 'PRIVATE_TEMPLATE_' not in response.text and snapshot() == before)

    context = browser.new_context()
    context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in owner.cookies])
    page = context.new_page(); errors = []; page.on('pageerror', lambda error: errors.append(str(error)))
    try:
        page.goto(url); section = page.locator('#password-reset-template')
        section.locator('summary').first.click()
        textarea = section.locator('[data-reset-template-edit] textarea[name=content]')
        expect(textarea).to_be_visible()
        # HTML normalizes CRLF, but preserves the leading LF after the renderer's deliberate sentinel LF.
        check('actual editor preserves leading newline and treats opaque markup as text',
              textarea.input_value() == before_text.replace('\r\n', '\n') and page.evaluate('typeof window.templateLeak') == 'undefined')
        textarea.fill(desired); section.locator('[data-reset-template-edit] button').click()
        review_locator = page.locator('[data-reset-template-review]'); expect(review_locator).to_be_visible()
        check('Chromium review has escaped original and proposed source',
              review_locator.locator('pre').all_text_contents() == [before_text.replace('\r\n', '\n'), desired]
              and review_locator.locator('script').count() == 0 and page.evaluate('typeof window.templateLeak') == 'undefined')
        for width, height, name in [(1440, 1050, CAPTURES[0]), (390, 844, CAPTURES[1])]:
            page.set_viewport_size({'width': width, 'height': height}); page.evaluate('document.fonts.ready')
            review_locator.screenshot(path=str(qa / name))
            check(name + ' has bounded readable source and controls',
                  page.evaluate('document.documentElement.scrollWidth <= innerWidth')
                  and review_locator.locator('button').evaluate_all('bs => bs.length === 1 && bs.every(b => b.scrollWidth <= b.clientWidth + 1)'))
        captured = form_data(soup(SimpleNamespace(text=page.content())).select_one('[data-reset-template-review] form'))
        # HTML form submission normalizes textarea newlines to CRLF before review.
        # The sealed final form must preserve those reviewed bytes exactly.
        sent_text = base64.b64decode(captured['content_base64']).decode()
        check('final form seals exactly the browser-submitted reviewed text', sent_text == desired.replace('\n', '\r\n'))
        captured.update({'current_password': OWNER, 'reviewed': '1'})
        review_locator.locator('[name=reviewed]').check(); review_locator.locator('[name=current_password]').fill(OWNER)
        review_locator.locator('button').click(); expect(page.locator('[data-reset-template-history]')).to_be_visible()
        check('actual save click records one pending acknowledgment without changing GET text',
              '20i acknowledged the request; confirmation pending' in page.locator('[data-reset-template-history]').inner_text()
              and provider.calls == calls + 2 and provider.posts == posts + [{'path': route, 'body': {'content': sent_text}}]
              and provider.reset_template == before_text)
        before = snapshot(); replay = page.request.post(url, form=captured)
        check('captured exact save replay is inert', replay.status == 200 and snapshot() == before)
        check('review and actual save have no browser script errors', not errors)
    finally:
        context.close()

    cool(); response = post_form('verify', current_password=OWNER)
    check('separate different GET retains unresolved history with no resend',
          response.status_code == 200 and '20i reports a different template' in response.text
          and soup(response).select_one('[data-reset-template-edit]') is None and provider.posts == posts + [{'path': route, 'body': {'content': sent_text}}])
    provider.reset_template = sent_text; cool(); response = post_form('verify', current_password=OWNER)
    check('separate exact GET reports stored text without an email-delivery claim',
          response.status_code == 200 and 'Requested template reported by 20i' in response.text
          and 'does not confirm how the email renders or whether an email was delivered' in response.text
          and soup(response).select_one('[data-reset-template-edit]') is not None)
    check('journey uses exactly three GETs and one POST with no fixture errors',
          provider.calls == calls + 4 and provider.get_paths == paths + [route] * 3
          and provider.posts == posts + [{'path': route, 'body': {'content': sent_text}}] and not provider.errors)
    check('template editing does not send local or SMTP email',
          (mail_capture.read_bytes() if mail_capture.exists() else None) == mail_before
          and (env['submission_server'].connections, len(env['submission_server'].attempts)) == smtp_before)
    check('all inherited provider rows and original profile remain unchanged',
          all(all(r in rows(name) for r in saved) for name, saved in inherited.items())
          and db('SELECT * FROM ci_provider_profiles WHERE id=?', [profile['id']]) == [profile])
    private_rows = json.dumps(db('SELECT * FROM ci_' + table) + db('SELECT * FROM ci_provider_operation_grants') + db('SELECT * FROM ci_provider_operations') + db('SELECT * FROM ci_audit'))
    check('template plaintext and owner password never enter durable generic metadata', 'PRIVATE_TEMPLATE_' not in private_rows and OWNER not in private_rows)
    owner.close()
    return {'passed': all(c['passed'] for c in checks), 'checks': checks, 'captures': list(CAPTURES)}
