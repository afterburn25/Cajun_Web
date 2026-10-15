"""Real Apache upgrade/recovery from an installed, immutable 1.11.0 package."""
from pathlib import Path
import json,os,shutil,subprocess,zipfile,time
import requests
from bs4 import BeautifulSoup
from release_version import VERSION,STAGE,RECOVERY
ROOT=Path(__file__).resolve().parents[1]

def token(response):
    n=BeautifulSoup(response.text,'html.parser').select_one('input[name=csrf]')
    return n['value']

def run(web,base,check,qa,php,label,prefix,version):
    folder='organized-'+version;root=web/folder;url=base+'/'+folder;shutil.copytree(ROOT/('test-runtime/released-'+version),root)
    label+=' '+version
    session=requests.Session();r=session.get(url+'/install.php')
    password='Synthetic organized upgrade passphrase'
    fields={'csrf':token(r),'action':'install','db_host':os.environ['CWP_TEST_HOST'],'db_port':os.environ['CWP_TEST_PORT'],'db_name':'cwp_test','db_user':os.environ['CWP_TEST_USER'],'db_password':os.environ['CWP_TEST_PASSWORD'],'db_prefix':prefix,'owner_name':'Organized fixture owner','owner_email':'organized@example.invalid','owner_password':password,'owner_confirm':password,'site_url':url,'confirm_install':'1','seed':'1','preview':'1'}
    r=session.post(url+'/install.php',data=fields,timeout=90)
    (qa/('organized-'+label+'-install.json')).write_text(r.text)
    check(label+' actual retained release installs through Apache',r.status_code==200 and r.json()['ok'])
    private=Path(r.json()['storage']);(private/'retained-customer.php').write_bytes(b'<?php exit; ?>private retained customer bytes')
    original={n:(root/n).read_bytes() for n in ['config.php','.installed.php','storage/.htaccess','upload/.htaccess','config/defaults.php','assets/css/site.css']}
    def records(action='snapshot'):
        p=subprocess.run([php,str(ROOT/'tests/organized_update_fixture.php'),str(root),action],capture_output=True,text=True)
        assert p.returncode==0,p.stderr
        return json.loads(p.stdout)
    before=records('seed')
    if version in ['1.21.0','1.22.0','1.23.0']:records('drafts-seed')
    prior_submissions=None
    if version in ['1.22.0','1.23.0']:
        records('submissions-seed');prior_submissions=records('submissions')
    prior_drafts=records('drafts') if version in ['1.21.0','1.22.0','1.23.0'] else None
    prior_templates=records('templates') if version in ['1.12.0','1.13.0','1.14.0','1.15.0','1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    prior_accounts=records('mail-accounts') if version in ['1.13.0','1.14.0','1.15.0','1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    prior_key=(private/'mail-master.key').read_bytes() if version in ['1.13.0','1.14.0','1.15.0','1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    prior_review=records('review-intake') if version in ['1.13.0','1.14.0','1.15.0','1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    prior_reports=records('reviews') if version in ['1.14.0','1.15.0','1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    prior_appointment=records('appointment-intake') if version in ['1.14.0','1.15.0','1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    prior_schedule=records('appointments') if version in ['1.15.0','1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    if version in ['1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0']:records('mail-checks-seed')
    prior_checks=records('mail-checks') if version in ['1.16.0','1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    if version in ['1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0']:records('inbox-seed')
    prior_inbox=records('inbox') if version in ['1.17.0','1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    if version in ['1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0']:records('messages-seed')
    prior_messages=records('messages') if version in ['1.18.0','1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    if version in ['1.19.0','1.20.0','1.21.0','1.22.0','1.23.0']:
        records('notices-seed');prior_inbox=records('inbox')
    prior_notices=records('notices') if version in ['1.19.0','1.20.0','1.21.0','1.22.0','1.23.0'] else None
    prior_provider=None;prior_provider_key=None
    if version in ['1.20.0','1.21.0','1.22.0','1.23.0']:
        # Fresh installation creates the owner, but does not authenticate this browser.
        login=url+'/admin/index.php?view=login'
        session.post(login,data={'csrf':token(session.get(login)),'action':'login','email':'organized@example.invalid','password':password})
        settings=url+'/admin/index.php?view=provider-settings';secret='SYNTHETIC_ORIGINAL_120_GENERAL_KEY'
        r=session.get(settings)
        check(label+' owner signs in before saving original provider settings',r.status_code==200 and BeautifulSoup(r.text,'html.parser').select_one('input[name=action][value=provider_settings_save]') is not None)
        r=session.post(settings,data={'csrf':token(r),'action':'provider_settings_save','profile_id':'','revision':'0','current_password':password,'label':'Earlier 1.20 provider connection','general_key':secret,'enabled':'1'})
        check(label+' authenticated original provider save succeeds before history seeding','20i settings saved' in r.text and len(records('provider')['profiles'])==1)
        records('provider-seed');prior_provider=records('provider');prior_provider_key=(private/'provider-master.key').read_bytes()
        check(label+' original 1.20 installation holds encrypted provider profile and nonempty check history','20i settings saved' in r.text and len(prior_provider['profiles'])==1 and len(prior_provider['history'])==1 and len(prior_provider['checks'])==2 and len(prior_provider['results'])==1 and secret not in prior_provider['profiles'][0]['credentials'] and secret not in r.text and len(prior_provider_key)==32 and prior_provider_key!=prior_key and (private/'provider-master.key').stat().st_mode&0o777==0o600)
    check(label+' retained schema contains meaningful client project and invoice records',all(before[n]['count']>0 for n in before))
    session.get(url+'/admin/index.php?view=login')
    probe=root/'cache-probe.php';probe.write_text("<?php echo json_encode(['enabled'=>opcache_get_status(false)['opcache_enabled']??false,'timestamps'=>ini_get('opcache.validate_timestamps'),'cached'=>opcache_is_script_cached(__DIR__.'/config/defaults.php')]);")
    cache=session.get(url+'/cache-probe.php').json();probe.unlink()
    check(label+' prior organized version actually cached without timestamp checks',cache['enabled'] and cache['timestamps']=='0' and cache['cached'])
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(root)
    owner=url+'/'+STAGE+'/owner-update.php';r=session.get(owner)
    def action(name,**overrides):
        fields={'csrf':token(session.get(owner)),'email':'organized@example.invalid','owner_password':password,'backup_confirm':'1','action':name};fields.update(overrides)
        return session.post(owner,data=fields,timeout=90)
    r=action('apply',csrf='invalid')
    check(label+' organized update rejects CSRF before journal creation','session expired' in r.text.lower() and not (private/RECOVERY/'journal.php').exists())
    r=action('inspect');check(label+' owner inspection recognizes organized baseline',('Starting release: '+version+'.') in r.text)
    r=action('apply',owner_password='Wrong fixture password')
    check(label+' organized update requires current owner password','current owner password is required' in r.text and not (private/RECOVERY/'journal.php').exists())
    r=action('apply',backup_confirm='0')
    check(label+' organized update requires backup confirmation','Confirm a recoverable backup' in r.text and not (private/RECOVERY/'journal.php').exists())
    failed=subprocess.run([php,str(ROOT/'tests/structure_worker.php'),str(root),'apply','2'],capture_output=True,text=True)
    check(label+' organized file update interrupts with recovery available',failed.returncode!=0 and 'Synthetic interruption' in failed.stderr)
    check(label+' maintenance blocks canonical administration',session.get(url+'/admin/index.php').status_code==403)
    check(label+' staged application payload stays private',session.get(url+'/'+STAGE+'/site/admin/index.php').status_code==403)
    r=action('apply');(qa/('organized-'+label+'-update.html')).write_text(r.text)
    check(label+' owner resumes organized update through Apache',r.status_code==200 and '<h2>Complete</h2>' in r.text)
    r=session.get(url+'/admin/index.php')
    check(label+' same owner session sees new version despite prior opcode cache',r.status_code==200 and ('STUDIO RELEASE '+VERSION) in r.text)
    check(label+' organized update preserves exact business rows',records()==before)
    check(label+' organized update preserves config guards scaffolds and private bytes',all((root/n).read_bytes()==original[n] for n in ['config.php','.installed.php','storage/.htaccess','upload/.htaccess']) and (private/'retained-customer.php').read_bytes()==b'<?php exit; ?>private retained customer bytes')
    check(label+' fresh organized update adds no legacy PHP endpoints',all(not (root/n).exists() for n in ['admin.php','portal.php','api.php','activity.php','media.php','sandbox-notifications.php','install.php']))
    r=action('recover');check(label+' owner can recover original organized files','<h2>Rolled-back</h2>' in r.text and all((root/n).read_bytes()==b for n,b in original.items()))
    check(label+' recovery invalidates cached version and preserves session',('STUDIO RELEASE '+version) in session.get(url+'/admin/index.php').text)
    check(label+' recovery does not reset business records',records()==before)
    if prior_provider is not None:check(label+' original provider records and both keys survive recovered 1.20 files',records('provider')==prior_provider and (private/'provider-master.key').read_bytes()==prior_provider_key and (private/'mail-master.key').read_bytes()==prior_key)
    r=action('apply');check(label+' recovered organized release can be updated again','<h2>Complete</h2>' in r.text and ('STUDIO RELEASE '+VERSION) in session.get(url+'/admin/index.php').text)
    if version=='1.11.2':
        library=url+'/admin/index.php?view=mail-templates'
        r=session.post(library,data={'csrf':token(session.get(library)),'action':'mail_templates_setup','current_password':password})
        check(label+' actual 1.11.2 owner enables additive template storage','Template library enabled' in r.text)
        new=url+'/admin/index.php?view=mail-template'
        r=session.post(new,data={'csrf':token(session.get(new)),'action':'mail_templates_create','name':'Retained template','subject':'Hello {{client_name}}','body':'Preserve template after file recovery','visibility':'personal'})
        saved=records('templates')
        check(label+' actual upgraded site saves versioned template',len(saved['templates'])==1 and len(saved['history'])==1 and 'Retained template' in r.text)
        after=records();check(label+' template setup preserves original business records',all(after[n]==v for n,v in before.items() if n!='settings'))
        r=action('recover');check(label+' file recovery leaves new template records intact','<h2>Rolled-back</h2>' in r.text and records('templates')==saved)
        r=action('apply');check(label+' reapplied update recognizes retained templates','<h2>Complete</h2>' in r.text and 'Retained template' in session.get(library).text and records('templates')==saved)
    if version=='1.12.0':
        check(label+' upgrade retains actual earlier template and history',len(prior_templates['templates'])==1 and records('templates')==prior_templates)
        library=url+'/admin/index.php?view=mail-accounts';new=url+'/admin/index.php?view=mail-account'
        r=session.post(library,data={'csrf':token(session.get(library)),'action':'mail_accounts_setup','current_password':password})
        check(label+' owner enables additive email account storage','Email account storage enabled' in r.text)
        fields={'csrf':token(session.get(new)),'action':'mail_accounts_save','account_id':'','revision':'0','current_password':password,'label':'Retained upgraded account','address':'upgrade@example.invalid','imap_host':'imap.example.invalid','imap_port':'993','imap_encryption':'ssl','imap_username':'upgrade-incoming','imap_password':'Synthetic upgrade incoming password','smtp_host':'smtp.example.invalid','smtp_port':'587','smtp_encryption':'starttls','smtp_username':'upgrade-outgoing','smtp_password':'Synthetic upgrade outgoing password','enabled':'1'}
        r=session.post(new,data=fields);saved=records('mail-accounts');aid=saved['accounts'][0]['id'];detail=new+'&account_id='+aid
        check(label+' upgraded site saves encrypted account and metadata history','Retained upgraded account' in r.text and len(saved['accounts'])==1 and len(saved['history'])==1 and fields['imap_password'] not in saved['accounts'][0]['credentials'])
        r=session.post(detail,data={'csrf':token(session.get(detail)),'action':'mail_accounts_grant','account_id':aid,'revision':'0','user_id':'1','can_read':'1','enabled':'1','days':'7','current_password':password})
        saved=records('mail-accounts');key=(private/'mail-master.key').read_bytes()
        check(label+' upgraded site retains explicit assignment and private key',len(saved['grants'])==1 and len(saved['grant_history'])==1 and len(key)==32 and (private/'mail-master.key').stat().st_mode&0o777==0o600)
        after=records();check(label+' account setup preserves existing business and template records',all(after[n]==v for n,v in before.items() if n!='settings') and records('templates')==prior_templates)
        r=action('recover');check(label+' file recovery preserves new account data and credential key','<h2>Rolled-back</h2>' in r.text and records('mail-accounts')==saved and (private/'mail-master.key').read_bytes()==key and records('templates')==prior_templates)
        r=action('apply');check(label+' reapplied update recognizes protected email account settings','<h2>Complete</h2>' in r.text and 'Retained upgraded account' in session.get(library).text and records('mail-accounts')==saved and (private/'mail-master.key').read_bytes()==key)
        r=session.post(detail,data={'csrf':token(session.get(detail)),'action':'mail_accounts_save','account_id':aid,'revision':'1','current_password':password,**{k:v for k,v in fields.items() if k in ['label','address','imap_host','imap_port','imap_encryption','imap_username','smtp_host','smtp_port','smtp_encryption','smtp_username','enabled']},'imap_password':'','smtp_password':''})
        check(label+' original private key still opens saved credentials after recovery','Email settings saved' in r.text and records('mail-accounts')['accounts'][0]['revision']==2)
    if version=='1.13.0':
        check(label+' upgrade preserves actual email settings assignments key and templates',records('mail-accounts')==prior_accounts and records('templates')==prior_templates and (private/'mail-master.key').read_bytes()==prior_key and len(prior_accounts['accounts'])==1 and len(prior_accounts['grants'])==1)
        lid=prior_review['id'];review=url+'/admin/index.php?view=website-review&id='+str(lid)
        def review_action(name,**fields):
            return session.post(review,data={'csrf':token(session.get(review)),'action':name,'id':lid,**fields})
        r=review_action('reviews_setup',current_password=password);check(label+' older owner enables additive review storage','Start this review' in r.text)
        r=review_action('reviews_adopt',website='example.org/upgrade');r=review_action('reviews_stage',revision='1',stage='in-review')
        r=review_action('reviews_report',revision='2',summary='Retained upgraded report',**{'findings[0][title]':'Retained observation','findings[0][detail]':'Synthetic human-authored evidence','findings[0][evidence]':'human-review','findings[0][source]':'','findings[0][observed_at]':''})
        saved=records('reviews');check(label+' upgraded owner saves human report and retained revisions',len(saved['reviews'])==1 and len(saved['reports'])==1 and len(saved['history'])==3 and 'Retained upgraded report' in r.text)
        check(label+' review setup preserves original inquiry email and template bytes',records('review-intake')==prior_review and records('mail-accounts')==prior_accounts and records('templates')==prior_templates)
        r=action('recover');check(label+' file recovery retains review rows and original email key','<h2>Rolled-back</h2>' in r.text and records('reviews')==saved and records('mail-accounts')==prior_accounts and (private/'mail-master.key').read_bytes()==prior_key)
        r=action('apply');check(label+' reapplication opens retained report and history','<h2>Complete</h2>' in r.text and 'Retained upgraded report' in session.get(review).text and records('reviews')==saved and records('review-intake')==prior_review)
        account=prior_accounts['accounts'][0];mail=url+'/admin/index.php?view=mail-account&account_id='+account['id']
        r=session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_save','account_id':account['id'],'revision':account['revision'],'current_password':password,**{k:account[k] for k in ['label','address','imap_host','imap_port','imap_encryption','imap_username','smtp_host','smtp_port','smtp_encryption','smtp_username','enabled']},'imap_password':'','smtp_password':''})
        check(label+' original account key still decrypts after review update and recovery','Email settings saved' in r.text and records('mail-accounts')['accounts'][0]['revision']==account['revision']+1 and (private/'mail-master.key').read_bytes()==prior_key)

    if version=='1.14.0':
        check(label+' update preserves existing reviews and private email key',records('reviews')==prior_reports and len(prior_reports['history'])==3 and records('mail-accounts')==prior_accounts and records('templates')==prior_templates and (private/'mail-master.key').read_bytes()==prior_key)
        settings=url+'/admin/index.php?view=consultation-settings'
        r=session.post(settings,data={'csrf':token(session.get(settings)),'action':'appointments_setup','current_password':password})
        check(label+' older owner prepares disabled appointment storage','Studio availability' in r.text and records('appointments')['settings'][0]['enabled']==0)
        fields={'csrf':token(session.get(settings)),'action':'appointments_configure','revision':'1','current_password':password,'timezone':'UTC','slot_minutes':'30','buffer_minutes':'15','notice_minutes':'0','enabled':'1'}
        for day in range(7):fields.update({f'hours[{day}][enabled]':'1',f'hours[{day}][start]':'00:00',f'hours[{day}][end]':'24:00'})
        r=session.post(settings,data=fields);check(label+' owner enables explicit consultation availability','Availability saved' in r.text)
        lid=prior_appointment['id'];detail=url+'/admin/index.php?view=consultation&id='+str(lid)
        offered=BeautifulSoup(session.get(detail).text,'html.parser');chosen=next(o['value'] for o in offered.select('[name=slot] option[value]') if o['value'] and int(o['value'])>time.time()+86400)
        r=session.post(detail,data={'csrf':token(session.get(detail)),'action':'appointments_adopt','id':lid,'revision':'1','slot':chosen,'timezone':'America/Chicago'})
        r=session.post(detail,data={'csrf':token(session.get(detail)),'action':'appointments_confirm','id':lid,'revision':'1','current_password':password})
        saved=records('appointments');check(label+' upgraded inquiry receives explicit confirmation and history',saved['appointments'][0]['status']=='confirmed' and len(saved['history'])==2 and len(saved['settings_history'])==2 and 'Confirmed' in r.text)
        check(label+' scheduling keeps original inquiry review template and email bytes',records('appointment-intake')==prior_appointment and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates)
        r=action('recover');check(label+' file recovery retains appointments and earlier private records','<h2>Rolled-back</h2>' in r.text and records('appointments')==saved and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and (private/'mail-master.key').read_bytes()==prior_key)
        r=action('apply');check(label+' reapplication reopens retained appointment history','<h2>Complete</h2>' in r.text and 'Confirmed' in session.get(detail).text and records('appointments')==saved and records('appointment-intake')==prior_appointment)
        account=prior_accounts['accounts'][0];mail=url+'/admin/index.php?view=mail-account&account_id='+account['id']
        r=session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_save','account_id':account['id'],'revision':account['revision'],'current_password':password,**{k:account[k] for k in ['label','address','imap_host','imap_port','imap_encryption','imap_username','smtp_host','smtp_port','smtp_encryption','smtp_username','enabled']},'imap_password':'','smtp_password':''})
        check(label+' earlier email key still decrypts after scheduling recovery','Email settings saved' in r.text and records('mail-accounts')['accounts'][0]['revision']==account['revision']+1 and (private/'mail-master.key').read_bytes()==prior_key)

    if version=='1.15.0':
        check(label+' update retains calendar history and earlier private account records',records('appointments')==prior_schedule and len(prior_schedule['history'])==2 and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates and (private/'mail-master.key').read_bytes()==prior_key)
        account=prior_accounts['accounts'][0];mail=url+'/admin/index.php?view=mail-account&account_id='+account['id']
        r=session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_connections_setup','account_id':account['id'],'current_password':password})
        check(label+' older owner prepares empty check history','Connection-check history is ready' in r.text and records('mail-checks')=={'checks':[],'results':[]})
        r=session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_check','account_id':account['id'],'revision':account['revision'],'target':'imap','current_password':password})
        check(label+' upgraded check refuses fixture-only host before network','public server hostname' in r.text and records('mail-checks')=={'checks':[],'results':[]})
        records('mail-checks-seed');saved_checks=records('mail-checks')
        check(label+' synthetic failure history is displayed without claiming success',len(saved_checks['checks'])==1 and saved_checks['results'][0]['outcome']=='failed' and 'Check failed' in session.get(mail).text)
        check(label+' check setup preserves scheduling reviews accounts and templates',records('appointments')==prior_schedule and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates)
        r=action('recover');check(label+' file recovery preserves check schema and earlier private records','<h2>Rolled-back</h2>' in r.text and records('mail-checks')==saved_checks and records('appointments')==prior_schedule and records('mail-accounts')==prior_accounts and (private/'mail-master.key').read_bytes()==prior_key)
        r=action('apply');check(label+' reapplication recognizes prepared connection checks','<h2>Complete</h2>' in r.text and 'Test incoming connection' in session.get(mail).text and records('mail-checks')==saved_checks and records('appointments')==prior_schedule and records('reviews')==prior_reports)
        r=session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_save','account_id':account['id'],'revision':account['revision'],'current_password':password,**{k:account[k] for k in ['label','address','imap_host','imap_port','imap_encryption','imap_username','smtp_host','smtp_port','smtp_encryption','smtp_username','enabled']},'imap_password':'','smtp_password':''})
        check(label+' original key decrypts after connection-check recovery','Email settings saved' in r.text and records('mail-accounts')['accounts'][0]['revision']==account['revision']+1 and (private/'mail-master.key').read_bytes()==prior_key)

    if version=='1.16.0':
        check(label+' update retains nonempty connection checks and earlier private records',records('mail-checks')==prior_checks and len(prior_checks['checks'])==1 and records('appointments')==prior_schedule and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates and (private/'mail-master.key').read_bytes()==prior_key)
        account=prior_accounts['accounts'][0];library=url+'/admin/index.php?view=mail-accounts';inbox=url+'/admin/index.php?view=mail-inbox&account_id='+account['id']
        check(label+' upgraded inbox waits for explicit owner preparation','must prepare inbox reading' in session.get(inbox).text)
        r=session.post(library,data={'csrf':token(session.get(library)),'action':'mail_inbox_setup','current_password':password})
        check(label+' owner prepares empty inbox request history','Inbox request history is ready' in r.text and records('inbox')=={'requests':[],'results':[]})
        r=session.post(inbox,data={'csrf':token(session.get(inbox)),'action':'mail_inbox_read','account_id':account['id'],'revision':account['revision']})
        check(label+' upgraded inbox refuses fixture-only host without network','public server hostname' in r.text and records('inbox')=={'requests':[],'results':[]})
        records('inbox-seed');saved_inbox=records('inbox')
        check(label+' synthetic failed inbox history never claims an empty successful mailbox',len(saved_inbox['requests'])==1 and saved_inbox['results'][0]['outcome']=='failed' and 'Request failed' in session.get(inbox).text and 'server reported an empty inbox' not in session.get(inbox).text)
        check(label+' inbox setup preserves connection calendar review account template and key bytes',records('mail-checks')==prior_checks and records('appointments')==prior_schedule and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates and (private/'mail-master.key').read_bytes()==prior_key)
        r=action('recover');check(label+' file recovery preserves inbox history and existing private records','<h2>Rolled-back</h2>' in r.text and records('inbox')==saved_inbox and records('mail-checks')==prior_checks and records('appointments')==prior_schedule and records('mail-accounts')==prior_accounts and (private/'mail-master.key').read_bytes()==prior_key)
        r=action('apply');check(label+' reapplication recognizes retained inbox request history','<h2>Complete</h2>' in r.text and 'Request failed' in session.get(inbox).text and records('inbox')==saved_inbox and records('mail-checks')==prior_checks and records('reviews')==prior_reports)
        mail=url+'/admin/index.php?view=mail-account&account_id='+account['id']
        r=session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_save','account_id':account['id'],'revision':account['revision'],'current_password':password,**{k:account[k] for k in ['label','address','imap_host','imap_port','imap_encryption','imap_username','smtp_host','smtp_port','smtp_encryption','smtp_username','enabled']},'imap_password':'','smtp_password':''})
        check(label+' original private key decrypts after inbox update recovery','Email settings saved' in r.text and records('mail-accounts')['accounts'][0]['revision']==account['revision']+1 and (private/'mail-master.key').read_bytes()==prior_key)

    if version=='1.17.0':
        check(label+' update retains nonempty inbox and earlier private history',records('inbox')==prior_inbox and len(prior_inbox['requests'])==1 and records('mail-checks')==prior_checks and records('appointments')==prior_schedule and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates and (private/'mail-master.key').read_bytes()==prior_key)
        account=prior_accounts['accounts'][0];library=url+'/admin/index.php?view=mail-accounts'
        route={'account_id':account['id'],'revision':str(account['revision']),'uidvalidity':'1234','uid':'82'}
        from urllib.parse import urlencode
        message=url+'/admin/index.php?view=mail-message&'+urlencode(route)
        check(label+' upgraded messages wait for explicit owner preparation','must prepare message reading' in session.get(message).text)
        r=session.post(library,data={'csrf':token(session.get(library)),'action':'mail_message_setup','current_password':password})
        check(label+' owner prepares empty message request history','Message request history is ready' in r.text and records('messages')=={'requests':[],'results':[]})
        r=session.post(message,data={'csrf':token(session.get(message)),'action':'mail_message_read',**route})
        check(label+' upgraded message refuses fixture-only host without network','public incoming server' in r.text and records('messages')=={'requests':[],'results':[]})
        records('messages-seed');saved_messages=records('messages')
        check(label+' synthetic failed message history is displayed honestly',len(saved_messages['requests'])==1 and saved_messages['results'][0]['outcome']=='failed' and 'Request failed' in session.get(message).text and 'data-message-content' not in session.get(message).text)
        check(label+' message preparation preserves every earlier account and history',records('inbox')==prior_inbox and records('mail-checks')==prior_checks and records('appointments')==prior_schedule and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates)
        r=action('recover');check(label+' file recovery preserves message history and original key','<h2>Rolled-back</h2>' in r.text and records('messages')==saved_messages and records('inbox')==prior_inbox and records('mail-checks')==prior_checks and records('appointments')==prior_schedule and records('mail-accounts')==prior_accounts and (private/'mail-master.key').read_bytes()==prior_key)
        r=action('apply');check(label+' reapplication recognizes retained message requests','<h2>Complete</h2>' in r.text and 'Request failed' in session.get(message).text and records('messages')==saved_messages and records('inbox')==prior_inbox and records('reviews')==prior_reports)
        mail=url+'/admin/index.php?view=mail-account&account_id='+account['id']
        r=session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_save','account_id':account['id'],'revision':account['revision'],'current_password':password,**{k:account[k] for k in ['label','address','imap_host','imap_port','imap_encryption','imap_username','smtp_host','smtp_port','smtp_encryption','smtp_username','enabled']},'imap_password':'','smtp_password':''})
        check(label+' original private key decrypts after message update recovery','Email settings saved' in r.text and records('mail-accounts')['accounts'][0]['revision']==account['revision']+1 and (private/'mail-master.key').read_bytes()==prior_key)

    if version=='1.18.0':
        def prior_retained():
            return records('messages')==prior_messages and records('mail-checks')==prior_checks and records('appointments')==prior_schedule and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates and (private/'mail-master.key').read_bytes()==prior_key
        check(label+' update preserves nonempty message and prior history',len(prior_messages['requests'])==1 and records('inbox')==prior_inbox and prior_retained())
        library=url+'/admin/index.php?view=mail-accounts';center=url+'/admin/index.php?view=mail-notices';endpoint=url+'/api/mail-notices.php'
        check(label+' updated notices wait for owner preparation','must prepare' in session.get(center).text and session.get(endpoint).status_code==204)
        r=session.post(library,data={'csrf':token(session.get(library)),'action':'mail_notices_setup','current_password':password})
        check(label+' owner enables empty additive notice storage','Email notice storage is ready' in r.text and records('notices')=={'windows':[],'items':[],'states':[]})
        records('notices-seed');saved_notices=records('notices');saved_inbox=records('inbox')
        check(label+' synthetic committed observation and local dismissal survive projection',len(saved_notices['windows'])==1 and len(saved_notices['items'])==2 and len(saved_notices['states'])==1 and session.get(endpoint).json()['count']==1 and 'Last successful inbox check' in session.get(center).text)
        check(label+' notice setup preserves original account key and all earlier history',prior_retained() and all(q in saved_inbox['requests'] for q in prior_inbox['requests']) and all(q in saved_inbox['results'] for q in prior_inbox['results']))
        r=action('recover');check(label+' file recovery retains nonempty notice state and original key','<h2>Rolled-back</h2>' in r.text and records('notices')==saved_notices and records('inbox')==saved_inbox and prior_retained())
        r=action('apply');check(label+' reapplication recognizes retained notices without resetting local state','<h2>Complete</h2>' in r.text and records('notices')==saved_notices and session.get(endpoint).json()['count']==1 and records('inbox')==saved_inbox and prior_retained())
        account=prior_accounts['accounts'][0];mail=url+'/admin/index.php?view=mail-account&account_id='+account['id']
        r=session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_save','account_id':account['id'],'revision':account['revision'],'current_password':password,**{k:account[k] for k in ['label','address','imap_host','imap_port','imap_encryption','imap_username','smtp_host','smtp_port','smtp_encryption','smtp_username','enabled']},'imap_password':'','smtp_password':''})
        check(label+' recovered private key decrypts and changed generation hides old notices','Email settings saved' in r.text and records('mail-accounts')['accounts'][0]['revision']==account['revision']+1 and (private/'mail-master.key').read_bytes()==prior_key and session.get(endpoint).json()['count']==0 and records('notices')==saved_notices)

    if version in ['1.19.0','1.20.0','1.21.0','1.22.0','1.23.0']:
        def prior_retained():
            return records('notices')==prior_notices and records('messages')==prior_messages and records('inbox')==prior_inbox and records('mail-checks')==prior_checks and records('appointments')==prior_schedule and records('reviews')==prior_reports and records('mail-accounts')==prior_accounts and records('templates')==prior_templates and (private/'mail-master.key').read_bytes()==prior_key
    if version=='1.19.0':
        check(label+' update preserves nonempty notices and every earlier private record',len(prior_notices['items'])==2 and len(prior_notices['states'])==1 and prior_retained())
        settings=url+'/admin/index.php?view=provider-settings'
        r=session.get(settings);check(label+' owner explicitly prepares provider storage after file update','Prepare 20i settings' in r.text and not (private/'provider-master.key').exists())
        r=session.post(settings,data={'csrf':token(r),'action':'provider_settings_setup','current_password':password})
        check(label+' additive provider setup creates empty storage','20i settings and check history are ready' in r.text and records('provider')=={'profiles':[],'history':[],'checks':[],'results':[]})
        secret='SYNTHETIC_UPGRADE_GENERAL_KEY'
        r=session.post(settings,data={'csrf':token(session.get(settings)),'action':'provider_settings_save','profile_id':'','revision':'0','current_password':password,'label':'Retained provider connection','general_key':secret,'enabled':'1'})
        saved=records('provider');provider_key=(private/'provider-master.key').read_bytes()
        check(label+' upgraded owner saves encrypted profile and private provider key',len(saved['profiles'])==1 and len(saved['history'])==1 and secret not in saved['profiles'][0]['credentials'] and secret not in r.text and len(provider_key)==32 and (private/'provider-master.key').stat().st_mode&0o777==0o600 and provider_key!=prior_key)
        records('provider-seed');saved=records('provider');r=session.get(settings)
        check(label+' upgrade retains honest unknown and incomplete check history',len(saved['checks'])==2 and len(saved['results'])==1 and saved['results'][0]['state']=='unknown' and saved['results'][0]['result_code']=='timeout' and 'Incomplete' in r.text and 'Read verified' not in r.text)
        check(label+' provider setup and history preserve earlier private data',prior_retained())
        r=action('recover');check(label+' recovery retains provider records original keys and earlier data','<h2>Rolled-back</h2>' in r.text and records('provider')==saved and (private/'provider-master.key').read_bytes()==provider_key and prior_retained())
        r=action('apply');check(label+' reapplication recognizes retained profile and check history','<h2>Complete</h2>' in r.text and records('provider')==saved and 'Retained provider connection' in session.get(settings).text and (private/'provider-master.key').read_bytes()==provider_key and prior_retained())
        profile=saved['profiles'][0]
        r=session.post(settings,data={'csrf':token(session.get(settings)),'action':'provider_settings_save','profile_id':profile['id'],'revision':profile['revision'],'current_password':password,'label':'Retained provider connection','general_key':'','enabled':'1'})
        restored=records('provider')
        check(label+' original provider key decrypts after recovery and reapplication','20i settings saved' in r.text and restored['profiles'][0]['revision']==profile['revision']+1 and len(restored['history'])==2 and restored['checks']==saved['checks'] and restored['results']==saved['results'] and (private/'provider-master.key').read_bytes()==provider_key and prior_retained())

    if version in ['1.20.0','1.21.0','1.22.0','1.23.0']:
        provider_key=prior_provider_key;restored=records('provider')
        check(label+' update and recovery preserve original provider profile check history and both keys',restored==prior_provider and prior_retained() and (private/'provider-master.key').read_bytes()==provider_key)
        settings=url+'/admin/index.php?view=provider-settings';profile=restored['profiles'][0]
        r=session.post(settings,data={'csrf':token(session.get(settings)),'action':'provider_settings_save','profile_id':profile['id'],'revision':profile['revision'],'current_password':password,'label':'Earlier 1.20 provider connection','general_key':'','enabled':'1'})
        restored=records('provider')
        check(label+' original 1.20 provider key still decrypts after update and recovery','20i settings saved' in r.text and restored['profiles'][0]['revision']==profile['revision']+1 and len(restored['history'])==2 and restored['checks']==prior_provider['checks'] and restored['results']==prior_provider['results'] and (private/'provider-master.key').read_bytes()==provider_key and prior_retained())
    if version in ['1.19.0','1.20.0']:
        # Actual retained-release upgrade, local draft preparation and file recovery.
        library=url+'/admin/index.php?view=mail-drafts'
        r=session.get(library);check(label+' draft upgrade requires explicit owner preparation','Prepare local drafts' in r.text)
        r=session.post(library,data={'csrf':token(r),'action':'mail_drafts_setup','current_password':password})
        check(label+' draft setup is additive and preserves provider and prior private data','Local email drafts are ready' in r.text and records('drafts')=={'drafts':[],'history':[]} and records('provider')==restored and prior_retained())
        account=prior_accounts['accounts'][0];mail=url+'/admin/index.php?view=mail-account&account_id='+account['id'];grant=prior_accounts['grants'][0]
        session.post(mail,data={'csrf':token(session.get(mail)),'action':'mail_accounts_grant','account_id':account['id'],'revision':grant['revision'],'user_id':1,'can_read':'1','can_send':'1','enabled':'1','days':'0','current_password':password})
        draft_accounts=records('mail-accounts')
        check(label+' owner explicitly grants Send while retaining original profile and key',draft_accounts['accounts']==prior_accounts['accounts'] and draft_accounts['grants'][0]['can_send']==1 and (private/'mail-master.key').read_bytes()==prior_key)
        new=url+'/admin/index.php?view=mail-draft&account_id='+account['id']
        def draft_form(target):
            form=BeautifulSoup(session.get(target).text,'html.parser').select_one('[data-mail-draft-editor]')
            assert form is not None,target
            return {n['name']:n.get('value','') for n in form.select('input[type=hidden][name]')}
        from urllib.parse import parse_qs,urlparse
        fields=draft_form(new);fields.update({'action':'mail_drafts_save','sender':account['id']+':'+str(account['revision']),'to':'retained@example.invalid','cc':'','bcc':'private@example.invalid','subject':'Retained draft subject','body':'Retained draft first'})
        r=session.post(new,data=fields);draft_id=parse_qs(urlparse(r.url).query)['draft_id'][0];detail=url+'/admin/index.php?view=mail-draft&draft_id='+draft_id
        fields=draft_form(detail);fields.update({'action':'mail_drafts_save','sender':account['id']+':'+str(account['revision']),'to':'retained@example.invalid','cc':'','bcc':'private@example.invalid','subject':'Retained draft subject','body':'Retained draft second'})
        session.post(detail,data=fields)
        fields=draft_form(new);fields.update({'action':'mail_drafts_save','sender':account['id']+':'+str(account['revision']),'to':'','cc':'','bcc':'','subject':'Retained discarded draft','body':'Retained discarded text'})
        r=session.post(new,data=fields);discard_id=parse_qs(urlparse(r.url).query)['draft_id'][0]
        session.post(library,data={'csrf':token(session.get(library)),'action':'mail_drafts_discard','draft_id':discard_id,'revision':'1'})
        saved_drafts=records('drafts')
        check(label+' upgraded draft forms create edited and discarded local records',len(saved_drafts['drafts'])==2 and len(saved_drafts['history'])==4 and sorted(x['status'] for x in saved_drafts['drafts'])==['discarded','draft'])
        def draft_retained():
            return records('drafts')==saved_drafts and records('provider')==restored and records('mail-accounts')==draft_accounts and records('notices')==prior_notices and records('templates')==prior_templates and records('messages')==prior_messages and records('inbox')==prior_inbox and (private/'mail-master.key').read_bytes()==prior_key and (private/'provider-master.key').read_bytes()==provider_key
        r=action('recover');check(label+' recovery retains draft text history original keys and earlier records','<h2>Rolled-back</h2>' in r.text and draft_retained())
        r=action('apply');check(label+' reapplication reopens retained private draft without rewriting history','<h2>Complete</h2>' in r.text and draft_retained() and 'Retained draft second' in session.get(detail).text)

    if version=='1.21.0':
        from mail_submission_upgrade_cases import run as submission_upgrade
        submission_upgrade({**locals(),'token':token})

    if version in ['1.22.0','1.23.0']:
        from submission_retention_122 import run as submission_retention
        submission_retention({**locals(),'token':token})

    if version=='1.23.0':
        from service_plan_upgrade_cases import run as service_plan_upgrade
        ticket_context=service_plan_upgrade({**locals(),'token':token})
        from ticket_upgrade_cases import run as ticket_upgrade
        ticket_upgrade({**locals(),**ticket_context})
        from project_progress_upgrade_cases import run as progress_upgrade
        progress_upgrade({**locals(),**ticket_context})
        from project_planning_upgrade_cases import run as planning_upgrade
        planning_upgrade({**locals(),**ticket_context})
        from design_financial_upgrade_cases import run as financial_upgrade
        financial_upgrade({**locals(),**ticket_context})
        from recurring_billing_upgrade_cases import run as recurring_upgrade
        recurring_upgrade({**locals(),**ticket_context})
