"""Installed public-client identity acceptance with synthetic captured mail only."""
from email import policy
from email.parser import BytesParser
from email.utils import getaddresses
from copy import deepcopy
import re
import time
from urllib.parse import parse_qsl, urlencode, urljoin, urlsplit

import requests

from release_cases import db


EMAIL = 'identity-browser@example.invalid'
UNKNOWN_EMAIL = 'identity-unknown@example.invalid'
VERIFY_PASSWORD = 'Verified client identity passphrase 2026'
RESET_PASSWORD = 'Recovered client identity passphrase 2026'
OFFER = '1' * 32
OFFER_SHA256 = '2' * 64
MAIL_END = b'\n---END---\n'
COMMERCIAL_TABLES = (
    'studio_jobs',
    'studio_invoices',
    'invoice_payments',
    'payment_requests',
    'payment_attempts',
    'service_orders',
    'service_subscriptions',
    'hosting_checkout_reviews',
    'hosting_checkout_results',
    'provider_operations',
    'provider_operation_grants',
    'website_licenses',
)


def run_browser(env, browser):
    globals().update({key: env[key] for key in [
        'BASE', 'QA', 'check', 'soup', 'form_data', 'form_for', 'submit', 'provider_server',
    ]})
    portal = BASE + '/existing/portal/index.php'
    descriptor = {'hosting': 'checkout', 'offer': OFFER, 'offer_sha256': OFFER_SHA256}
    descriptor_query = urlencode(descriptor)
    register_url = portal + '?register=1&' + descriptor_query
    resend_url = portal + '?resend=1&' + descriptor_query
    forgot_url = portal + '?forgot=1&' + descriptor_query
    login_url = portal + '?' + descriptor_query
    capture_path = QA / 'mail-capture.txt'

    def setting_rows():
        return db("SELECT name,value FROM ci_settings WHERE name IN ('email_mode','mail_from') ORDER BY name")

    original_settings = setting_rows()

    def commercial_state():
        state = {}
        for name in COMMERCIAL_TABLES:
            exists = db(
                'SELECT TABLE_NAME FROM information_schema.tables '
                'WHERE table_schema=DATABASE() AND table_name=?',
                ['ci_' + name],
            )
            state[name] = db('SELECT * FROM ci_' + name + ' ORDER BY 1') if exists else None
        return state

    def provider_state():
        return deepcopy({
            'calls': provider_server.calls,
            'posts': provider_server.posts,
            'names': provider_server.names,
            'packages': provider_server.packages,
            'get_paths': provider_server.get_paths,
            'errors': provider_server.errors,
        })

    def mail_offset():
        return capture_path.stat().st_size if capture_path.exists() else 0

    def appended_messages(offset, expected, label):
        raw = capture_path.read_bytes()[offset:] if capture_path.exists() else b''
        complete = raw.endswith(MAIL_END)
        parts = raw[:-len(MAIL_END)].split(MAIL_END) if complete and raw else []
        check(label + ' mail append is complete and exact', complete and len(parts) == expected and all(parts))
        return [BytesParser(policy=policy.default).parsebytes(part) for part in parts]

    def message_body(message):
        part = message.get_body(preferencelist=('plain',))
        if part is None:
            raise AssertionError('Captured identity mail has no plain-text body')
        try:
            return part.get_content()
        except (LookupError, UnicodeDecodeError, AttributeError):
            raise AssertionError('Captured identity mail body is not valid text')

    def captured_link(offset, route, lifetime_text, label):
        messages = appended_messages(offset, 1, label)
        message = messages[0]
        body = message_body(message)
        links = re.findall(r'https?://[^\s<>"\']+', body)
        recipients = [address.lower() for _, address in getaddresses(message.get_all('To', []))]
        expected_subject = (
            'Verify your CajunWebPro account' if route == 'verify'
            else 'Reset your CajunWebPro password'
        )
        check(label + ' mail uses the intended synthetic recipient',
              recipients == [EMAIL])
        check(label + ' mail uses the exact account-security subject',
              str(message.get('Subject', '')) == expected_subject)
        check(label + ' mail states its bounded lifetime', lifetime_text in body)
        check(label + ' mail contains one account-security link', len(links) == 1)
        try:
            parsed = urlsplit(links[0])
            pairs = parse_qsl(parsed.query, keep_blank_values=True, strict_parsing=True)
        except (TypeError, ValueError):
            raise AssertionError('Captured identity mail link is not structurally valid')
        expected_keys = [route, 'hosting', 'offer', 'offer_sha256']
        values = dict(pairs)
        check(label + ' link is local and route-bounded',
              parsed.scheme == 'http' and parsed.hostname == '127.0.0.1' and parsed.port == 8091
              and parsed.username is None and parsed.password is None and parsed.fragment == ''
              and parsed.path == '/existing/portal/index.php'
              and len(pairs) == len(expected_keys) and [key for key, _ in pairs] == expected_keys)
        check(label + ' link retains only the closed checkout descriptor',
              re.fullmatch(r'[a-f0-9]{64}', values.get(route, '')) is not None
              and values.get('hosting') == 'checkout'
              and values.get('offer') == OFFER
              and values.get('offer_sha256') == OFFER_SHA256)
        return links[0], values[route]

    def identity_form(response, action):
        for form in soup(response).select('form'):
            field = form.select_one('input[name=action]')
            if field is not None and field.get('value') == action:
                return form
        raise AssertionError('Expected account-security form is missing')

    def identity_get(session, url):
        try:
            return session.get(url, timeout=15)
        except requests.RequestException:
            raise AssertionError('Account-security GET did not complete')

    def post_identity(session, url, response, action, changes):
        form = identity_form(response, action)
        data = form_data(form)
        data.update(changes)
        try:
            return session.post(urljoin(url, form.get('action') or url), data=data, timeout=25)
        except requests.RequestException:
            raise AssertionError('Account-security POST did not complete')

    def timed_identity(session, url, response, action, changes):
        started = time.perf_counter()
        result = post_identity(session, url, response, action, changes)
        return result, time.perf_counter() - started

    def has_form(response, action):
        return any(
            field.get('value') == action
            for field in soup(response).select('form input[name=action]')
        )

    def headers_are_private(response):
        return (
            'no-store' in response.headers.get('Cache-Control', '')
            and 'private' in response.headers.get('Cache-Control', '')
            and response.headers.get('Referrer-Policy') == 'no-referrer'
            and 'noindex' in response.headers.get('X-Robots-Tag', '')
        )

    def destination_is_exact(url):
        try:
            parsed = urlsplit(url)
            pairs = parse_qsl(parsed.query, keep_blank_values=True, strict_parsing=True)
        except (TypeError, ValueError):
            return False
        return (
            parsed.scheme == 'http'
            and parsed.hostname == '127.0.0.1'
            and parsed.port == 8091
            and parsed.path == '/existing/portal/index.php'
            and pairs == list(descriptor.items())
        )

    def token_metadata(purpose):
        return db(
            'SELECT t.client_id,t.purpose,t.expires_at,t.created_at '
            'FROM ci_studio_client_tokens t JOIN ci_studio_clients c ON c.id=t.client_id '
            'WHERE c.email=? AND t.purpose=?',
            [EMAIL, purpose],
        )

    def sign_in(password):
        session = requests.Session()
        page = session.get(login_url, timeout=15)
        response = post_identity(session, login_url, page, 'login', {'email': EMAIL, 'password': password})
        return session, response

    def capture_confirmation(url, stem, expected_action):
        context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce')
        outside = []
        errors = []

        def route(request):
            parsed = urlsplit(request.request.url)
            if parsed.hostname != '127.0.0.1' or parsed.port != 8091:
                outside.append(parsed.hostname or '')
                request.abort()
            else:
                request.continue_()

        context.route('**/*', route)
        page = context.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        try:
            response = page.goto(url, wait_until='networkidle')
        except Exception:
            context.close()
            raise AssertionError(stem + ' confirmation navigation did not complete')
        check(stem + ' confirmation renders in Chromium',
              response is not None and response.status == 200
              and page.locator('input[name=action][value="' + expected_action + '"]').count() == 1)
        for width, height, label in [(1440, 1000, 'Desktop'), (390, 844, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height})
            page.wait_for_timeout(100)
            check(stem + ' confirmation fits ' + label.lower(),
                  page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            target = QA / ('Client-Identity-' + stem + '-' + label + '.png')
            check(stem + ' screenshot target is new for ' + label.lower(), not target.exists())
            page.screenshot(path=str(target), full_page=True, animations='disabled')
            check(stem + ' screenshot is nonempty for ' + label.lower(), target.stat().st_size > 0)
        check(stem + ' confirmation makes no external request or script error', not outside and not errors)
        context.close()

    db('DELETE FROM ci_rate_limits')
    before_commercial = commercial_state()
    before_provider = provider_state()
    try:
        db("UPDATE ci_settings SET value='disabled' WHERE name='email_mode'")
        unavailable_registration = requests.Session().get(register_url, timeout=15)
        unavailable_login = requests.Session().get(login_url, timeout=15)
        check('Mail-disabled installation hides public registration recovery and resend',
              unavailable_registration.status_code == 200
              and not has_form(unavailable_registration, 'public_register')
              and 'not available yet' in unavailable_registration.text
              and '?register=1' not in unavailable_login.text
              and '?forgot=1' not in unavailable_login.text
              and '?resend=1' not in unavailable_login.text)
        db("UPDATE ci_settings SET value='mail' WHERE name='email_mode'")
        db("UPDATE ci_settings SET value='studio@example.invalid' WHERE name='mail_from'")
        check('Installed public identity storage is prepared',
              db("SELECT name,value FROM ci_settings WHERE name IN ('contacts_schema_version','client_identity_schema_version') ORDER BY name") == [
                  {'name': 'client_identity_schema_version', 'value': '1'},
                  {'name': 'contacts_schema_version', 'value': '1'},
              ])

        visitor = requests.Session()
        registration = visitor.get(register_url, timeout=15)
        registration_form = identity_form(registration, 'public_register')
        check('Registration route is private and presents CSRF protection',
              registration.status_code == 200 and headers_are_private(registration)
              and registration_form.select_one('input[name=csrf]') is not None)
        check('Registration collects no password before mailbox verification',
              not registration_form.select('input[type=password],input[name=password],input[name=confirm_password]'))
        check('Unavailable checkout offer is truthful and never substituted',
              'no longer available in its exact saved form' in registration.text
              and 'no substitute was selected' in registration.text
              and OFFER not in soup(registration).get_text())
        malformed = visitor.get(portal + '?register=1&support=synthetic', timeout=15)
        extra = visitor.get(portal + '?register=1&next=synthetic', timeout=15)
        check('Registration route refuses support and non-allowlisted continuation state',
              malformed.status_code == 400 and extra.status_code == 400
              and headers_are_private(malformed) and headers_are_private(extra)
              and not has_form(malformed, 'public_register') and not has_form(extra, 'public_register'))

        before_mail = mail_offset()
        forged_data = form_data(registration_form)
        forged_data.update({'csrf': 'forged', 'name': 'Identity Browser Customer', 'email': EMAIL})
        forged = visitor.post(register_url, data=forged_data, timeout=25)
        check('Registration rejects forged CSRF without creating an account',
              'session expired' in forged.text.lower()
              and not db('SELECT id FROM ci_studio_clients WHERE email=?', [EMAIL])
              and mail_offset() == before_mail)

        registration = visitor.get(register_url, timeout=15)
        initial_mail = mail_offset()
        created, created_seconds = timed_identity(visitor, register_url, registration, 'public_register', {
            'name': 'Identity Browser Customer',
            'email': EMAIL,
            'password': 'Ignored preregistration input',
            'confirm_password': 'Ignored preregistration input',
        })
        check('Registration returns the same neutral verification guidance',
              created.status_code == 200
              and 'If that address can be registered, a verification link has been sent.' in created.text)
        original_verify_url, original_verify_token = captured_link(
            initial_mail, 'verify', '24 hours', 'Initial verification',
        )
        client_rows = db('SELECT id,name,email,active FROM ci_studio_clients WHERE email=?', [EMAIL])
        registration_rows = db(
            'SELECT r.verified_at FROM ci_studio_client_registrations r JOIN ci_studio_clients c ON c.id=r.client_id WHERE c.email=?',
            [EMAIL],
        )
        check('Registration creates one pending primary identity only',
              len(client_rows) == 1 and client_rows[0]['name'] == 'Identity Browser Customer'
              and int(client_rows[0]['active']) == 1 and registration_rows == [{'verified_at': None}])

        pending_session, pending_login = sign_in('Ignored preregistration input')
        check('Injected preregistration password cannot authenticate',
              'Email or password not recognized' in pending_login.text and has_form(pending_login, 'login'))

        duplicate_mail = mail_offset()
        duplicate_session = requests.Session()
        duplicate_page = duplicate_session.get(register_url, timeout=15)
        duplicate, duplicate_seconds = timed_identity(
            duplicate_session, register_url, duplicate_page, 'public_register',
            {'name': 'Duplicate Probe', 'email': EMAIL},
        )
        check('Duplicate registration keeps the same neutral public response',
              'If that address can be registered, a verification link has been sent.' in duplicate.text
              and len(db('SELECT id FROM ci_studio_clients WHERE email=?', [EMAIL])) == 1
              and mail_offset() == duplicate_mail)
        check('Eligible and duplicate registration share the bounded neutral timing envelope',
              6.5 <= created_seconds <= 10.0 and 6.5 <= duplicate_seconds <= 10.0
              and abs(created_seconds - duplicate_seconds) <= 1.5)

        db("UPDATE ci_studio_client_tokens t JOIN ci_studio_clients c ON c.id=t.client_id SET t.expires_at=? WHERE c.email=? AND t.purpose='verify'",
           [1, EMAIL])
        expired = identity_get(requests.Session(), original_verify_url)
        check('Expired verification link is read-only and offers resend',
              expired.status_code == 200 and not has_form(expired, 'public_verify')
              and 'expired, was replaced, or has already been used' in expired.text)

        unknown_resend = requests.Session()
        unknown_page = unknown_resend.get(resend_url, timeout=15)
        unknown_mail = mail_offset()
        unknown_response, unknown_resend_seconds = timed_identity(
            unknown_resend, resend_url, unknown_page, 'public_resend', {'email': UNKNOWN_EMAIL},
        )
        check('Unknown resend remains neutral without producing mail',
              'If that address belongs to a pending account' in unknown_response.text
              and mail_offset() == unknown_mail)

        resend = requests.Session()
        resend_page = resend.get(resend_url, timeout=15)
        replacement_mail = mail_offset()
        resend_response, resend_seconds = timed_identity(
            resend, resend_url, resend_page, 'public_resend', {'email': EMAIL},
        )
        check('Pending resend uses the neutral replacement response',
              'If that address belongs to a pending account' in resend_response.text)
        check('Eligible and unknown resend share the bounded neutral timing envelope',
              6.5 <= unknown_resend_seconds <= 10.0 and 6.5 <= resend_seconds <= 10.0
              and abs(unknown_resend_seconds - resend_seconds) <= 1.5)
        verify_url, verify_token = captured_link(
            replacement_mail, 'verify', '24 hours', 'Replacement verification',
        )
        check('Verification resend replaces the expired security link',
              verify_url != original_verify_url and verify_token != original_verify_token)
        check('Replaced verification link remains unavailable',
              not has_form(identity_get(requests.Session(), original_verify_url), 'public_verify'))

        verify_session = requests.Session()
        verify_metadata = token_metadata('verify')
        verify_first = identity_get(verify_session, verify_url)
        verify_second = identity_get(verify_session, verify_url)
        check('Verification GET is repeatable, private, and non-consuming',
              headers_are_private(verify_first) and headers_are_private(verify_second)
              and has_form(verify_first, 'public_verify') and has_form(verify_second, 'public_verify')
              and token_metadata('verify') == verify_metadata)
        check('Verification uses its stored closed descriptor and truthful unavailable offer',
              'no longer available in its exact saved form' in verify_second.text
              and 'no substitute was selected' in verify_second.text)
        capture_confirmation(verify_url, 'Verification', 'public_verify')
        forged_verify = post_identity(verify_session, verify_url, verify_second, 'public_verify', {
            'csrf': 'forged', 'password': VERIFY_PASSWORD, 'confirm_password': VERIFY_PASSWORD,
        })
        check('Verification POST rejects forged CSRF without consuming the link',
              'session expired' in forged_verify.text.lower()
              and has_form(identity_get(verify_session, verify_url), 'public_verify'))
        verify_page = identity_get(verify_session, verify_url)
        verified = post_identity(verify_session, verify_url, verify_page, 'public_verify', {
            'password': VERIFY_PASSWORD, 'confirm_password': VERIFY_PASSWORD,
        })
        check('Verification POST chooses the password and resumes exact checkout',
              verified.status_code == 200 and destination_is_exact(verified.url)
              and 'Review hosting' in verified.text and not has_form(verified, 'login'))
        check('Successful verification consumes the link',
              not has_form(identity_get(requests.Session(), verify_url), 'public_verify'))

        unknown_recovery = requests.Session()
        unknown_recovery_page = unknown_recovery.get(forgot_url, timeout=15)
        unknown_recovery_mail = mail_offset()
        unknown_recovery_response, unknown_recovery_seconds = timed_identity(
            unknown_recovery, forgot_url, unknown_recovery_page, 'public_recovery_request',
            {'email': UNKNOWN_EMAIL},
        )
        check('Unknown recovery remains neutral without producing mail',
              'If that address is eligible for recovery' in unknown_recovery_response.text
              and mail_offset() == unknown_recovery_mail)

        recovery = requests.Session()
        recovery_page = recovery.get(forgot_url, timeout=15)
        recovery_mail = mail_offset()
        recovery_response, recovery_seconds = timed_identity(
            recovery, forgot_url, recovery_page, 'public_recovery_request', {'email': EMAIL},
        )
        check('Eligible recovery uses the same neutral public response',
              'If that address is eligible for recovery' in recovery_response.text)
        check('Eligible and unknown recovery share the bounded neutral timing envelope',
              6.5 <= unknown_recovery_seconds <= 10.0 and 6.5 <= recovery_seconds <= 10.0
              and abs(unknown_recovery_seconds - recovery_seconds) <= 1.5)
        reset_url, _ = captured_link(recovery_mail, 'reset', 'one hour', 'Recovery')
        reset_session = requests.Session()
        reset_metadata = token_metadata('recover')
        reset_first = identity_get(reset_session, reset_url)
        reset_second = identity_get(reset_session, reset_url)
        check('Reset GET is repeatable, private, and non-consuming',
              headers_are_private(reset_first) and headers_are_private(reset_second)
              and has_form(reset_first, 'public_recovery_reset')
              and has_form(reset_second, 'public_recovery_reset')
              and token_metadata('recover') == reset_metadata)
        capture_confirmation(reset_url, 'Recovery', 'public_recovery_reset')
        forged_reset = post_identity(reset_session, reset_url, reset_second, 'public_recovery_reset', {
            'csrf': 'forged', 'password': RESET_PASSWORD, 'confirm_password': RESET_PASSWORD,
        })
        check('Reset POST rejects forged CSRF without consuming the link',
              'session expired' in forged_reset.text.lower()
              and has_form(identity_get(reset_session, reset_url), 'public_recovery_reset'))
        reset_page = identity_get(reset_session, reset_url)
        mismatched_reset = post_identity(reset_session, reset_url, reset_page, 'public_recovery_reset', {
            'password': RESET_PASSWORD, 'confirm_password': VERIFY_PASSWORD,
        })
        check('Mismatched recovery passwords preserve the usable security link',
              'Passwords do not match' in mismatched_reset.text
              and has_form(identity_get(reset_session, reset_url), 'public_recovery_reset'))
        reset_page = identity_get(reset_session, reset_url)
        reset = post_identity(reset_session, reset_url, reset_page, 'public_recovery_reset', {
            'password': RESET_PASSWORD, 'confirm_password': RESET_PASSWORD,
        })
        check('Password reset returns to sign-in with the exact closed checkout descriptor',
              reset.status_code == 200 and destination_is_exact(reset.url) and has_form(reset, 'login'))
        check('Successful reset consumes its security link',
              not has_form(identity_get(requests.Session(), reset_url), 'public_recovery_reset'))
        check('Password reset revokes the verification-established session',
              has_form(verify_session.get(portal, timeout=15), 'login'))

        _, old_password_response = sign_in(VERIFY_PASSWORD)
        check('Password recovery invalidates the former password',
              'Email or password not recognized' in old_password_response.text and has_form(old_password_response, 'login'))
        _, new_password_response = sign_in(RESET_PASSWORD)
        check('Recovered password signs in and resumes exact checkout',
              new_password_response.status_code == 200
              and destination_is_exact(new_password_response.url)
              and 'Review hosting' in new_password_response.text
              and not has_form(new_password_response, 'login'))

        check('Public identity lifecycle has no commercial or provider side effect',
              commercial_state() == before_commercial and provider_state() == before_provider)
    finally:
        for row in original_settings:
            db('UPDATE ci_settings SET value=? WHERE name=?', [row['value'], row['name']])
        check('Public identity acceptance restores mail settings', setting_rows() == original_settings)
