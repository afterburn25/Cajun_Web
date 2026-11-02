#!/usr/bin/env python3
"""Reconcile two reviewed feature sources without altering the delivered release or main."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='3e66cb490bb8c6a93119a73209434535be365f44'
ACCOUNT='7d43444cf74ac30fd851b3b2c439606582667ca3'
BRANCH='refs/heads/chatgpt/account-notifications-combined-20261101'
EXPECTED={
'site/app/clients/account_security.php':'fe655b53e6df39acc2aaf1e04d17e4c8ad23e603bf10fe2e1bac8127de5aa4f2',
'site/portal/views/account.php':'4b3e77f530ab7d0b11761b6168d5c77727c648ac5f659ea03ca744977bd6884e',
'site/portal/controllers/router.php':'03d604fe2c411c3908aaddd3acb6d3d9510b1d2da9a80fb247149d61f17bd826',
'site/portal/controllers/actions.php':'aa81e27ff965b2aad247f20ae01216de942f81fd809a43e82b9bf1246bf374ad',
'site/portal/views/workspace.php':'42f1fb62fac27ac6e989fe3ea1f17d1fc3bc35dfbdc1ccafeb0c141e2d9d8bd2',
'site/install/inventory.generated.php':'32562a0bb8fa0b6cbf82539be91b95916b7d740ff5cd3d1681aee7781ab034fa'}
def raw(ref,path):return subprocess.check_output(['git','show',ref+':'+path],cwd=ROOT)
def sha(value):return hashlib.sha256(value).hexdigest()
def replace(text,old,new):
    if text.count(old)!=1:raise AssertionError('Expected unique reviewed anchor: '+old[:100])
    return text.replace(old,new,1)
def put(path,data):
    target=ROOT/path
    if target.is_symlink() or any(p.is_symlink() for p in target.parents):raise AssertionError('Symlink target')
    target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data if isinstance(data,bytes) else data.encode())
def main():
    if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated account/notification integration branch only')
    receipt=ROOT/'docs/ACCOUNT_NOTIFICATION_COMBINATION.json'
    if receipt.exists():
        from sys import path
        path.insert(0,str(ROOT/'tests'))
        from account_notification_contract import validate
        validate(ROOT);print('Exact combined source already recorded');return
    if subprocess.check_output(['git','status','--porcelain'],cwd=ROOT,text=True).strip():raise SystemExit('Uncommitted input refused')
    subprocess.run(['git','merge-base','--is-ancestor',BASE,'HEAD'],cwd=ROOT,check=True)
    for p in ['site/portal/controllers/router.php','site/portal/controllers/actions.php','site/portal/views/workspace.php']:
        if (ROOT/p).read_bytes()!=raw(BASE,p):raise SystemExit('Concurrent shipping change: '+p)
    adopted=['docs/ACCOUNT_SECURITY_SOURCE.json','docs/CLIENT_ACCOUNT_SECURITY.md','site/app/clients/account_security.php','site/portal/views/account.php','tests/account_security_browser.py','tests/account_security_native.php','tests/account_security_policy.php','tests/admin_session_no_reentry.php','tools/integration/account-source.0.gzpart','tools/integration/account-source.1.gzpart','tools/integration/account-source.2.gzpart','tools/integration/account-source.3.gzpart','tools/integration/account_security_apply.py']
    for p in adopted:put(p,raw(ACCOUNT,p))
    p='site/portal/controllers/router.php';s=raw(BASE,p).decode();account=raw(ACCOUNT,p).decode()
    block=account[account.index("require_once CWP_CODE_ROOT.'/app/clients/account_security.php';"):account.index("if(array_key_exists('proposal'")]
    anchor="require_once CWP_CODE_ROOT.'/includes/layout/proposals.php';require_once CWP_CODE_ROOT.'/includes/layout/notifications.php';\n"
    s=replace(s,anchor,anchor+block)
    anchor="require CWP_CODE_ROOT.'/portal/views/auth.php';\n";s=replace(s,anchor,anchor+"if($accountRequested){require CWP_CODE_ROOT.'/portal/views/account.php';}\n");put(p,s)
    p='site/portal/controllers/actions.php';s=raw(BASE,p).decode();account=raw(ACCOUNT,p).decode();start=account.index(" elseif($action==='account_password'){");end=account.index(" elseif($action==='activate')",start)
    anchor=" elseif($action==='activate')";s=replace(s,anchor,account[start:end]+anchor)
    anchor="  studio_login(strtolower(posted('email',190)),posted('password',1000));\n";s=replace(s,anchor,anchor+"  if(($_GET['account']??null)==='1')redirect(studio_url(['account'=>1]));\n");put(p,s)
    p='site/portal/views/workspace.php';s=raw(BASE,p).decode();anchor="if(notifications_feed_ready())echo '<a class=\"btn outline\" href=\"'.esc(studio_url(['notifications'=>1])).'\">Notifications</a>';?><form"
    replacement="if(notifications_feed_ready())echo '<a class=\"btn outline\" href=\"'.esc(studio_url(['notifications'=>1])).'\">Notifications</a>';?><a class=\"btn outline\" href=\"<?=esc(studio_url(['account'=>1]))?>\">Your account</a><form"
    put(p,replace(s,anchor,replacement))
    p='tests/notification_integration_contract.py';s=raw(BASE,p).decode();s=replace(s,'def validate(root):\n',"def validate(root):\n    revision='HEAD'\n    if (root/'docs/ACCOUNT_NOTIFICATION_COMBINATION.json').exists():\n        from account_notification_contract import validate as combination\n        revision=combination(root)\n    def contents(path):\n        p=root/path\n        if p.is_symlink():raise AssertionError('Symlink notification output: '+path)\n        return p.read_bytes() if revision=='HEAD' else subprocess.check_output(['git','show',revision+':'+path],cwd=root)\n")
    s=replace(s,"BASE,'HEAD','--'","BASE,revision,'--'");s=replace(s,'hashlib.sha256(target.read_bytes()).hexdigest()','hashlib.sha256(contents(path)).hexdigest()');s=replace(s,"(root/'VERSION').read_bytes()","contents('VERSION')");put(p,s)
    p='tests/account_integration_contract.py';s=raw(ACCOUNT,p).decode();s=replace(s,'def validate(root):\n',"def validate(root):\n    revision='HEAD'\n    if (root/'docs/ACCOUNT_NOTIFICATION_COMBINATION.json').exists():\n        from account_notification_contract import validate as combination\n        combination(root)\n        revision='7d43444cf74ac30fd851b3b2c439606582667ca3'\n    def contents(path):\n        return (root/path).read_bytes() if revision=='HEAD' else subprocess.check_output(['git','show',revision+':'+path],cwd=root)\n")
    s=replace(s,"BASE,'HEAD','--'","BASE,revision,'--'");s=replace(s,'hashlib.sha256(p.read_bytes()).hexdigest()','hashlib.sha256(contents(path)).hexdigest()');put(p,s)
    # Preserve the existing full account browser suite and add cross-feature checks.
    s=raw(ACCOUNT,'tests/account_security_browser.py').decode()
    anchor=" alice=sign_in('alice@example.invalid',old);stale=sign_in('alice@example.invalid',old);bob=sign_in('bob@example.invalid',old)"
    addition=""" owner_setup=requests.Session();submit(owner_setup,admin_url+'?view=login','login',{'email':'owner@example.invalid','password':'Synthetic owner passphrase 2026'})
 submit(owner_setup,admin_url+'?view=notifications','notifications_setup',{'confirm':'1'})
 check('Combined owner session enables notification storage without another password',db("SELECT value FROM ci_settings WHERE name='notification_feed_version'")==[{'value':'1'}])
 submit(owner_setup,admin_url+'?view=studio-job&id=1','studio_message',{'body':'Synthetic combined account notification'})
 directed=db("SELECT id FROM ci_notification_feed WHERE audience='client' AND recipient_id=1")
 check('Real project message creates the combined private notification',len(directed)==1)
 notification_id=directed[0]['id'];notification_before={t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['notification_feed','notification_deliveries']}
 owner_setup.close()
"""
    s=replace(s,anchor,addition+anchor)
    anchor=" r=alice.get(portal);check('Customer workspace links to account settings',bool(soup(r).select_one('a[href$=\"?account=1\"]')))"
    s=replace(s,anchor,anchor+"\n check('Same customer workspace retains both notification and account navigation',bool(soup(r).select_one('a[href$=\"?notifications=1\"]')) and bool(soup(r).select_one('a[href$=\"?account=1\"]')))\n check('Notification center works alongside account settings',notification_id in alice.get(portal+'?notifications=1').text and notification_id not in bob.get(portal+'?notifications=1').text)\n")
    anchor=" check('Other previously signed-in browser loses account access',soup(stale.get(account)).select_one('[data-client-account]') is None)"
    s=replace(s,anchor,anchor+"\n check('Password rotation revokes the old session from private notifications',notification_id not in stale.get(portal+'?notifications=1').text)\n check('Updated current session retains its own private notification',notification_id in alice.get(portal+'?notifications=1').text)\n check('Credential change does not mutate feed state or delivery history',{t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['notification_feed','notification_deliveries']}==notification_before)\n")
    if sha(s.encode())!='397b3878787a57c3a54383711dce138f8922ab4f6fb2751dde997e76f1cad845':raise AssertionError('Unexpected combined browser output')
    put('tests/account_notification_browser.py',s)
    subprocess.run(['python3','tools/build.py','--refresh'],cwd=ROOT,check=True)
    shipping={}
    for p,expected in EXPECTED.items():
        after=(ROOT/p).read_bytes()
        if sha(after)!=expected:raise AssertionError('Unexpected combined shipping bytes: '+p)
        before=subprocess.run(['git','show',BASE+':'+p],cwd=ROOT,capture_output=True)
        shipping[p]={'before':sha(before.stdout) if before.returncode==0 else None,'after':expected}
    put('docs/ACCOUNT_NOTIFICATION_COMBINATION.json',json.dumps({'base':BASE,'account_source':ACCOUNT,'shipping':shipping,'scope':'Exact tested account implementation integrated additively with notifications. Shared portal conflicts preserve both routes. Combined SQL/browser acceptance still required.'},indent=2)+'\n')
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    print('Reconciled six shipping paths; historical account and notification receipts retained.')
if __name__=='__main__':main()
