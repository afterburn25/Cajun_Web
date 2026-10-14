"""Actual latest-published upgrade with populated project history and original replay."""
import json
from bs4 import BeautifulSoup
from release_version import RECOVERY


def run(env):
    url,label,check=(env[k] for k in ['url','label','check'])
    owner,client,records,action=(env[k] for k in ['session','client','records','action'])
    fields,submit,password,private=(env[k] for k in ['fields','submit','password','private'])
    admin,portal=url+'/admin/index.php',url+'/portal/index.php'
    setup,editor,target=admin+'?view=studio-upgrade',admin+'?view=studio-job&id=1',portal+'?job=1'
    original=records();earlier={name:records(name) for name in ['tickets','plans','submissions','drafts','provider','mail-accounts','templates']}
    keys={name:(private/name).read_bytes() for name in ['mail-master.key','provider-master.key','retained-customer.php']}
    check(label+' upgraded project history requires explicit owner setup','Enable project history' in owner.get(setup).text)
    response=submit(owner,setup,'project_progress_setup',{'password':password,'confirm':'1'})
    empty=records('progress')
    check(label+' additive progress setup preserves real existing projects without fabricated history','Dated project updates are enabled' in response.text and not empty['studio_progress_events'] and records()['studio_jobs']==original['studio_jobs'])
    first=dict(fields(owner,editor,'studio_job'),summary='Retained dated project summary',stage='review')
    response=owner.post(editor,data=first)
    check(label+' first upgraded project edit records honest baseline and original request',len(records('progress')['studio_progress_events'])==2 and 'Tracking started' in response.text)
    submit(owner,editor,'studio_job',{'summary':'Retained later project summary','stage':'development'})
    activity=portal+'?activity=1'
    if BeautifulSoup(owner.get(admin+'?view=activity').text,'html.parser').select_one('[value=activity_setup]') is not None:
        submit(owner,admin+'?view=activity','activity_setup',{'password':password})
    key='progress:'+first['request_id'];response=client.get(activity+'&open='+key)
    check(label+' upgraded client resolves the exact dated progress update',BeautifulSoup(response.text,'html.parser').select_one('#progress-'+first['request_id']) is not None and 'focus_progress='+first['request_id'] in response.url)
    submit(client,activity,'activity_mark',{'keys':json.dumps([key]),'state':'read'})
    saved=records('progress')
    check(label+' upgraded progress retains atomic history anchor requests and personal read state',len(saved['studio_progress_events'])==3 and len(saved['studio_progress_requests'])==2 and len(saved['studio_progress_heads'])==1 and any(r['event_key']==key and r['actor_type']=='client' for r in saved['activity_reads']))
    stable=records()

    def retained(expected):
        return records('progress')==expected and all(records(name)==data for name,data in earlier.items()) and all(records()[name]==stable[name] for name in ['studio_clients','studio_jobs','studio_invoices']) and all((private/name).read_bytes()==data for name,data in keys.items())

    journal=private/RECOVERY/'journal.php';journal_before=journal.read_bytes();records('owner-rate-exhaust')
    response=action('recover')
    check(label+' throttled recovery preserves populated project history and original private records','Please wait before another owner authentication attempt.' in response.text and journal.read_bytes()==journal_before and retained(saved))
    records('owner-rate-reset');response=action('recover');old=client.get(target)
    check(label+' old files preserve progress history without claiming a timeline','<h2>Rolled-back</h2>' in response.text and retained(saved) and BeautifulSoup(old.text,'html.parser').select_one('.project-progress') is None)
    response=action('apply');restored=client.get(target)
    check(label+' reapplication restores saved history without setup or read reset','<h2>Complete</h2>' in response.text and retained(saved) and 'Retained later project summary' in restored.text and 'Tracking started' in restored.text)
    owner.post(editor,data=first)
    check(label+' original project update replay after recovery cannot duplicate or reset progress',retained(saved))
    submit(owner,editor,'studio_job',{'summary':'Project change after recovery','stage':'launched'})
    active=records('progress');stable=records()
    check(label+' recovered project accepts a new authorized dated change',len(active['studio_progress_events'])==4 and len(active['studio_progress_requests'])==3 and retained(active) and 'Project change after recovery' in client.get(target).text)
    response=action('recover');check(label+' second recovery preserves appended history and original read state','<h2>Rolled-back</h2>' in response.text and retained(active))
    response=action('apply');check(label+' second reapplication restores exact progress without provider mail or billing changes','<h2>Complete</h2>' in response.text and retained(active) and 'Project change after recovery' in client.get(target).text)
