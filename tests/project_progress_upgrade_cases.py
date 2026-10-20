"""Actual latest-published upgrade with populated project history and original replay."""
import json
import requests
from bs4 import BeautifulSoup
from release_version import RECOVERY


def run(env):
    url,label,check=(env[k] for k in ['url','label','check'])
    owner,records,action=(env[k] for k in ['session','records','action'])
    password,private=(env[k] for k in ['password','private'])
    admin,portal=url+'/admin/index.php',url+'/portal/index.php'
    setup,editor,target=admin+'?view=studio-upgrade',admin+'?view=studio-job&id=1',portal+'?job=1'

    def fields(session,target,operation,client_id=None):
        response=session.get(target)
        assert response.status_code==200,response.status_code
        for form in BeautifulSoup(response.text,'html.parser').select('form'):
            marker=form.select_one('[name=action]')
            if marker is None or marker.get('value')!=operation:
                continue
            if client_id is not None:
                identity=form.select_one('[name=id]')
                if identity is None or identity.get('value')!=str(client_id):
                    continue
            result={}
            for control in form.select('input[name], textarea[name], select[name]'):
                if control.has_attr('disabled') or control.get('type') in ['submit','password']:
                    continue
                if control.get('type') in ['checkbox','radio'] and not control.has_attr('checked'):
                    continue
                if control.name=='textarea':
                    value=control.get_text()
                elif control.name=='select':
                    option=control.select_one('option[selected]') or control.select_one('option')
                    value=option.get('value',option.get_text()) if option else ''
                else:
                    value=control.get('value','')
                result[control['name']]=value
            return result
        found=[(form.select_one('[name=action]') or {}).get('value') for form in BeautifulSoup(response.text,'html.parser').select('form')]
        raise AssertionError('Missing expected progress upgrade form: '+operation+' url='+response.url+' found='+repr(found))

    def submit(session,target,operation,changes,client_id=None):
        return session.post(target,data=dict(fields(session,target,operation,client_id),**changes))

    original=records();earlier={name:records(name) for name in ['submissions','drafts','provider','mail-accounts','templates']}
    keys={name:(private/name).read_bytes() for name in ['mail-master.key','provider-master.key','retained-customer.php']}
    check(label+' upgraded project history requires explicit owner setup','Enable project history' in owner.get(setup).text)
    response=submit(owner,setup,'studio_migrate',{'confirm':'1'})
    check(label+' upgraded workspace schema precedes project history setup','Database update completed' in response.text)
    response=submit(owner,setup,'project_progress_setup',{'password':password,'confirm':'1'})
    empty=records('progress')
    check(label+' additive progress setup preserves real existing projects without fabricated history','Dated project updates are enabled' in response.text and not empty['studio_progress_events'] and records()['studio_jobs']==original['studio_jobs'])

    # Activate the already seeded retained client through the real invitation flow.
    clients=admin+'?view=studio-clients'
    response=submit(owner,clients,'studio_invite',{},client_id=1)
    invitation=BeautifulSoup(response.text,'html.parser').select_one('.notice.info a[href*="invite="]')['href']
    client=requests.Session()
    client_password='Synthetic retained progress client passphrase'
    submit(client,invitation,'activate',{'password':client_password,'confirm_password':client_password})
    submit(client,portal,'login',{'email':'organized-client@example.invalid','password':client_password})

    first=dict(fields(owner,editor,'studio_job'),summary='Retained dated project summary',stage='review')
    response=owner.post(editor,data=first)
    check(label+' first upgraded project edit records honest baseline and original request',len(records('progress')['studio_progress_events'])==2 and 'Tracking started' in response.text)
    submit(owner,editor,'studio_job',{'summary':'Retained later project summary','stage':'development'})
    activity=portal+'?activity=1'
    if BeautifulSoup(owner.get(admin+'?view=activity').text,'html.parser').select_one('[value=activity_setup]') is not None:
        submit(owner,admin+'?view=activity','activity_setup',{'password':password})
    key='progress:'+first['request_id'];response=client.get(activity+'&open='+key)
    soup=BeautifulSoup(response.text,'html.parser');found=soup.select_one('#progress-'+first['request_id'])
    if not (found is not None and 'focus_progress='+first['request_id'] in response.url):
        title=(soup.select_one('title').get_text() if soup.select_one('title') else '');print(label+' activity-open evidence:',response.status_code,response.url,'anchor' if found else 'missing','title='+title.strip()[:120],flush=True)
    check(label+' upgraded client resolves the exact dated progress update',found is not None and 'focus_progress='+first['request_id'] in response.url)
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
