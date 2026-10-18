"""Use the delivered owner workflow, never emulate migration by copying payload files."""
from release_version import VERSION,STAGE,RECOVERY,LOCK_RECOVERY
import requests
import re, subprocess


def removed_setup_unavailable(env, destination, session):
    """A removed installer is either a 404 or PHP 8.4 CLI-server's exact home fallback."""
    installer=destination/'install.php'
    if installer.exists() or installer.is_symlink():return False
    version=int(subprocess.check_output([env['php'],'-r','echo PHP_VERSION_ID;'],text=True,timeout=10))
    root=env['BASE'].rstrip('/')+'/'+destination.name+'/'
    responses=[session.get(root+'install.php',allow_redirects=False,timeout=20),
               session.post(root+'install.php',data={'action':'install','confirm_install':'1'},allow_redirects=False,timeout=20)]
    def direct(response):
        return not response.history and not any(k.lower() in ('location','x-cwp-installer') for k in response.headers)
    if installer.exists() or installer.is_symlink() or not all(direct(r) for r in responses):return False
    if all(r.status_code==404 for r in responses):return True
    # https://www.php.net/manual/en/features.commandline.webserver.php documents this 8.4 change.
    if not 80400<=version<80500 or not all(r.status_code==200 for r in responses):return False
    home=session.get(root+'index.php',allow_redirects=False,timeout=20)
    if home.status_code!=200 or not direct(home):return False
    def home_document(response):
        doc=env['soup'](response)
        title=doc.select('title');hero=doc.select('h1#hero-title');canonical=doc.select('link[rel=canonical]')
        if (len(title)!=1 or not title[0].get_text(strip=True) or len(hero)!=1 or not hero[0].get_text(strip=True)
            or len(canonical)!=1 or canonical[0].get('href')!=root
            or len(doc.select('body.cwp-site.is-home[data-release="'+VERSION+'"]'))!=1
            or len(doc.select('main#main'))!=1):return None
        if doc.select('#install-form, [name=owner_password], [name=owner_confirm], [name=owner_email], [name=db_password], [name=db_name], [name=db_user], [name=confirm_install], [name=action][value=install]'):return None
        if any('install.php' in form.get('action','').lower() for form in doc.select('form')):return None
        # The current homepage has one random CSP nonce and no changing form tokens.
        nonces=doc.select('[nonce]')
        if (len(nonces)!=1 or nonces[0].name!='script' or nonces[0].get('type')!='application/ld+json'
            or not re.fullmatch(r'[A-Za-z0-9+/]{24}',nonces[0].get('nonce',''))):return None
        nonces[0]['nonce']='CWP_TEST_NONCE'
        return str(doc)
    expected=home_document(home)
    return (expected is not None and all(home_document(r)==expected for r in responses)
            and not installer.exists() and not installer.is_symlink())


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
    check(destination.name+' setup cannot be reused after update',removed_setup_unavailable(env,destination,session))
    old=session.get(base+'/'+destination.name+'/admin.php',timeout=20)
    check(destination.name+' pre-switch owner session survives old and new URLs',old.status_code==200 and 'Sales pipeline' in old.text and 'Sales pipeline' in session.get(base+'/'+destination.name+'/admin/index.php').text)
    legacy=base+'/'+destination.name+'/admin.php?view=security';r=session.get(legacy)
    fields=env['form_data'](env['form_for'](r,'account_save'));fields['current_password']='Incorrect synthetic password'
    r=session.post(legacy,data=fields,allow_redirects=False,timeout=20)
    check(destination.name+' legacy POST body and query reach canonical action without redirect',r.status_code==200 and 'current password' in r.text.lower() and 'Location' not in r.headers)
    return session
