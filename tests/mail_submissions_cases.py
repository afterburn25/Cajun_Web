"""Actual packaged HTTP/Chromium submission against isolated MariaDB and SMTP/TLS."""
import json, uuid, hashlib
from email import policy
from email.parser import BytesParser
from urllib.parse import parse_qs, urlparse, urljoin
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['ROOT', 'WEB', 'BASE', 'QA', 'check', 'soup', 'token', 'form_data', 'submit', 'inbox_server', 'provider_server', 'submission_server']})
    db('DELETE FROM ci_rate_limits')
    root = BASE + '/existing/admin/index.php'
    library = root + '?view=mail-submissions'
    accounts = root + '?view=mail-account'
    draft_url = root + '?view=mail-draft'
    owner = requests.Session()
    submit(owner, root + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    member = requests.Session()
    submit(member, root + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    uid = db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id']
    oid = db("SELECT id FROM ci_users WHERE email='owner@example.invalid'")[0]['id']
    original = db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?', [uid])[0]['permissions']
    capture = (QA / 'mail-capture.txt').read_bytes()
    inbox_connections, provider_calls = inbox_server.connections, provider_server.calls
    check('Fresh install has ready empty submission tables', db("SELECT value FROM fresh_settings WHERE name='mail_submissions_schema_version'") == [{'value': '1'}] and all(not db('SELECT * FROM fresh_' + t) for t in ['mail_submissions', 'mail_submission_dispatches', 'mail_submission_results']))
    snapshot, jobs = db('SELECT * FROM ci_settings'), db('SELECT * FROM ci_studio_jobs')
    for changes in [{'csrf': 'invalid', 'current_password': OWNER}, {'current_password': 'wrong'}]:
        r = submit(owner, library, 'mail_submissions_setup', changes)
        check('Submission setup requires CSRF and current password ' + str(list(changes)), not db("SELECT value FROM ci_settings WHERE name='mail_submissions_schema_version'") and ('session expired' in r.text.lower() or 'current password' in r.text))
    r = submit(owner, library, 'mail_submissions_setup', {'current_password': OWNER})
    check('Owner prepares additive submission storage preserving prior records', 'Email submission storage is ready' in r.text and db('SELECT * FROM ci_studio_jobs') == jobs and all(s in db('SELECT * FROM ci_settings') for s in snapshot))
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', ['[]', uid])
    check('Staff needs global Send for submission history', member.get(library).status_code == 403)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', [json.dumps(['mail.send']), uid])
    check('Send-only staff can open mail history without website permissions', member.get(library).status_code == 200 and member.get(root + '?view=projects').status_code == 403)

    def account(encryption, imap_host='imap.example.invalid', imap_username='synthetic-incoming', imap_password='Synthetic incoming password'):
        f = soup(owner.get(accounts)).select_one('[data-mail-account-editor]')
        data = form_data(f)
        data.update(label='Synthetic SMTP ' + encryption, address='sender@example.invalid', imap_host=imap_host, imap_port='993', imap_encryption='ssl', imap_username=imap_username, imap_password=imap_password, smtp_host=submission_server.host, smtp_port='465' if encryption == 'ssl' else '587', smtp_encryption=encryption, smtp_username=submission_server.username, smtp_password=submission_server.password, enabled='1', current_password=OWNER)
        r = owner.post(accounts, data=data)
        aid = parse_qs(urlparse(r.url).query)['account_id'][0]
        for actor in [uid, oid]:
            detail = accounts + '&account_id=' + aid
            owner.post(detail, data={'csrf': token(owner.get(detail)), 'action': 'mail_accounts_grant', 'account_id': aid, 'revision': '0', 'user_id': actor, 'can_read': '0', 'can_send': '1', 'enabled': '1', 'days': '0', 'current_password': OWNER})
        return aid

    aids = {mode: account(mode) for mode in ['ssl', 'starttls']}
    body = 'SYNTHETIC_PRIVATE_SUBMISSION_BODY\n</textarea><script>window.submissionExecuted=true</script>\nCajun café ⚜'
    payload = {'to': 'first@example.invalid', 'cc': 'copy@example.invalid', 'bcc': 'private@example.invalid', 'subject': 'Synthetic SMTP review café', 'body': body}

    def fields(session, url, selector):
        r = session.get(url)
        f = soup(r).select_one(selector)
        assert f is not None, (url, r.status_code, r.text[-1500:])
        return urljoin(url, f.get('action') or url), form_data(f)

    def create(aid):
        url = draft_url + '&account_id=' + aid
        target, data = fields(member, url, '[data-mail-draft-editor]')
        r = member.post(target, data={**data, 'action': 'mail_drafts_save', **payload})
        did = parse_qs(urlparse(r.url).query)['draft_id'][0]
        return did, root + '?view=mail-send&draft_id=' + did

    def edit(did, **changes):
        target, data = fields(member, draft_url + '&draft_id=' + did, '[data-mail-draft-editor]')
        return member.post(target, data={**data, 'action': 'mail_drafts_save', **changes})

    def review(url):
        return fields(member, url, '[data-mail-submission-confirm]')

    def age_dispatches():
        # Advance only disposable-fixture cooldown timestamps, never a production retry control.
        db('UPDATE ci_mail_submission_dispatches SET started_epoch=0')

    did, url = create(aids['ssl'])
    before = submission_server.connections
    target, data = review(url)
    r = member.get(url)
    confirm = soup(r).select_one('[name=confirm_send]')
    check('Review requires unchecked confirmation and displays private Bcc separately', confirm.has_attr('required') and not confirm.has_attr('checked') and soup(r).select_one('[aria-label="Blind copy recipients"]').get_text().find('private@example.invalid') >= 0)
    check('Private review sends no cache or referral information', 'no-store' in r.headers.get('Cache-Control', '') and r.headers.get('Referrer-Policy') == 'no-referrer')
    check('Review is escaped and passive with no intent or SMTP connection', not soup(r).select_one('[data-mail-submission-body] script') and not db('SELECT * FROM ci_mail_submissions') and submission_server.connections == before)
    check('Saved drafts and mailbox hub expose review and private history', 'view=mail-send' in member.get(draft_url + '&draft_id=' + did).text and 'view=mail-submissions' in member.get(root + '?view=mailboxes').text)
    for label, changes in [('CSRF', {'csrf': 'bad', 'confirm_send': '1'}), ('confirmation', {}), ('zero revision', {'confirm_send': '1', 'draft_revision': '0'}), ('malformed identity', {'confirm_send': '1', 'submission_id': 'bad'})]:
        r = member.post(target, data={**data, **changes})
        check('Send rejects invalid ' + label + ' before persistence or SMTP', not db('SELECT * FROM ci_mail_submissions') and submission_server.connections == before and 'Server accepted' not in r.text)
    old = dict(data)
    edit(did, subject='Changed after review')
    r = member.post(target, data={**old, 'confirm_send': '1'})
    check('Stale reviewed draft cannot send newer content', not db('SELECT * FROM ci_mail_submissions') and soup(r).select_one('[data-mail-submission-confirm]') is None and submission_server.connections == before and 'changed' in r.text)
    edit(did, subject=payload['subject'])
    target, data = review(url)
    for mode in ['grant', 'account', 'global', 'account revision']:
        if mode == 'grant': db('UPDATE ci_mail_account_grants SET enabled=0 WHERE account_id=? AND user_id=?', [aids['ssl'], uid])
        elif mode == 'account': db('UPDATE ci_mail_accounts SET enabled=0 WHERE id=?', [aids['ssl']])
        elif mode == 'global': db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', ['[]', uid])
        else: db('UPDATE ci_mail_accounts SET revision=2 WHERE id=?', [aids['ssl']])
        r = member.post(target, data={**data, 'confirm_send': '1'})
        check('Send rechecks current ' + mode + ' before SMTP', not db('SELECT * FROM ci_mail_submissions') and submission_server.connections == before and soup(r).select_one('[data-mail-submission-confirm]') is None)
        db('UPDATE ci_mail_account_grants SET enabled=1 WHERE account_id=? AND user_id=?', [aids['ssl'], uid])
        db('UPDATE ci_mail_accounts SET enabled=1,revision=1 WHERE id=?', [aids['ssl']])
        db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', [json.dumps(['mail.send']), uid])
    r = owner.post(target, data={**data, 'csrf': token(owner.get(library)), 'confirm_send': '1'})
    check('Owner cannot send another authors reviewed draft', not db('SELECT * FROM ci_mail_submissions') and submission_server.connections == before and body not in r.text)
    r = member.post(target + '&support=fixture', data={**data, 'confirm_send': '1'})
    check('Support context cannot send staff mail', r.status_code == 403 and not db('SELECT * FROM ci_mail_submissions') and submission_server.connections == before)

    # The actual browser submits the SSL message after keyboard confirmation.
    submission_server.configure('ssl', 'accepted')
    context = browser.new_context(viewport={'width': 1440, 'height': 1050})
    context.add_cookies([{'name': c.name, 'value': c.value, 'url': BASE + '/existing/'} for c in member.cookies])
    page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
    page.goto(url)
    form = page.locator('[data-mail-submission-confirm]')
    expect(form.locator('[name=confirm_send]')).not_to_be_checked()
    check('Actual review browser cannot execute hostile message content', page.evaluate('window.submissionExecuted!==true') and page.locator('[data-mail-submission-body] script').count() == 0)
    page.screenshot(path=str(QA / 'Mail-Send-Desktop.png'), full_page=True)
    page.set_viewport_size({'width': 320, 'height': 800})
    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
    page.evaluate("window.scrollTo({top:0,left:0,behavior:'instant'})")
    expect(page.locator('.sidebar-toggle')).to_be_in_viewport()
    check('Send review fits narrow mobile with closed navigation', page.evaluate('document.documentElement.scrollWidth <= innerWidth') and page.locator('.admin-sidebar').evaluate('(node)=>node.getBoundingClientRect().right<=0'))
    page.screenshot(path=str(QA / 'Mail-Send-Mobile.png'), full_page=True)
    form.locator('[name=confirm_send]').focus(); page.keyboard.press('Space'); page.keyboard.press('Tab')
    check('Keyboard confirmation leads to the explicit Send button', form.locator('[name=confirm_send]').is_checked() and form.get_by_role('button', name='Send email', exact=True).evaluate('(node)=>node===document.activeElement'))
    page.keyboard.press('Enter')
    expect(page.locator('[role=status]')).to_contain_text('Server accepted')
    sid = parse_qs(urlparse(page.url).query)['submission_id'][0]
    check('Actual browser Send records server responsibility without claiming delivery', 'does not confirm recipient delivery' in page.locator('[role=status]').inner_text() and submission_server.connections == before + 1)
    page.screenshot(path=str(QA / 'Mail-Submission-Mobile.png'), full_page=True)
    check('Submission screens have no browser script errors', not errors)
    context.close()

    saved = db('SELECT * FROM ci_mail_submissions WHERE id=?', [sid])[0]
    mime = saved['mime_bytes'].encode('ascii')
    transmitted = submission_server.attempts[-1]
    parsed = BytesParser(policy=policy.default).parsebytes(mime)
    check('HTTP transmission equals immutable saved MIME and hash', transmitted['data'] == mime and hashlib.sha256(mime).hexdigest() == saved['mime_sha256'])
    check('HTTP sends all three recipients while keeping Bcc off wire', transmitted['recipients'] == ['first@example.invalid', 'copy@example.invalid', 'private@example.invalid'] and parsed['Bcc'] is None and 'private@example.invalid' not in mime.decode())
    check('HTTP sends exact saved plaintext and subject', parsed.get_content().replace('\r\n', '\n').rstrip('\n') == body and str(parsed['Subject']) == payload['subject'])
    check('HTTP success has exactly one dispatch and accepted result', len(db('SELECT * FROM ci_mail_submission_dispatches WHERE submission_id=?', [sid])) == 1 and db('SELECT status FROM ci_mail_submission_results WHERE submission_id=?', [sid]) == [{'status': 'accepted'}])
    for identity in [sid, uuid.uuid4().hex]:
        r = member.post(target, data={**data, 'submission_id': identity, 'confirm_send': '1', 'body': 'forged body', 'smtp_host': '127.0.0.1'})
        check('Same saved revision replay returns existing history ' + ('stable' if identity == sid else 'new form'), parse_qs(urlparse(r.url).query).get('submission_id') == [sid] and submission_server.connections == before + 1 and len(db('SELECT * FROM ci_mail_submissions WHERE draft_id=?', [did])) == 1)
    history = root + '?view=mail-submission&submission_id=' + sid
    for viewer, label in [(owner, 'different author'), (requests.Session(), 'anonymous')]:
        r = viewer.get(history)
        check('Submission content stays private from ' + label, 'SYNTHETIC_PRIVATE_SUBMISSION_BODY' not in r.text and 'private@example.invalid' not in r.text)
    for mode in ['grant', 'global']:
        if mode == 'grant': db('UPDATE ci_mail_account_grants SET enabled=0 WHERE account_id=? AND user_id=?', [aids['ssl'], uid])
        else: db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', ['[]', uid])
        r = member.get(history)
        check('History immediately hides private content after ' + mode + ' loss', 'SYNTHETIC_PRIVATE_SUBMISSION_BODY' not in r.text and 'private@example.invalid' not in r.text)
        db('UPDATE ci_mail_account_grants SET enabled=1 WHERE account_id=? AND user_id=?', [aids['ssl'], uid])
        db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', [json.dumps(['mail.send']), uid])
    edit(did, subject='Edited after acceptance')
    check('Editing accepted draft never exposes another Send control', soup(member.get(url)).select_one('[data-mail-submission-confirm]') is None and 'Do not resend' in member.get(url).text and submission_server.connections == before + 1)

    for encryption, mode, status, title in [('starttls', 'accepted', 'accepted', 'Server accepted'), ('ssl', 'recipient', 'not_submitted', 'Not submitted'), ('ssl', 'final550', 'rejected', 'Server rejected'), ('ssl', 'disconnect_final', 'unknown', 'Confirmation missing')]:
        age_dispatches(); submission_server.configure(encryption, mode)
        case_id, case_url = create(aids[encryption]); case_target, case_data = review(case_url)
        count = submission_server.connections
        r = member.post(case_target, data={**case_data, 'confirm_send': '1', 'body': 'forged body'})
        case_sid = parse_qs(urlparse(r.url).query)['submission_id'][0]
        check('HTTP records real SMTP ' + encryption + ' ' + mode, title in r.text and db('SELECT status FROM ci_mail_submission_results WHERE submission_id=?', [case_sid]) == [{'status': status}] and submission_server.connections == count + 1)
        member.post(case_target, data={**case_data, 'confirm_send': '1'})
        check('HTTP never automatically repeats ' + mode, submission_server.connections == count + 1 and soup(member.get(case_url)).select_one('[data-mail-submission-confirm]') is None)
        if mode == 'recipient':
            check('HTTP recipient refusal transmits no DATA', 'DATA' not in submission_server.attempts[-1]['commands'] and submission_server.attempts[-1]['data'] == b'')
        if mode == 'disconnect_final':
            check('HTTP missing confirmation warns without asserting failure', 'Do not resend' in r.text and submission_server.attempts[-1]['terminator'])
            edit(case_id, subject='Changed uncertain message')
            check('Editing uncertain draft cannot repeat transmission', soup(member.get(case_url)).select_one('[data-mail-submission-confirm]') is None)
        if mode == 'final550':
            edit(case_id, subject='Reviewed correction after known rejection')
            check('Known rejected draft can prepare a deliberately edited revision', soup(member.get(case_url)).select_one('[data-mail-submission-confirm]') is not None)

    # Acceptance followed by strict audit failure must retain an unresolved fence.
    age_dispatches(); submission_server.configure('ssl', 'accepted')
    lost_id, lost_url = create(aids['ssl']); lost_target, lost_data = review(lost_url)
    db("CREATE TRIGGER ci_submission_http_audit BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action='mail_submissions.finished' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic submission result audit failure'; END IF; END")
    count = submission_server.connections
    try: r = member.post(lost_target, data={**lost_data, 'confirm_send': '1'})
    finally: db('DROP TRIGGER ci_submission_http_audit')
    lost_sid = db('SELECT id FROM ci_mail_submissions WHERE draft_id=?', [lost_id])[0]['id']
    r = member.get(root + '?view=mail-submission&submission_id=' + lost_sid)
    check('HTTP accepted mail with failed result persistence remains unresolved', 'Submission unresolved' in r.text and 'Do not resend' in r.text and submission_server.connections == count + 1 and submission_server.attempts[-1]['terminator'] and not db('SELECT * FROM ci_mail_submission_results WHERE submission_id=?', [lost_sid]) and len(db('SELECT * FROM ci_mail_submission_dispatches WHERE submission_id=?', [lost_sid])) == 1)
    member.post(lost_target, data={**lost_data, 'confirm_send': '1'})
    check('HTTP unresolved result cannot be retried by replay', submission_server.connections == count + 1 and soup(member.get(lost_url)).select_one('[data-mail-submission-confirm]') is None)

    # An accepted submission can save one exact copy into a configured server Sent folder.
    copy_connections = 0
    sent_aid = account('ssl', 'inbox-fixture.example.net', 'synthetic@example.net', 'Synthetic receiving HTTP password')
    sent_detail = accounts + '&account_id=' + sent_aid
    sent_did, sent_url = create(sent_aid)
    sent_target, sent_data = review(sent_url)
    submission_server.configure('ssl', 'accepted')
    r = member.post(sent_target, data={**sent_data, 'confirm_send': '1'})
    sent_sid = parse_qs(urlparse(r.url).query)['submission_id'][0]
    copy_page = root + '?view=mail-submission&submission_id=' + sent_sid
    check('Acceptance never appends a Sent copy silently', inbox_server.connections == inbox_connections and soup(member.get(copy_page)).select_one('[data-mail-sent-copy]') is None)

    def copy_post():
        t = token(member.get(copy_page))
        return member.post(copy_page, data={'csrf': t, 'action': 'mail_submissions_copy_sent', 'submission_id': sent_sid})

    r = owner.post(sent_detail, data={'csrf': token(owner.get(sent_detail)), 'action': 'mail_accounts_sent_setup', 'account_id': sent_aid, 'current_password': OWNER})
    check('Owner prepares additive Sent-copy storage', 'Sent-copy storage is ready' in r.text and db("SELECT value FROM ci_settings WHERE name='mail_copies_schema_version'") == [{'value': '1'}])
    r = copy_post()
    check('Copy refuses before an owner configures a Sent folder', 'Sent folder' in r.text and inbox_server.connections == inbox_connections and not db('SELECT * FROM ci_mail_copy_attempts'))
    for bad in ['Sent*', 'Sent"Folder', 'INBOX\\Sent']:
        r = owner.post(sent_detail, data={'csrf': token(owner.get(sent_detail)), 'action': 'mail_accounts_sent_folder', 'account_id': sent_aid, 'sent_folder': bad, 'current_password': OWNER})
        check('Wire-unsafe Sent folder refused over HTTP ' + bad, not db('SELECT * FROM ci_mail_sent_folders WHERE id=?', [sent_aid]) and 'plain server folder' in r.text)
    r = owner.post(sent_detail, data={'csrf': token(owner.get(sent_detail)), 'action': 'mail_accounts_sent_folder', 'account_id': sent_aid, 'sent_folder': 'Sent', 'current_password': OWNER})
    check('Owner saves the Sent folder setting', db('SELECT sent_folder FROM ci_mail_sent_folders WHERE id=?', [sent_aid]) == [{'sent_folder': 'Sent'}])
    check('Copy form appears only for accepted mail with a folder', soup(member.get(copy_page)).select_one('[data-mail-sent-copy]') is not None)
    inbox_server.mode = 'append'
    r = copy_post()
    copy_connections += 1
    sent_mime = db('SELECT mime_bytes FROM ci_mail_submissions WHERE id=?', [sent_sid])[0]['mime_bytes'].encode('ascii')
    check('HTTP copy appends the exact accepted MIME once', inbox_server.appended == sent_mime and 'copy was saved in the server Sent folder' in r.text and db('SELECT outcome,result_code FROM ci_mail_copy_results') == [{'outcome': 'success', 'result_code': 'appended'}])
    r = copy_post()
    check('HTTP cannot append a recorded Sent copy twice', 'already has a recorded Sent copy' in r.text and inbox_server.connections == inbox_connections + copy_connections)
    r = owner.post(copy_page, data={'csrf': token(owner.get(sent_detail)), 'action': 'mail_submissions_copy_sent', 'submission_id': sent_sid})
    check('Another author cannot drive a Sent copy', 'copy was saved' not in r.text and inbox_server.connections == inbox_connections + copy_connections)
    inbox_server.mode = 'append-refused'
    refused_did, refused_url = create(sent_aid)
    refused_target, refused_data = review(refused_url)
    r = member.post(refused_target, data={**refused_data, 'confirm_send': '1'})
    refused_sid = parse_qs(urlparse(r.url).query)['submission_id'][0]
    refused_page = root + '?view=mail-submission&submission_id=' + refused_sid
    # The two-second per-account copy bound counts the earlier successful append;
    # age the attempts so the refused copy reaches the transport deterministically.
    db('UPDATE ci_mail_copy_attempts SET started_epoch=0')
    r = member.post(refused_page, data={'csrf': token(member.get(refused_page)), 'action': 'mail_submissions_copy_sent', 'submission_id': refused_sid})
    copy_connections += 1
    refused_mime = db('SELECT mime_bytes FROM ci_mail_submissions WHERE id=?', [refused_sid])[0]['mime_bytes'].encode('ascii')
    check('Server folder refusal records a bounded failure without copy claims', 'did not accept the configured Sent folder' in r.text and db('SELECT result_code FROM ci_mail_copy_results WHERE id=(SELECT id FROM ci_mail_copy_attempts WHERE submission_id=?)', [refused_sid]) == [{'result_code': 'folder'}] and inbox_server.appended == refused_mime)
    inbox_server.mode = 'ok'
    db('UPDATE ci_mail_copy_attempts SET started_epoch=0')

    # Bounded private draft attachments: multipart upload, exact download,
    # revision binding and byte-exact send/Sent-copy of the larger MIME.
    drafts_page = root + '?view=mail-drafts'
    r = owner.post(drafts_page, data={'csrf': token(owner.get(drafts_page)), 'action': 'mail_drafts_files_setup', 'current_password': OWNER})
    check('Owner prepares additive attachment storage', 'attachments are ready' in r.text and db("SELECT value FROM ci_settings WHERE name='mail_files_schema_version'") == [{'value': '1'}])
    attach_did, attach_url = create(sent_aid)
    attach_page = draft_url + '&draft_id=' + attach_did
    attach_bytes = b'SYNTHETIC_ATTACHMENT_BYTES\x00\xff' + bytes(range(256)) * 400
    def file_post(action, revision, **extra):
        data = {'csrf': token(member.get(attach_page)), 'action': action, 'draft_id': attach_did, 'revision': str(revision), **extra}
        files = {'file': ('report.pdf', attach_bytes, 'application/pdf')} if action == 'mail_drafts_attach' else None
        return member.post(attach_page, data=data, files=files)
    r = file_post('mail_drafts_attach', 2)
    check('Stale revision cannot attach over HTTP', 'changed' in r.text.lower() and not db('SELECT * FROM ci_mail_draft_files'))
    r = file_post('mail_drafts_attach', 1)
    check('Multipart upload attaches exact bytes to the saved draft', 'File attached' in r.text and db('SELECT name,mime,LENGTH(data) FROM ci_mail_draft_files WHERE draft_id=?', [attach_did]) == [{'name': 'report.pdf', 'mime': 'application/pdf', 'LENGTH(data)': len(attach_bytes)}])
    check('Attachment card lists stored metadata', 'report.pdf' in member.get(attach_page).text)
    file_id = db('SELECT id FROM ci_mail_draft_files WHERE draft_id=?', [attach_did])[0]['id']
    r = file_post('mail_drafts_file', 2, file_id=file_id)
    check('Attachment download streams exact bytes with safe headers', r.content == attach_bytes and r.headers.get('Content-Type') == 'application/octet-stream' and r.headers.get('X-Content-Type-Options') == 'nosniff' and 'no-store' in r.headers.get('Cache-Control', ''))
    r = owner.post(attach_page, data={'csrf': token(owner.get(attach_page)), 'action': 'mail_drafts_file', 'draft_id': attach_did, 'revision': '2', 'file_id': file_id})
    check('Another author cannot download draft attachments', r.content != attach_bytes)
    r = file_post('mail_drafts_detach', 1, file_id=file_id)
    check('Stale revision cannot detach over HTTP', 'changed' in r.text.lower() and db('SELECT COUNT(*) AS n FROM ci_mail_draft_files WHERE draft_id=?', [attach_did])[0]['n'] == 1)
    r = member.get(attach_url)
    check('Send review displays the attachment before sending', 'report.pdf' in r.text and soup(r).select_one('[data-mail-submission-files]') is not None)
    age_dispatches()
    attach_target, attach_data = review(attach_url)
    count = submission_server.connections
    r = member.post(attach_target, data={**attach_data, 'confirm_send': '1'})
    attach_sid = parse_qs(urlparse(r.url).query)['submission_id'][0]
    wire = submission_server.attempts[-1]
    parts = BytesParser(policy=policy.default).parsebytes(wire['data'])
    decoded = [p.get_payload(decode=True) for p in parts.walk() if p.get_content_disposition() == 'attachment']
    check('HTTP sends attachment-bearing MIME byte-exactly', wire['data'] == db('SELECT mime_bytes FROM ci_mail_submissions WHERE id=?', [attach_sid])[0]['mime_bytes'].encode('ascii') and decoded == [attach_bytes] and submission_server.connections == count + 1)
    check('Submission record shows the saved attachment', 'report.pdf' in member.get(root + '?view=mail-submission&submission_id=' + attach_sid).text)
    r = member.post(root + '?view=mail-submission&submission_id=' + attach_sid, data={'csrf': token(member.get(copy_page)), 'action': 'mail_submissions_copy_sent', 'submission_id': attach_sid})
    copy_connections += 1
    check('Sent copy carries the larger attachment MIME byte-exactly', inbox_server.appended == wire['data'] and db('SELECT result_code FROM ci_mail_copy_results WHERE id=(SELECT id FROM ci_mail_copy_attempts WHERE submission_id=?)', [attach_sid]) == [{'result_code': 'appended'}])
    r = file_post('mail_drafts_detach', db('SELECT revision FROM ci_mail_drafts WHERE id=?', [attach_did])[0]['revision'], file_id=file_id)
    check('Author removes the saved attachment', 'Attachment removed' in r.text and not db('SELECT * FROM ci_mail_draft_files WHERE draft_id=?', [attach_did]))

    metadata = json.dumps(db("SELECT * FROM ci_audit WHERE action LIKE 'mail_submissions.%'"))
    check('Submission audits exclude message content recipients and credentials', all(x not in metadata for x in ['SYNTHETIC_PRIVATE_SUBMISSION_BODY', 'private@example.invalid', payload['subject'], submission_server.password]))
    for path in ['app/mail/submissions.php', 'app/mail/submission_dispatch.php', 'app/mail/submission_views.php', 'admin/controllers/mail_submissions.php', 'admin/views/mail_submissions.php', 'database/schema/mail_submissions_schema.php', 'database/migrations/mail_submissions_migrate.php', 'includes/foundation/mail-submission/SubmissionChannel.php']:
        check('HTTP denies direct submission internal ' + path, requests.get(BASE + '/existing/' + path).status_code == 404)
    check('Submission workflows leave IMAP provider and system-mail traffic unchanged', inbox_server.connections == inbox_connections + copy_connections and not inbox_server.errors and provider_server.calls == provider_calls and (QA / 'mail-capture.txt').read_bytes() == capture)
    check('Isolated submission SMTP server observed only expected protocol', not submission_server.errors)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?', [original, uid])
