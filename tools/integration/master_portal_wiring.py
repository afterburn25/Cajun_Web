#!/usr/bin/env python3
"""One-shot checked source wiring on the isolated integration branch, never production."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
def sha(raw):return hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest()
PATCHES = [{'path': 'site/includes/layout/admin.php', 'before': 'b649e64badcce40e9ec791bb294b78915976fa57', 'after': 'fa2a96801310bb3dfcb5be86863000523a04f7dd', 'replacements': [('/** Navigation only:', "require_once CWP_CODE_ROOT.'/app/clients/master_portal.php';\n/** Navigation only:"), ('<div class="sidebar-bottom"><span', '<div class="sidebar-bottom"><a href="<?=esc(master_portal_url())?>">Master client portal</a><span')]}, {'path': 'site/admin/controllers/auth.php', 'before': '8369fdf59a827af15dc0117e9d786c2d4418dcae', 'after': '88ea8b9c5faa6df6204743c08854155a48b606d4', 'replacements': [('redirect(admin_url(staff_home($u)));', 'redirect(master_portal_login_destination($u));')]}, {'path': 'site/admin/views/auth.php', 'before': '1d1e5294024f5817da026f6607f4133c9d545199', 'after': '1edcf142e867acf3da823878f8567065c2a31cf1', 'replacements': [('redirect(admin_url(staff_home($signed)));', 'redirect(master_portal_login_destination($signed));'), ('action="<?=esc(admin_url(\'login\'))?>"', 'action="<?=esc(admin_url(\'login\',master_portal_login_query()))?>"'), ('<p>Sign in to manage your website, projects and inquiries.</p>', "<p><?=master_portal_login_query()?'Sign in with your administrator account to open the master client portal.':'Sign in to manage your website, projects and inquiries.'?></p>")]}, {'path': 'site/portal/controllers/router.php', 'before': '4919f9867b055814d99afedd733b48fb5cb66f30', 'after': '1041df7229fa2a7fd4ff7734de77bb85a99c3a1a', 'replacements': [('$client=studio_client();$jobId=', 'master_portal_dispatch();\n$client=studio_client();$jobId=')]}, {'path': 'site/portal/views/auth.php', 'before': '1a745128dedd741e89738504e38e8fab8496ee6b', 'after': '575054b749b536955cd1b0884df54eca6b0d39d3', 'replacements': [('Website owner? <a href="<?=esc(admin_url(\'login\'))?>">Sign in to administration</a>, then open a client portal from Client support. Administrator and client logins remain separate.', 'Administrator? <a href="<?=esc(master_portal_url())?>">Open the master client portal</a> using your administrator sign-in. Customer credentials are not needed for authorized client review.')]}]
def main():
    if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/admin-20i-integration-20261030':raise SystemExit('Isolated integration branch only')
    receipt=ROOT/'docs/MASTER_CLIENT_PORTAL_SOURCE.json'
    if receipt.exists():
        if json.loads(receipt.read_text()).get('schema')!=1:raise SystemExit('Unknown master portal receipt')
        print('Master portal source already integrated');return
    outputs=[]
    for item in PATCHES:
        path=ROOT/item['path'];raw=path.read_bytes()
        if path.is_symlink() or sha(raw)!=item['before']:raise SystemExit('Source drift: '+item['path'])
        text=raw.decode()
        for old,new in item['replacements']:
            if text.count(old)!=1:raise SystemExit('Hook is not unique: '+item['path'])
            text=text.replace(old,new,1)
        data=text.encode()
        if sha(data)!=item['after']:raise SystemExit('Output mismatch: '+item['path'])
        outputs.append((path,data))
    for path,data in outputs:path.write_bytes(data)
    receipt.write_text(json.dumps({'schema':1,'status':'source wired; native/browser/release acceptance required','base_commit':'321ef7a90e1ff46b5fcb208b892f76ec1b04de99','files':[{'path':i['path'],'before':i['before'],'after':i['after']} for i in PATCHES],'policy':'Existing admin authentication; explicit client selection via audited support; no automatic customer identity creation or credential sharing'},indent=2)+'\n')
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
if __name__=='__main__':main()
