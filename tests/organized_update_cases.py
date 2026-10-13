"""Real Apache upgrade/recovery from an installed, immutable 1.11.0 package."""
from pathlib import Path
import json,os,shutil,subprocess,zipfile
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
    r=action('apply');check(label+' recovered organized release can be updated again','<h2>Complete</h2>' in r.text and ('STUDIO RELEASE '+VERSION) in session.get(url+'/admin/index.php').text)
