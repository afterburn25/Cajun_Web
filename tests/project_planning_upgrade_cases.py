"""Latest-published populated upgrade/recovery retains plans and original requests."""
import json
from bs4 import BeautifulSoup
from release_version import RECOVERY


def run(env):
    url, label, check = (env[k] for k in ['url', 'label', 'check'])
    owner, client, records, action = (env[k] for k in ['session', 'client', 'records', 'action'])
    fields, submit, password, private = (env[k] for k in ['fields', 'submit', 'password', 'private'])
    admin, portal = url+'/admin/index.php', url+'/portal/index.php'
    setup, editor, target = admin+'?view=studio-upgrade', admin+'?view=studio-job&id=1', portal+'?job=1'
    original = records(); keys = {name: (private/name).read_bytes() for name in ['mail-master.key', 'provider-master.key', 'retained-customer.php']}
    check(label+' upgraded planning requires explicit owner setup', 'Enable project planning' in owner.get(setup).text)
    response = submit(owner, setup, 'project_planning_setup', {'password': password, 'confirm': '1'})
    check(label+' additive planning setup preserves existing project without inventing a plan', 'Structured project plans and dated snapshots are enabled' in response.text and not records('planning')['studio_plan_events'] and records()['studio_jobs'] == original['studio_jobs'])
    milestones = [{'id': 'f'*32, 'title': 'Retained content milestone', 'details': 'Approved project copy', 'state': 'planned', 'owner': 'client', 'due_date': '2028-03-15', 'depends_on': []}]
    first = dict(fields(owner, editor, 'project_planning_save'), scope='Retained structured project scope', assigned_contact='Retained studio contact', target_date='2028-04-01', client_action='Supply content', blockers='', milestones_json=json.dumps(milestones, separators=(',', ':')))
    response = owner.post(editor, data=first)
    check(label+' first upgraded plan retains exact event request and independent current plan', len(records('planning')['studio_plan_events']) == 1 and len(records('planning')['studio_plan_requests']) == 1 and len(records('planning')['studio_plan_heads']) == 1 and 'Plan created' in response.text)
    submit(owner, editor, 'project_planning_save', {'client_action': 'Review retained design'})
    activity = portal+'?activity=1'; key = 'planning:'+first['request_id']; response = client.get(activity+'&open='+key)
    check(label+' upgraded planning notice opens exact earlier snapshot', 'focus_plan='+first['request_id'] in response.url and BeautifulSoup(response.text, 'html.parser').select_one('#planning-'+first['request_id']+' details[open]') is not None)
    submit(client, activity, 'activity_mark', {'keys': json.dumps([key]), 'state': 'read'})
    saved = records('planning'); stable = records()
    earlier = {name: records(name) for name in ['progress', 'tickets', 'plans', 'submissions', 'drafts', 'provider', 'mail-accounts', 'templates']}
    check(label+' upgraded plans retain dated revisions and private read status', len(saved['studio_plan_events']) == 2 and any(r['event_key'] == key and r['actor_type'] == 'client' for r in saved['activity_reads']))

    def retained(expected):
        return records('planning') == expected and all(records(name) == data for name, data in earlier.items()) and all(records()[name] == stable[name] for name in ['studio_clients', 'studio_jobs', 'studio_invoices']) and all((private/name).read_bytes() == data for name, data in keys.items())

    journal = private/RECOVERY/'journal.php'; journal_before = journal.read_bytes(); records('owner-rate-exhaust')
    response = action('recover')
    check(label+' throttled recovery preserves exact planning and private keys', 'Please wait before another owner authentication attempt.' in response.text and journal.read_bytes() == journal_before and retained(saved))
    records('owner-rate-reset'); response = action('recover'); old = client.get(target)
    check(label+' old application retains planning records without claiming new dashboard', '<h2>Rolled-back</h2>' in response.text and retained(saved) and BeautifulSoup(old.text, 'html.parser').select_one('.project-planning') is None)
    response = action('apply'); restored = client.get(target)
    check(label+' reapplication restores populated plan without setup or read reset', '<h2>Complete</h2>' in response.text and retained(saved) and 'Review retained design' in restored.text and 'Retained content milestone' in restored.text)
    owner.post(editor, data=first)
    check(label+' original plan retry after recovery cannot duplicate or revert later plan', retained(saved))
    submit(owner, editor, 'project_planning_save', {'scope': 'Authorized planning change after recovery'})
    active = records('planning')
    check(label+' recovered planning accepts one new authorized revision', len(active['studio_plan_events']) == 3 and len(active['studio_plan_requests']) == 3 and retained(active) and 'Authorized planning change after recovery' in client.get(target).text)
    response = action('recover')
    check(label+' second recovery retains appended planning and existing activity reads', '<h2>Rolled-back</h2>' in response.text and retained(active))
    response = action('apply')
    check(label+' second reapplication restores plans without changing earlier business records', '<h2>Complete</h2>' in response.text and retained(active) and 'Authorized planning change after recovery' in client.get(target).text)
