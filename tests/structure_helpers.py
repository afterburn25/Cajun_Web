"""Use the delivered owner workflow, never emulate migration by copying payload files."""
from release_version import VERSION,STAGE,RECOVERY,LOCK_RECOVERY
import requests


def apply_file_update(env, destination):
    base=env['BASE'];check=env['check'];soup=env['soup'];token=env['token']
    url=base+'/'+destination.name+'/'+STAGE+'/owner-update.php'
    if destination.name=='portfolio-upgrade':
        path=destination/STAGE/'site/includes/security/csrf.php';saved=path.read_bytes();path.unlink()
        try:
            broken=requests.get(url,timeout=20)
            check('Interrupted staged bootstrap upload has useful fallback before application loading',broken.status_code==503 and 'Update support is missing or mismatched' in broken.text and not (destination/'admin/index.php').exists())
        finally:path.write_bytes(saved)
    session=requests.Session();r=session.get(url,timeout=20)
    check(destination.name+' staged update requires owner and backup',r.status_code==200 and 'Current owner password' in r.text and 'backup_confirm' in r.text)
    data={'csrf':token(r),'email':'upgrade@example.invalid','owner_password':'Synthetic upgrade passphrase 2026','backup_confirm':'1','action':'inspect'}
    r=session.post(url,data=dict(data,csrf='wrong'),timeout=20)
    check(destination.name+' update rejects missing session protection','session expired' in r.text.lower() and not (destination/'admin/index.php').exists())
    r=session.post(url,data=data,timeout=30)
    check(destination.name+' owner verifies immutable staged replacements',r.status_code==200 and '<h2>Planned</h2>' in r.text)
    data['csrf']=token(r);data['action']='apply'
    r=session.post(url,data=data,timeout=90)
    if '<h2>Complete</h2>' not in r.text:
        (env['QA']/(destination.name+'-structure-failure.html')).write_text(r.text,encoding='utf-8')
    check(destination.name+' actual owner structure migration completes',r.status_code==200 and '<h2>Complete</h2>' in r.text)
    check(destination.name+' old privileged entry is a tiny direct-dispatch shim',(destination/'admin.php').stat().st_size<300 and "'/admin/index.php'" in (destination/'admin.php').read_text())
    check(destination.name+' setup cannot be reused after update',not (destination/'install.php').exists() and session.get(base+'/'+destination.name+'/install.php').status_code==404)
    old=session.get(base+'/'+destination.name+'/admin.php',timeout=20)
    check(destination.name+' pre-switch owner session survives old and new URLs',old.status_code==200 and 'Sales pipeline' in old.text and 'Sales pipeline' in session.get(base+'/'+destination.name+'/admin/index.php').text)
    legacy=base+'/'+destination.name+'/admin.php?view=security';r=session.get(legacy)
    fields=env['form_data'](env['form_for'](r,'account_save'));fields['current_password']='Incorrect synthetic password'
    r=session.post(legacy,data=fields,allow_redirects=False,timeout=20)
    check(destination.name+' legacy POST body and query reach canonical action without redirect',r.status_code==200 and 'current password' in r.text.lower() and 'Location' not in r.headers)
    return session
