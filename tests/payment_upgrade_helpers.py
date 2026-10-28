"""Explicit owner preparation of retained payment schema before later upgrades."""
import json


def prepare_retained_payments(env, session, admin_url, prefix, before, settings, preserved):
    """Preserve original values while accepting only schema1's link-column addition."""
    if prefix not in {'trialup_', 'notifyup_', 'ledgerup_', 'deliveryup_', 'activityup_', 'portfolioup_'}:
        raise ValueError('Unexpected retained payment fixture')
    check, db, submit = env['check'], env['db'], env['submit']
    password = 'Synthetic upgrade passphrase 2026'
    label = prefix.rstrip('_') + ' payment prerequisite'
    original_settings = {row['name']: row['value'] for row in settings}
    check(label + ' starts at retained schema1',
          original_settings.get('payments_schema_version') == '1'
          and all('link_hash' not in row and 'link_secret' not in row
                  for row in before['payment_requests']))

    def rows():
        return {table: db('SELECT * FROM ' + prefix + table) for table in before}

    def current_settings():
        return {row['name']: row['value'] for row in db('SELECT * FROM ' + prefix + 'settings')}

    def original_state():
        return (rows() == before and current_settings() == original_settings
                and all(path.read_bytes() == value for path, value in preserved.items()))

    target = admin_url + '?view=payments'
    denied = submit(session, target, 'payments_migrate', {'owner_password': 'wrong', 'confirm': '1'})
    check(label + ' refuses wrong owner without retained mutation',
          denied.status_code == 200 and 'current password' in denied.text.lower() and original_state())
    denied = submit(session, target, 'payments_migrate',
                    {'owner_password': password, 'confirm': '1', 'csrf': 'invalid'})
    check(label + ' refuses invalid CSRF without retained mutation',
          'session expired' in denied.text.lower() and original_state())
    prepared = submit(session, target, 'payments_migrate', {'owner_password': password, 'confirm': '1'})
    check(label + ' uses explicit owner preparation with checkout disabled',
          prepared.status_code == 200 and 'Payment preparation enabled' in prepared.text
          and 'Client checkout remains disabled' in prepared.text)

    after = rows()
    def ordered(values):
        return sorted(json.dumps(row, sort_keys=True) for row in values)

    for table, original in before.items():
        if table == 'payment_requests':
            additions = {'link_hash', 'link_secret'}
            retained = [{key: value for key, value in row.items() if key not in additions}
                        for row in after[table]]
            intact = (ordered(retained) == ordered(original)
                      and all(all(key in row and row[key] is None for key in additions)
                              for row in after[table]))
        elif table == 'settings':
            expected = [dict(row, value='2') if row['name'] == 'payments_schema_version' else row
                        for row in original]
            intact = ordered(after[table]) == ordered(expected)
        else:
            intact = ordered(after[table]) == ordered(original)
        check(label + ' preserves original ' + table + ' values', intact)

    expected_settings = dict(original_settings, payments_schema_version='2')
    check(label + ' changes only the payment schema marker', current_settings() == expected_settings)
    check(label + ' preserves config locks private files and master key',
          all(path.read_bytes() == value for path, value in preserved.items()))
    # Following component migrations compare against this verified additive state.
    return after, db('SELECT * FROM ' + prefix + 'settings')
