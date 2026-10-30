#!/usr/bin/env python3
"""Hash-bound integration corrections. No production, SQL or external requests."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
PATCHES_JSON=r'''[
  {
    "path": "site/app/licenses/service.php",
    "before": "43c7e692ee47ac28d0a1e86a3eaeafd369667c2c",
    "after": "26f4d721561d9c1d04107c8b8764678132e568e9",
    "edits": [
      [
        " * Deactivate an entire license. Financing payoff removes only payment-based\n * suspension; it never overrides an explicit owner license-control decision.",
        " * Deactivate an unpaid or unfinanced license. Completed website financing is\n * permanently unlocked; neither billing nor a later owner action may disable it."
      ],
      [
        "function license_deactivate(int $licenseId, int $actor = 0): void {\n    license_transaction(function() use ($licenseId, $actor): void {\n        license_lock($licenseId);",
        "function license_deactivate(int $licenseId, int $actor = 0): void {\n    license_transaction(function() use ($licenseId, $actor): void {\n        license_lock($licenseId);\n        if (license_paid_off_lock($licenseId)) {\n            throw new RuntimeException('This financed license is permanently unlocked after full payoff and cannot be deactivated.');\n        }"
      ]
    ]
  },
  {
    "path": "site/admin/views/studio/licenses.php",
    "before": "9481274d54e1fce6dbaaf4827470eeba99288ad0",
    "after": "ba57d6acb23fac4e18e6f2e21d428af0def390a0",
    "edits": [
      [
        "<p class=\"hint\"><strong>Financing permanently paid:</strong> payment status can no longer suspend this license. Owner deactivation, domain binding and commercial-rights restrictions still apply independently.</p>\n<?php endif; ?><form method=\"post\"><?php admin_action('studio_license_deactivate'); ?><input type=\"hidden\" name=\"id\" value=\"<?=esc($lic['id'])?>\"><button class=\"btn outline\">Deactivate license</button></form>",
        "<p class=\"hint\"><strong>Financing permanently paid:</strong> payment status can no longer suspend this license, and owner deactivation is blocked. Domain binding and commercial-rights restrictions remain in effect.</p>\n<?php else: ?><form method=\"post\"><?php admin_action('studio_license_deactivate'); ?><input type=\"hidden\" name=\"id\" value=\"<?=esc($lic['id'])?>\"><button class=\"btn outline\">Deactivate license</button></form><?php endif; ?>"
      ]
    ]
  },
  {
    "path": "tests/license_financing_native_cases.php",
    "before": "a2119c361c3dc1e5ff53552ad625eca83d14a7cd",
    "after": "965bda125e37594665130b9f593606dd29086dad",
    "edits": [
      [
        "license_deactivate($licenseId,1);\nrb_ok('Paid-off financing cannot override a later owner deactivation',\n    (int)license_get($licenseKey)['is_active']===0\n    &&license_financing_authorized($licenseId)===true);\nlicense_reactivate($licenseId,1);",
        "$paidBefore=license_get($licenseKey);$paidPlanBefore=license_financing_plan($licenseId);\n$paidAuditBefore=rb_count('audit');$paidDeactivateBlocked=false;\ntry{license_deactivate($licenseId,1);}catch(RuntimeException $expected){$paidDeactivateBlocked=str_contains($expected->getMessage(),'permanently unlocked');}\nrb_ok('Paid-off license refuses later owner deactivation without changing key state or audit',\n    $paidDeactivateBlocked&&license_get($licenseKey)===$paidBefore\n    &&license_financing_plan($licenseId)===$paidPlanBefore&&rb_count('audit')===$paidAuditBefore\n    &&(int)license_get($licenseKey)['is_active']===1&&license_financing_authorized($licenseId)===true);"
      ]
    ]
  },
  {
    "path": "tests/license_financing_cases.py",
    "before": "57f50aa0eec92f198d3f87653b1aa7cad753067e",
    "after": "4b7c0f8174c9aa38af07f0ad06159179fa0d33e7",
    "edits": [
      [
        "    check('Paid-off owner UI preserves license control while preventing standalone domain removal',\n          len(paid_forms)==1 and not paid_domain_forms\n          and 'payment status can no longer suspend this license' in owner_paid.text.lower())\n    chosen_form(admin,licenses,'studio_license_deactivate','id',license_id,{})\n    owner_disabled=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)\n    check('Owner deactivation remains authoritative after financing payoff',\n          owner_disabled.status_code==200\n          and owner_disabled.json()=={'authorized':False,'error':'License is deactivated'}\n          and env['db']('SELECT is_active FROM ci_website_licenses WHERE id=?',[license_id])==[{'is_active':0}])\n    chosen_form(admin,licenses,'studio_license_reactivate','id',license_id,{})\n    check('Owner reactivation restores the same paid-off license without changing its key',\n          env['db']('SELECT license_key,is_active FROM ci_website_licenses WHERE id=?',[license_id])\n              ==[{'license_key':license_key,'is_active':1}])",
        "    check('Paid-off owner UI prevents deactivation and standalone domain removal',\n          not paid_forms and not paid_domain_forms\n          and 'owner deactivation is blocked' in owner_paid.text.lower())\n    paid_before=env['db']('SELECT * FROM ci_website_licenses WHERE id=?',[license_id])\n    payoff_before=env['db']('SELECT * FROM ci_license_financing WHERE license_id=?',[license_id])\n    refused=admin.post(licenses,data={\n        'csrf':soup(owner_paid).select_one('[name=csrf]').get('value'),\n        'action':'studio_license_deactivate','id':str(license_id)},timeout=25)\n    owner_disabled=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)\n    check('Forged owner deactivation request cannot disable a permanently paid license',\n          'permanently unlocked after full payoff' in refused.text\n          and owner_disabled.status_code==200 and owner_disabled.json().get('authorized') is True\n          and env['db']('SELECT * FROM ci_website_licenses WHERE id=?',[license_id])==paid_before\n          and env['db']('SELECT * FROM ci_license_financing WHERE license_id=?',[license_id])==payoff_before)\n    check('Refused owner deactivation preserves the same active paid-off key',\n          env['db']('SELECT license_key,is_active FROM ci_website_licenses WHERE id=?',[license_id])\n              ==[{'license_key':license_key,'is_active':1}])"
      ]
    ]
  },
  {
    "path": "site/admin/controllers/support.php",
    "before": "ee88f4638124776461fd1e6998de1416fcee02fc",
    "after": "6c21e136e88b7f9f86f0ea0c515d0257f6fb2315",
    "edits": [
      [
        "<?php admin_action('support_start');studio_hidden('client_id',$clientId);?>",
        "<?php admin_action('support_start');?><input type=\"hidden\" name=\"client_id\" value=\"<?=(int)$clientId?>\">"
      ]
    ]
  }
]'''
PATCHES_SHA='063de1708a33301b9134a82c809c5d98e4e593eba3f2d8e150012ebe98a4b3eb'
def blob(data):return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()
def main():
    if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/admin-20i-integration-20261030':raise SystemExit('Isolated integration branch only')
    if hashlib.sha256(PATCHES_JSON.encode()).hexdigest()!=PATCHES_SHA:raise SystemExit('Correction recipe changed')
    receipt=ROOT/'docs/ADMIN_RELEASE_FIXES_SOURCE.json'
    if receipt.exists():
        if json.loads(receipt.read_text()).get('recipe_sha256')!=PATCHES_SHA:raise SystemExit('Unknown correction receipt')
        print('Admin release corrections already recorded');return
    outputs=[];records=[]
    for item in json.loads(PATCHES_JSON):
        path=ROOT/item['path']
        if not item['path'].startswith(('site/','tests/')) or '..' in Path(item['path']).parts:raise SystemExit('Unsafe correction path')
        if path.is_symlink() or any(x.is_symlink() for x in path.parents):raise SystemExit('Symlink correction refused')
        raw=path.read_bytes()
        if blob(raw)!=item['before']:raise SystemExit('Source drift: '+item['path'])
        text=raw.decode('utf-8')
        for old,new in item['edits']:
            if text.count(old)!=1:raise SystemExit('Correction is not unique: '+item['path'])
            text=text.replace(old,new,1)
        result=text.encode('utf-8')
        if blob(result)!=item['after']:raise SystemExit('Correction output mismatch: '+item['path'])
        outputs.append((path,result));records.append({k:item[k] for k in ['path','before','after']})
    for path,data in outputs:path.write_bytes(data)
    receipt.write_text(json.dumps({'schema':1,'recipe_sha256':PATCHES_SHA,'files':records,'status':'Source corrections recorded; exact-source installed and finance acceptance required','scope':'Self-contained client-review form; deny new owner deactivation after full financing payoff; retain customer identity, domain/resale rights, unpaid controls and existing recovery'},indent=2)+'\n')
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
if __name__=='__main__':main()
