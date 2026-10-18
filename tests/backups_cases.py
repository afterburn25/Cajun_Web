"""Backup/restore admin flows over real HTTP: setup, private archive, deliberate restore, guarded download."""
import io,json,requests,zipfile
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit','php','login']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php';url=root+'?view=backups';site=WEB/'existing'
    owner=login('owner@example.invalid',OWNER)
    fixture=site/'backups-web-fixture.txt';fixture.write_text('original browser bytes',encoding='utf-8')
    try:
        r=owner.get(url);check('Backups section renders before setup','Prepare backup storage' in r.text)
        r=submit(owner,url,'backups_setup',{'csrf':'wrong'});check('Backups setup rejects CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='backups_schema_version'"))
        r=submit(owner,url,'backups_setup',{'owner_password':'wrong'});check('Backups setup requires owner password','current password' in r.text.lower())
        r=submit(owner,url,'backups_setup',{'owner_password':OWNER,'confirm':'1'});check('Owner prepares backup storage','Backup storage and records are ready' in r.text and db("SELECT value FROM ci_settings WHERE name='backups_schema_version'")==[{'value':'2'}])
        web=requests.Session();submit(web,root+'?view=login','login',{'email':'website-staff@example.invalid','password':STAFF})
        check('Website-only staff cannot open backups',web.get(url).status_code==403)
        before=db('SELECT COUNT(*) AS c FROM ci_backups')[0]['c']
        web.post(url,data={'csrf':token(web.get(root+'?view=dashboard')),'action':'backups_create','retain_days':'30','notes':'forged','confirm':'1'})
        check('Website-only staff create attempt writes nothing',db('SELECT COUNT(*) AS c FROM ci_backups')[0]['c']==before)
        r=submit(owner,url,'backups_create',{'retain_days':'30','notes':'Browser HTTP snapshot','confirm':'1'})
        check('Backup creation records the archive','Backup snapshot recorded' in r.text and db('SELECT COUNT(*) AS c FROM ci_backups')[0]['c']==before+1)
        row=db('SELECT * FROM ci_backups ORDER BY created_at DESC LIMIT 1')[0];bid=row['id']
        private=ROOT/'test-runtime/private/backups';archive=private/('backup-'+bid+'.zip')
        check('Archive lives in private storage outside the site root',archive.is_file() and not str(archive).startswith(str(site)))
        manifest=json.loads(row['manifest'])
        check('Recorded manifest excludes secrets and storage',all(not n.startswith('storage/') and n not in ('config.php','.env') for n in manifest['items']))
        fixture.write_text('changed browser bytes',encoding='utf-8')
        r=owner.get(url+'&preview='+bid);check('Restore preview reports changed item','would change' in r.text and 'backups-web-fixture.txt' in r.text)
        phrase='RESTORE '+bid[:8].upper()
        r=submit(owner,url+'&preview='+bid,'backups_restore',{'phrase':'WRONG','owner_password':OWNER,'confirm':'1'});check('Wrong confirmation phrase refused','Backup applied' not in r.text and fixture.read_text()=='changed browser bytes')
        r=submit(owner,url+'&preview='+bid,'backups_restore',{'phrase':phrase,'owner_password':'wrong','confirm':'1'});check('Restore reauthenticates the owner','Backup applied' not in r.text and fixture.read_text()=='changed browser bytes')
        r=submit(owner,url+'&preview='+bid,'backups_restore',{'phrase':phrase,'owner_password':OWNER,'confirm':'1'});check('Deliberate restore rewrites the changed file','Backup applied' in r.text and fixture.read_text()=='original browser bytes' and db('SELECT status FROM ci_backups WHERE id=?',[bid])[0]['status']=='applied')
        check('Restore is audited',db("SELECT COUNT(*) AS c FROM ci_audit WHERE action='backups.restore'")[0]['c']>=1)
        r=owner.get(url+'&download='+bid);check('Backup download is private no-store ZIP','application/zip' in r.headers.get('Content-Type','') and 'no-store' in r.headers.get('Cache-Control','') and r.content==archive.read_bytes())
        with zipfile.ZipFile(io.BytesIO(r.content)) as z:dump=z.read('database/snapshot.sql').decode('utf-8')
        manifest=json.loads(row['manifest']);check('Archive embeds a consistent database export','database' in manifest and manifest['database']['file']=='database/snapshot.sql' and manifest['database']['sha256']==__import__('hashlib').sha256(dump.encode()).hexdigest() and 'INSERT INTO `ci_settings`' in dump)
        check('Database export skips transient runtime tables','ci_rate_limits' not in dump and 'ci_resets' not in dump)
        check('Staff download is refused',web.get(url+'&download='+bid).status_code in (403,404))
        r=requests.get(url+'&download='+bid,allow_redirects=False);check('Anonymous download is refused',r.status_code in (302,303,403,404) and 'application/zip' not in r.headers.get('Content-Type',''))
        web.post(url,data={'csrf':token(web.get(root+'?view=dashboard')),'action':'backups_drill','backup':bid})
        check('Website-only staff drill attempt writes nothing',db('SELECT COUNT(*) AS c FROM ci_backup_drills')[0]['c']==0)
        r=submit(owner,url,'backups_drill',{'backup':bid})
        check('Owner restore drill verifies every archived item','Restore drill verified all' in r.text and db('SELECT result,verified,missing,mismatched FROM ci_backup_drills WHERE backup_id=?',[bid])==[{'result':'verified','verified':int(row['items'])+1,'missing':0,'mismatched':0}])
        check('Drill result is shown on the backups list','Restore drill' in owner.get(url).text and 'verified' in owner.get(url).text)
        check('Drill is audited',db("SELECT COUNT(*) AS c FROM ci_audit WHERE action='backups.drill'")[0]['c']>=1)
        check('Drill left no scratch directories',not list(private.glob('drill-*')))
    finally:
        if fixture.exists():fixture.unlink()
