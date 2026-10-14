"""Actual published1.22 records through update/recovery; synthetic persisted results only."""
from bs4 import BeautifulSoup


def run(env):
    url, label, check = (env[k] for k in ['url', 'label', 'check'])
    session, records, action = (env[k] for k in ['session', 'records', 'action'])
    private, password, token = (env[k] for k in ['private', 'password', 'token'])
    saved, drafts = env['prior_submissions'], env['prior_drafts']
    library = url + '/admin/index.php?view=mail-submissions'

    def retained():
        return (records('submissions') == saved and records('drafts') == drafts
                and env['prior_retained']() and records('provider') == env['restored']
                and (private / 'mail-master.key').read_bytes() == env['prior_key']
                and (private / 'provider-master.key').read_bytes() == env['provider_key'])

    def history(stage):
        r = session.get(library)
        check(label + ' ' + stage + ' keeps original accepted unknown unresolved abandoned history',
              r.status_code == 200 and all(text in r.text for text in
              ['Server accepted', 'Confirmation missing', 'Submission unresolved', 'Preparation incomplete'])
              and 'Prepare email submissions' not in r.text
              and 'SYNTHETIC_UPGRADE_PRIVATE_BODY' not in r.text and retained())
        for row in saved['intents']:
            target = url + '/admin/index.php?view=mail-send&draft_id=' + row['draft_id']
            response = session.get(target)
            check(label + ' ' + stage + ' original intent has no resend control ' + row['id'],
                  BeautifulSoup(response.text, 'html.parser').select_one('[data-mail-submission-confirm]') is None
                  and 'View the existing submission' in response.text and retained())
            response = session.post(target, data={
                'csrf': token(session.get(library)), 'action': 'mail_submissions_send',
                'submission_id': row['id'], 'draft_id': row['draft_id'],
                'draft_revision': row['draft_revision'], 'account_revision': row['account_revision'],
                'confirm_send': '1'})
            check(label + ' ' + stage + ' explicit original request replay creates no new send ' + row['id'],
                  response.status_code == 200 and 'mail-submission&' in response.url and retained())

    check(label + ' original1.22 nonempty submission records survive initial update recovery and reapplication',
          len(saved['intents']) == 4 and len(saved['dispatches']) == 3
          and sorted(x['status'] for x in saved['results']) == ['accepted', 'unknown']
          and len(drafts['drafts']) == 6 and retained())
    history('updated')
    r = action('recover')
    check(label + ' file recovery retains original1.22 submission fences bodies and keys',
          '<h2>Rolled-back</h2>' in r.text and retained())
    history('recovered1.22')
    r = action('apply')
    check(label + ' reapplication retains original1.22 submission fences bodies and keys',
          '<h2>Complete</h2>' in r.text and retained())
    history('reapplied')
    account = env['prior_accounts']['accounts'][0]
    mail = url + '/admin/index.php?view=mail-account&account_id=' + account['id']
    r = session.post(mail, data={
        'csrf': token(session.get(mail)), 'action': 'mail_accounts_save',
        'account_id': account['id'], 'revision': account['revision'], 'current_password': password,
        **{k: account[k] for k in ['label', 'address', 'imap_host', 'imap_port', 'imap_encryption',
                                  'imap_username', 'smtp_host', 'smtp_port', 'smtp_encryption',
                                  'smtp_username', 'enabled']}, 'imap_password': '', 'smtp_password': ''})
    check(label + ' original1.22 mail key still decrypts without replacing submission history',
          'Email settings saved' in r.text
          and records('mail-accounts')['accounts'][0]['revision'] == account['revision'] + 1
          and records('submissions') == saved and records('drafts') == drafts
          and (private / 'mail-master.key').read_bytes() == env['prior_key']
          and (private / 'provider-master.key').read_bytes() == env['provider_key'])
