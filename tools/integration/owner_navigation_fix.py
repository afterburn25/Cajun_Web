#!/usr/bin/env python3
"""Retain the release assertion while checking current labels and exact destinations."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BRANCH='refs/heads/chatgpt/account-notifications-combined-20261101'
BEFORE={'tests/release_cases.py':'807837a2d42796a8b3661c3fe5da647a4a6f88fc75c25a5a81c42c35943b41af','tests/core_browser_fixture_provenance.py':'d191618e2aab59b8fc228fb791a72188adc8e31babf1619b82f20831b1c4c110','docs/CORE_BROWSER_FIXTURE_REPAIRS.json':'ae7fa6654ce7f8aa7cc58dc7c3c35379b9aedd6d27dd78914ca760dede408883','tests/account_notification_browser.py':'397b3878787a57c3a54383711dce138f8922ab4f6fb2751dde997e76f1cad845'}
AFTER={'tests/release_cases.py':'178bccbd9538de1e7d47e51659225f61c7e4324effdedc2b0a311001d36281e3','tests/core_browser_fixture_provenance.py':'489603afc4cdf1cf3a8ffd08329df93af77fee336904b10ff32bdadcf858ef71','docs/CORE_BROWSER_FIXTURE_REPAIRS.json':'f7aca2b087bbf43fb26be0e158d9b7945819f07ce46061191bed38e7b7ae4bbf','tests/account_notification_browser.py':'2ec0b157e687ef38b1cd66426b6be8f88212299374e4a65cd63584029aefe344'}
def digest(value):return hashlib.sha256(value).hexdigest()
def replace(text,old,new):
    if text.count(old)!=1:raise AssertionError('Navigation correction anchor changed')
    return text.replace(old,new,1)
def main():
    if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated combined review branch only')
    source={}
    for p in BEFORE:
        f=ROOT/p
        if f.is_symlink():raise SystemExit('Symlink fixture refused')
        source[p]=f.read_bytes()
    if all(digest(source[p])==AFTER[p] for p in AFTER):print('Exact navigation correction already present');return
    if any(digest(source[p])!=BEFORE[p] for p in BEFORE):raise SystemExit('Unexpected predecessor fixture')
    old="    check('Grouped owner navigation retains core destinations',all(admin_nav.get_by_role('link',name=label,exact=True,include_hidden=True).count()==1 for label in ['Overview','Service catalog','20i connection','Administrators']))"
    new="    from owner_navigation_contract import core_admin_destinations\n    check('Grouped owner navigation retains core destinations',core_admin_destinations(p))"
    outputs={};outputs['tests/release_cases.py']=replace(source['tests/release_cases.py'].decode(),old,new).encode()
    receipt=json.loads(source['docs/CORE_BROWSER_FIXTURE_REPAIRS.json']);receipt['files']['tests/release_cases.py']={'before_sha256':BEFORE['tests/release_cases.py'],'after_sha256':AFTER['tests/release_cases.py'],'edits':[[old,new]]}
    outputs['docs/CORE_BROWSER_FIXTURE_REPAIRS.json']=(json.dumps(receipt,indent=2)+'\n').encode()
    text=replace(source['tests/core_browser_fixture_provenance.py'].decode(),"'notifications','ledger','delivery','activity'))","'notifications','ledger','delivery','activity','release'))")
    outputs['tests/core_browser_fixture_provenance.py']=replace(text,'All nine original browser families reconstruct exactly.','All ten original browser families reconstruct exactly.').encode()
    old="  ctx.route('**/*',route);ctx.add_cookies("
    new="""  # Reuse the exact full-release navigation assertion with a separate real owner browser.
  admin_ctx=browser.new_context(viewport={'width':1440,'height':1050});admin_ctx.route('**/*',route);admin_page=admin_ctx.new_page();admin_page.on('pageerror',lambda e:errors.append(str(e)))
  admin_page.goto(admin_url+'?view=login');admin_page.get_by_label('Email address',exact=True).fill('owner@example.invalid');admin_page.get_by_label('Password',exact=True).fill('Synthetic owner passphrase 2026')
  with admin_page.expect_navigation():admin_page.locator('form button').first.click()
  from owner_navigation_contract import core_admin_destinations
  check('Actual owner navigation retains named same-origin destinations',core_admin_destinations(admin_page))
  group=admin_page.locator('[data-admin-navigation] [data-nav-group=hosting]');group.locator('summary').click()
  check('20i workspace remains visible in expanded Hosting and Domains',group.get_by_role('link',name='20i workspace',exact=True).is_visible())
  with admin_page.expect_navigation():group.get_by_role('link',name='20i workspace',exact=True).click()
  check('Actual 20i navigation opens the protected workspace',parse_qs(urlsplit(admin_page.url).query)=={'view':['provider-settings']} and admin_page.locator('[data-provider-hub]').count()==1 and core_admin_destinations(admin_page))
  admin_page.screenshot(path=str(QA/'Owner-20i-Navigation.png'),full_page=True);admin_ctx.close()
  ctx.route('**/*',route);ctx.add_cookies("""
    outputs['tests/account_notification_browser.py']=replace(source['tests/account_notification_browser.py'].decode(),old,new).encode()
    for p,v in outputs.items():
        if digest(v)!=AFTER[p]:raise SystemExit('Unexpected corrected output '+p)
    for p,v in outputs.items():(ROOT/p).write_bytes(v)
    print('Expanded exact four-file navigation test correction; no application change')
if __name__=='__main__':main()
