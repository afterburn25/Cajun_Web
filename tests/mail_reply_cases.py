"""Actual packaged HTTP/Chromium Reply/Forward flow against isolated MariaDB, IMAP and SMTP/TLS."""
import hashlib, json, time, uuid
from email import policy
from email.parser import BytesParser
from urllib.parse import parse_qs, urlparse, urlencode, urljoin
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['ROOT', 'WEB', 'BASE', 'QA', 'check', 'soup', 'token', 'form_data', 'form_for', 'submit', 'inbox_server', 'submission_server']})
    db('DELETE FROM ci_rate_limits')
    root = BASE + '/existing/admin/index.php'
    drafts_url = root + '?view=mail-drafts'
    draft_url = root + '?view=mail-draft'
    accounts = root + '?view=mail-account'
    owner = requests.Session()
    submit(owner, root + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    oid = db("SELECT id FROM ci_users WHERE email='owner@example.invalid'")[0]['id']
    aid = db("SELECT id FROM ci_mail_accounts WHERE label='Staff inbox fixture'")[0]['id']
    route = {'account_id': aid, 'revision': '1', 'uidvalidity': '1234', 'uid': '82', 'folder': 'INBOX'}
    message = root + '?view=mail-message&' + urlencode(route)

    check('Fresh install leaves protected reply storage owner-prepared only', not db("SELECT value FROM fresh_settings WHERE name='mail_replies_schema_version'"))
    r = submit(owner, drafts_url, 'mail_drafts_replies_setup', {'csrf': 'invalid'})
    check('Reply storage setup rejects invalid CSRF', not db("SELECT value FROM ci_settings WHERE name='mail_replies_schema_version'") and 'session expired' in r.text.lower())
    setup = owner.get(drafts_url)
    form = form_for(setup, 'mail_drafts_replies_setup')
    data = form_data(form)
    db('UPDATE ci_users SET auth_version=? WHERE id=?', [uuid.uuid4().hex, oid])
    r = owner.post(urljoin(drafts_url, form.get('action') or drafts_url), data=data)
    denied = soup(r).select_one('input[name="action"][value="login"]') is not None
    check('Reply storage setup refuses a revoked current owner session', not db("SELECT value FROM ci_settings WHERE name='mail_replies_schema_version'") and denied)
    owner = requests.Session()
    submit(owner, root + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    r = submit(owner, drafts_url, 'mail_drafts_replies_setup')
    check('Owner prepares additive protected reply storage', 'Reply and Forward drafts are ready' in r.text and db("SELECT value FROM ci_settings WHERE name='mail_replies_schema_version'") == [{'value': '1'}])
    check('Message route exposes direct copy controls without a prior duplicate read', 'data-mail-copy-controls' in owner.get(message).text)

    detail = accounts + '&account_id=' + aid
    rows = db('SELECT revision FROM ci_mail_account_grants WHERE account_id=? AND user_id=?', [aid, oid])
    owner.post(detail, data={'csrf': token(owner.get(detail)), 'action': 'mail_accounts_grant', 'account_id': aid, 'revision': str(rows[0]['revision'] if rows else 0), 'user_id': oid, 'can_read': '1', 'can_send': '0', 'enabled': '1', 'days': '0', 'current_password': OWNER})
    f = soup(owner.get(accounts)).select_one('[data-mail-account-editor]')
    data = form_data(f)
    data.update(label='Reply sender', address='reply-sender@example.invalid', imap_host='imap.example.invalid', imap_port='993', imap_encryption='ssl', imap_username='synthetic-incoming', imap_password='Synthetic incoming password', smtp_host=submission_server.host, smtp_port='465', smtp_encryption='ssl', smtp_username=submission_server.username, smtp_password=submission_server.password, enabled='1', current_password=OWNER)
    r = owner.post(accounts, data=data)
    sender = parse_qs(urlparse(r.url).query)['account_id'][0]
    detail = accounts + '&account_id=' + sender
    owner.post(detail, data={'csrf': token(owner.get(detail)), 'action': 'mail_accounts_grant', 'account_id': sender, 'revision': '0', 'user_id': oid, 'can_read': '0', 'can_send': '1', 'enabled': '1', 'days': '0', 'current_password': OWNER})
    sender_ref = sender + ':1'

    inbox_server.mode = 'message-reply-all'
    db('UPDATE ci_mail_message_reads SET started_epoch=?', [int(time.time()) - 7200])
    before = inbox_server.connections
    r = owner.post(message, data={'csrf': token(owner.get(message)), 'action': 'mail_message_read', **route}, timeout=30)
    check('Loaded message retains Reply Reply all and Forward controls to the author', 'data-mail-copy-controls' in r.text and 'value="mail_message_reply"' in r.text and 'value="mail_message_reply_all"' in r.text and 'value="mail_message_forward"' in r.text and 'Nothing is submitted or sent' in r.text and inbox_server.connections == before + 1)
    cooldown = uuid.uuid4().hex
    cooldown_data = {'csrf': token(owner.get(message)), 'action': 'mail_message_reply', **route, 'draft_id': cooldown, 'sender': sender_ref}
    r = owner.post(message, data=cooldown_data, timeout=30)
    check('Immediate copy after display read fails closed at the two-second source-read boundary', 'Wait 2 seconds' in r.text and inbox_server.connections == before + 1 and not db('SELECT * FROM ci_mail_reply_drafts WHERE id=?', [cooldown]))
    db('UPDATE ci_mail_message_reads SET started_epoch=?', [int(time.time()) - 7200])
    before = inbox_server.connections
    draft = uuid.uuid4().hex

    def reply_post(action, **overrides):
        data = {'csrf': token(owner.get(message)), 'action': action, **route, 'draft_id': draft, 'sender': sender_ref}
        data.update(overrides)
        return owner.post(message, data=data, timeout=30)

    r = reply_post('mail_message_reply', csrf='invalid')
    check('Reply requires a valid form token', 'session expired' in r.text.lower() and not db('SELECT * FROM ci_mail_reply_drafts') and inbox_server.connections == before)
    r = reply_post('mail_message_reply', uid='81')
    check('Reply rejects a form different from the open message', 'no longer matches' in r.text and not db('SELECT * FROM ci_mail_reply_drafts') and inbox_server.connections == before)
    r = reply_post('mail_message_reply', draft_id='forged-draft-id')
    check('Reply requires a bounded draft identity', not db('SELECT * FROM ci_mail_reply_drafts') and inbox_server.connections == before)
    r = reply_post('mail_message_reply')
    check('Reply performs one fresh authorized read and opens the editable copy', inbox_server.connections == before + 1 and 'view=mail-draft' in r.url and 'draft_id=' + draft in r.url and 'protected copied-message draft was created' in r.text)

    base = db('SELECT * FROM ci_mail_drafts WHERE id=?', [draft])[0]
    check('Reply leaves a content-free ordinary placeholder', base['status'] == 'source' and base['subject'] == 'Copied message draft' and base['body'] == '' and base['to_json'] == '[]' and base['cc_json'] == '[]' and base['bcc_json'] == '[]')
    copied = db('SELECT * FROM ci_mail_reply_drafts WHERE id=?', [draft])
    check('Protected copy binds the durable source read and reply mode', len(copied) == 1 and copied[0]['source_account_id'] == aid and copied[0]['status'] == 'draft' and copied[0]['source_read_id'] == db('SELECT id FROM ci_mail_message_reads ORDER BY started_epoch DESC, id DESC LIMIT 1')[0]['id'])
    origin = json.loads(copied[0]['origin_json'])
    check('Reply origin records source identity and thread', origin['mode'] == 'reply' and origin['source_account_id'] == aid and origin['uidvalidity'] == '1234' and origin['uid'] == '82' and origin['thread']['in_reply_to'] == '<synthetic-82@example.invalid>' and origin['thread']['references'] == ['<synthetic-82@example.invalid>'])
    content = json.loads(copied[0]['content_json'])
    check('Reply copies the structured From recipient and displayed text only', content['to'] == ['synthetic@example.net'] and content['cc'] == [] and content['bcc'] == [] and content['subject'].startswith('Re: SYNTHETIC PRIVATE MESSAGE') and 'SYNTHETIC PRIVATE BODY' in content['body'])
    check('Copied content never carries raw headers or hidden recipients', 'Message-ID' not in content['body'] and 'MIME-Version' not in content['body'])

    r = reply_post('mail_message_reply')
    check('Exact reply replay reopens the same draft without another fetch', inbox_server.connections == before + 1 and 'draft_id=' + draft in r.url and len(db('SELECT * FROM ci_mail_reply_drafts')) == 1)
    r = reply_post('mail_message_forward')
    check('Reusing a reply draft identity for forward is refused', 'different message or sender' in r.text and len(db('SELECT * FROM ci_mail_reply_drafts')) == 1 and inbox_server.connections == before + 1)

    reply_all = uuid.uuid4().hex
    db('UPDATE ci_mail_message_reads SET started_epoch=?', [int(time.time()) - 7200])
    r = owner.post(message, data={'csrf': token(owner.get(message)), 'action': 'mail_message_reply_all', **route, 'draft_id': reply_all, 'sender': sender_ref}, timeout=30)
    check('Reply all performs one fresh authorized read and opens a protected draft', inbox_server.connections == before + 2 and 'draft_id=' + reply_all in r.url)
    all_row = db('SELECT origin_json,content_json FROM ci_mail_reply_drafts WHERE id=?', [reply_all])[0]
    all_origin = json.loads(all_row['origin_json']); all_content = json.loads(all_row['content_json'])
    check('Reply all excludes the chosen sender and hidden recipients while retaining visible To and Cc',
          all_origin['mode'] == 'reply-all' and all_content['to'] == ['synthetic@example.net', 'visible@example.invalid']
          and all_content['cc'] == ['cc@example.invalid'] and all_content['bcc'] == []
          and 'reply-sender@example.invalid' not in json.dumps(all_content) and 'hidden@example.invalid' not in json.dumps(all_content))
    reply_all_state = [db('SELECT * FROM ci_mail_message_reads ORDER BY id'), db('SELECT * FROM ci_mail_message_results ORDER BY id'),
                       db('SELECT * FROM ci_mail_drafts ORDER BY id'), db('SELECT * FROM ci_mail_reply_drafts ORDER BY id'),
                       db('SELECT * FROM ci_mail_draft_revisions ORDER BY draft_id,revision'), db('SELECT * FROM ci_audit ORDER BY id')]
    r = owner.post(message, data={'csrf': token(owner.get(message)), 'action': 'mail_message_reply_all', **route, 'draft_id': reply_all, 'sender': sender_ref}, timeout=30)
    check('Exact reply all replay reopens the existing draft without another fetch or write',
          inbox_server.connections == before + 2 and 'draft_id=' + reply_all in r.url
          and 'existing copied-message draft' in r.text.lower()
          and reply_all_state == [db('SELECT * FROM ci_mail_message_reads ORDER BY id'), db('SELECT * FROM ci_mail_message_results ORDER BY id'),
                                  db('SELECT * FROM ci_mail_drafts ORDER BY id'), db('SELECT * FROM ci_mail_reply_drafts ORDER BY id'),
                                  db('SELECT * FROM ci_mail_draft_revisions ORDER BY draft_id,revision'), db('SELECT * FROM ci_audit ORDER BY id')])
    copied_file = uuid.uuid4().hex
    copied_file_bytes = b'PRIVATE_COPIED_ATTACHMENT_BYTES'
    db('INSERT INTO ci_mail_draft_files (id,slot,name,mime,bytes,sha256,data,created_at,draft_id) VALUES (?,1,?,?,?,?,UNHEX(?),UTC_TIMESTAMP(),?)', [copied_file, 'copied.txt', 'text/plain', len(copied_file_bytes), hashlib.sha256(copied_file_bytes).hexdigest(), copied_file_bytes.hex(), reply_all])
    copied_file_page = draft_url + '&draft_id=' + reply_all
    copied_file_csrf = token(owner.get(copied_file_page))
    r = owner.post(copied_file_page, data={'csrf': copied_file_csrf, 'action': 'mail_drafts_file', 'draft_id': reply_all, 'revision': '1', 'file_id': copied_file})
    check('Copied known-id attachment download succeeds under current source and sender authority', r.content == copied_file_bytes)

    page_text = owner.get(draft_url + '&draft_id=' + draft).text
    check('Copied draft editor shows content, origin and omissions panel', 'SYNTHETIC PRIVATE BODY' in page_text and 'data-mail-draft-origin' in page_text and 'replying to' in page_text and 'rechecked whenever this draft opens' in page_text)
    check('Copied draft keeps the quoted message out of the ordinary columns', base['body'] == '' and db('SELECT body FROM ci_mail_drafts WHERE id=?', [draft])[0]['body'] == '')

    forward = uuid.uuid4().hex
    data = {'csrf': token(owner.get(message)), 'action': 'mail_message_forward', **route, 'draft_id': forward, 'sender': sender_ref}
    db('UPDATE ci_mail_message_reads SET started_epoch=?', [int(time.time()) - 7200])
    r = owner.post(message, data=data, timeout=30)
    check('Forward performs one fresh authorized read and opens a recipientless draft', inbox_server.connections == before + 3 and 'draft_id=' + forward in r.url)
    forigin = json.loads(db('SELECT origin_json FROM ci_mail_reply_drafts WHERE id=?', [forward])[0]['origin_json'])
    fcontent = json.loads(db('SELECT content_json FROM ci_mail_reply_drafts WHERE id=?', [forward])[0]['content_json'])
    check('Forward starts without recipients or reply thread', forigin['mode'] == 'forward' and forigin['thread'] == {'in_reply_to': None, 'references': []} and fcontent['to'] == [] and fcontent['cc'] == [] and fcontent['bcc'] == [] and 'SYNTHETIC PRIVATE BODY' in fcontent['body'])

    f = soup(owner.get(draft_url + '&draft_id=' + forward)).select_one('[data-mail-draft-editor]')
    data = form_data(f)
    r = owner.post(urljoin(draft_url, f.get('action') or draft_url), data={**data, 'action': 'mail_drafts_save', 'to': 'forward-recipient@example.invalid'})
    check('Forward draft saves added recipients through the protected writer', 'view=mail-draft' in r.url and db('SELECT revision FROM ci_mail_reply_drafts WHERE id=?', [forward])[0]['revision'] == 2 and json.loads(db('SELECT content_json FROM ci_mail_reply_drafts WHERE id=?', [forward])[0]['content_json'])['to'] == ['forward-recipient@example.invalid'])
    check('Ordinary draft columns still hold no copied recipients', db('SELECT to_json FROM ci_mail_drafts WHERE id=?', [forward])[0]['to_json'] == '[]')

    send_page = root + '?view=mail-send&draft_id=' + draft
    r = owner.get(send_page)
    f = soup(r).select_one('[data-mail-submission-confirm]')
    check('Copied reply opens the same explicit review screen', f is not None and 'synthetic@example.net' in r.text)
    submission_server.configure('ssl', 'accepted', sender='reply-sender@example.invalid', recipients=['synthetic@example.net'])
    smtp_before = submission_server.connections
    target = urljoin(send_page, f.get('action') or send_page)
    data = form_data(f)
    r = owner.post(target, data={**data, 'confirm_send': '1'}, timeout=30)
    check('Reviewed reply send records server acceptance', 'Server accepted' in r.text and submission_server.connections == smtp_before + 1)
    sid = parse_qs(urlparse(r.url).query)['submission_id'][0]
    copy_page = root + '?view=mail-submission&submission_id=' + sid
    copy_csrf = token(owner.get(copy_page))
    owner.post(detail, data={'csrf': token(owner.get(detail)), 'action': 'mail_sent_folder_save', 'account_id': sender, 'sent_folder': 'Sent'})
    saved = db('SELECT * FROM ci_mail_submissions WHERE id=?', [sid])[0]
    mime = saved['mime_bytes'].encode('ascii')
    parsed = BytesParser(policy=policy.default).parsebytes(mime)
    check('Reply wire equals saved MIME with RFC thread headers', submission_server.attempts[-1]['data'] == mime and str(parsed['In-Reply-To']) == '<synthetic-82@example.invalid>' and str(parsed['References']) == '<synthetic-82@example.invalid>')
    check('Reply wire has no Reply-To or hidden recipients', parsed['Reply-To'] is None and 'Bcc' not in parsed.keys() and 'SYNTHETIC PRIVATE BODY' in parsed.get_content())
    r = owner.post(target, data={**data, 'confirm_send': '1'}, timeout=30)
    check('Same saved copied revision replays without another SMTP connection', parse_qs(urlparse(r.url).query).get('submission_id') == [sid] and submission_server.connections == smtp_before + 1)

    send_page = root + '?view=mail-send&draft_id=' + forward
    r = owner.get(send_page)
    f = soup(r).select_one('[data-mail-submission-confirm]')
    data = form_data(f)
    # Advance only the disposable-fixture per-account cooldown, never a production retry control.
    db('UPDATE ci_mail_submission_dispatches SET started_epoch=0')
    submission_server.configure('ssl', 'accepted', sender='reply-sender@example.invalid', recipients=['forward-recipient@example.invalid'])
    smtp_before = submission_server.connections
    r = owner.post(urljoin(send_page, f.get('action') or send_page), data={**data, 'confirm_send': '1'}, timeout=30)
    check('Reviewed forward send succeeds without reply headers', 'Server accepted' in r.text and submission_server.connections == smtp_before + 1)
    fid = parse_qs(urlparse(r.url).query)['submission_id'][0]
    parsed = BytesParser(policy=policy.default).parsebytes(db('SELECT mime_bytes FROM ci_mail_submissions WHERE id=?', [fid])[0]['mime_bytes'].encode('ascii'))
    check('Forward wire carries no In-Reply-To or References', parsed['In-Reply-To'] is None and parsed['References'] is None and 'SYNTHETIC PRIVATE BODY' in parsed.get_content())

    db('UPDATE ci_mail_account_grants SET can_read=0 WHERE account_id=? AND user_id=?', [aid, oid])
    r = owner.post(copied_file_page, data={'csrf': copied_file_csrf, 'action': 'mail_drafts_file', 'draft_id': reply_all, 'revision': '1', 'file_id': copied_file})
    check('Revoking copied source Read blocks known-id attachment bytes', copied_file_bytes not in r.content and 'Content-Disposition' not in r.headers and r.headers.get('Content-Type') != 'application/octet-stream' and 'inbox is unavailable' in r.text)
    r = owner.get(draft_url + '&draft_id=' + forward)
    check('Revoking source Read hides copied content on open', 'SYNTHETIC PRIVATE BODY' not in r.text and 'inbox is unavailable' in r.text)
    send_page = root + '?view=mail-send&draft_id=' + forward
    r = owner.get(send_page)
    check('Revoked source Read blocks copied review', 'data-mail-submission-confirm' not in r.text and 'SYNTHETIC PRIVATE BODY' not in r.text)
    smtp_before = submission_server.connections
    r = owner.post(send_page, data={'csrf': token(owner.get(send_page)), 'action': 'mail_submissions_send', 'submission_id': uuid.uuid4().hex, 'draft_id': forward, 'draft_revision': '2', 'account_revision': '1', 'confirm_send': '1'}, timeout=30)
    check('Revoked source Read blocks copied send before SMTP', submission_server.connections == smtp_before and 'inbox is unavailable' in r.text and len(db('SELECT * FROM ci_mail_submissions WHERE draft_id=?', [forward])) == 1)
    r = owner.get(root + '?view=mail-submission&submission_id=' + sid)
    check('Revoked source Read hides saved copied submission detail', 'inbox is unavailable' in r.text and 'SYNTHETIC PRIVATE' not in r.text)
    copy_before = inbox_server.connections
    r = owner.post(copy_page, data={'csrf': copy_csrf, 'action': 'mail_submissions_copy_sent', 'submission_id': sid, 'confirm_copy': '1'}, timeout=30)
    check('Revoked source Read blocks known-id direct Sent copy before attempt or IMAP', 'inbox is unavailable' in r.text and inbox_server.connections == copy_before and not db('SELECT * FROM ci_mail_copy_attempts WHERE submission_id=?', [sid]))
    r = owner.get(root + '?view=mail-submissions&account_id=' + sender)
    check('Revoked source Read masks copied subjects in submission history', 'Copied message unavailable' in r.text and 'SYNTHETIC PRIVATE' not in r.text)
    r = owner.post(root + '?view=mail-send&draft_id=' + draft, data={'csrf': token(owner.get(draft_url + '&draft_id=' + draft)), 'action': 'mail_submissions_send', 'submission_id': sid, 'draft_id': draft, 'draft_revision': '1', 'account_revision': '1', 'confirm_send': '1'}, timeout=30)
    check('Revoked source Read refuses existing-id copied replay before SMTP', submission_server.connections == smtp_before and 'inbox is unavailable' in r.text)
    db('UPDATE ci_mail_account_grants SET can_read=1 WHERE account_id=? AND user_id=?', [aid, oid])

    context = browser.new_context(viewport={'width': 1440, 'height': 1050})
    context.add_cookies([{'name': c.name, 'value': c.value, 'url': BASE + '/existing/'} for c in owner.cookies])
    page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
    page.goto(draft_url + '&draft_id=' + draft)
    expect(page.locator('[data-mail-draft-origin]')).to_contain_text('replying to')
    page.screenshot(path=str(QA / 'Mail-Reply-Draft-Desktop.png'), full_page=True)
    page.set_viewport_size({'width': 320, 'height': 800})
    check('Copied draft editor fits narrow mobile', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    page.screenshot(path=str(QA / 'Mail-Reply-Draft-Mobile.png'), full_page=True)
    check('Copied draft screens have no browser script errors', not errors)
    context.close()

    for path in ['app/mail/replies.php', 'database/schema/mail_replies_schema.php', 'database/migrations/mail_replies_migrate.php', 'includes/foundation/mail-compose/DraftOrigin.php']:
        check('Direct request to ' + path + ' is denied', requests.get(BASE + '/existing/' + path).status_code == 404)
