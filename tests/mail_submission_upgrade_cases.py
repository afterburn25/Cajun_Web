"""Actual 1.21 Apache upgrade/recovery; synthetic submission results, no SMTP network."""
from bs4 import BeautifulSoup


def run(env):
    globals().update({k: env[k] for k in ['url', 'label', 'check', 'session', 'records', 'action', 'private', 'password', 'token', 'prior_drafts', 'prior_accounts', 'prior_key', 'prior_retained', 'restored', 'provider_key']})
    check(label + ' original 1.21 edited and discarded drafts survive update and recovery', records('drafts') == prior_drafts and len(prior_drafts['drafts']) == 2 and len(prior_drafts['history']) == 4 and sorted(x['status'] for x in prior_drafts['drafts']) == ['discarded', 'draft'] and prior_retained())
    draft = next(x for x in prior_drafts['drafts'] if x['status'] == 'draft')
    detail = url + '/admin/index.php?view=mail-draft&draft_id=' + draft['id']
    drafts_library = url + '/admin/index.php?view=mail-drafts'
    library = url + '/admin/index.php?view=mail-submissions'
    r = session.get(detail)
    check(label + ' 1.21 draft storage requires owner preparation for the formatted column', 'Prepare local drafts' in r.text and BeautifulSoup(r.text, 'html.parser').select_one('[data-mail-draft-editor]') is None)
    r = session.post(drafts_library, data={'csrf': token(session.get(drafts_library)), 'action': 'mail_drafts_setup', 'current_password': 'Wrong synthetic password'})
    check(label + ' draft upgrade setup refuses wrong password', 'current password' in r.text)
    r = session.post(drafts_library, data={'csrf': token(session.get(drafts_library)), 'action': 'mail_drafts_setup', 'current_password': password})
    expected = [{**row, 'body_html': None} for row in prior_drafts['drafts']]
    check(label + ' additive draft upgrade preserves every prior draft and revision row', 'Local email drafts are ready' in r.text and records('drafts')['drafts'] == expected and records('drafts')['history'] == prior_drafts['history'] and prior_retained())
    r = session.get(detail)
    check(label + ' original draft remains editable before submission setup', 'Original 1.21 edited text' in r.text and BeautifulSoup(r.text, 'html.parser').select_one('[data-mail-draft-editor]') is not None)
    r = session.get(library)
    check(label + ' 1.21 owner explicitly prepares additive submission storage', 'Prepare email submissions' in r.text)
    r = session.post(library, data={'csrf': token(r), 'action': 'mail_submissions_setup', 'current_password': 'Wrong synthetic password'})
    check(label + ' submission setup refuses wrong password', 'current password' in r.text and 'Prepare email submissions' in r.text)
    r = session.post(library, data={'csrf': token(r), 'action': 'mail_submissions_setup', 'current_password': password})
    check(label + ' submission setup is empty additive and keeps prior private records', 'Email submission storage is ready' in r.text and records('submissions') == {'intents': [], 'dispatches': [], 'results': []} and records('drafts')['drafts'] == expected and records('drafts')['history'] == prior_drafts['history'] and prior_retained() and records('provider') == restored)
    records('submissions-seed')
    saved = records('submissions'); drafts = records('drafts')
    check(label + ' synthetic accepted uncertain unresolved and abandoned records exist', len(saved['intents']) == 4 and len(saved['dispatches']) == 3 and sorted(x['status'] for x in saved['results']) == ['accepted', 'unknown'])
    def retained():
        return records('submissions') == saved and records('drafts') == drafts and prior_retained() and records('provider') == restored and (private / 'mail-master.key').read_bytes() == prior_key and (private / 'provider-master.key').read_bytes() == provider_key
    r = action('recover')
    check(label + ' restoring actual 1.21 files retains submission fences content and keys', '<h2>Rolled-back</h2>' in r.text and retained() and 'Original 1.21 edited text' in session.get(detail).text)
    r = action('apply')
    check(label + ' reapplication retains exact submission records and both private keys', '<h2>Complete</h2>' in r.text and retained())
    r = session.get(library)
    check(label + ' retained history shows honest synthetic acceptance uncertainty and interruption', all(text in r.text for text in ['Server accepted', 'Confirmation missing', 'Submission unresolved', 'Preparation incomplete']) and 'SYNTHETIC_UPGRADE_PRIVATE_BODY' not in r.text)
    for row in saved['intents']:
        target = url + '/admin/index.php?view=mail-send&draft_id=' + row['draft_id']
        response = session.get(target)
        check(label + ' recovered existing intent cannot expose resend ' + row['id'], BeautifulSoup(response.text, 'html.parser').select_one('[data-mail-submission-confirm]') is None and 'View the existing submission' in response.text and retained())
    account = prior_accounts['accounts'][0]
    mail = url + '/admin/index.php?view=mail-account&account_id=' + account['id']
    r = session.post(mail, data={'csrf': token(session.get(mail)), 'action': 'mail_accounts_save', 'account_id': account['id'], 'revision': account['revision'], 'current_password': password, **{k: account[k] for k in ['label', 'address', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'enabled']}, 'imap_password': '', 'smtp_password': ''})
    check(label + ' original mail key decrypts after submission recovery without replacing history', 'Email settings saved' in r.text and records('mail-accounts')['accounts'][0]['revision'] == account['revision'] + 1 and records('submissions') == saved and records('drafts') == drafts and (private / 'mail-master.key').read_bytes() == prior_key and (private / 'provider-master.key').read_bytes() == provider_key)
