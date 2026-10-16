<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/revisions/ContentRevision.php';
require_once CWP_CODE_ROOT.'/includes/foundation/revisions/RevisionDiff.php';
use CajunWebPro\Foundation\Revisions\{ContentRevision,RevisionDiff};

/** Line-level comparison of one revision row's before/after content. */
function studio_revision_diff(array $row): RevisionDiff {
    $id='r'.(int)$row['id'];
    return RevisionDiff::between(
        new ContentRevision($id.'-before',(string)$row['setting_name'],1,(string)$row['old_value'],'staff:'.(int)$row['author_id'],(string)$row['created_at']),
        new ContentRevision($id.'-after',(string)$row['setting_name'],2,(string)$row['new_value'],'staff:'.(int)$row['author_id'],(string)$row['created_at'])
    );
}
function studio_revision_save(string $name,string $value,string $expected): void {
    require_admin();if(!in_array($name,['growth_intro','growth_faq','growth_packages','growth_prices','growth_package_0','growth_package_1','growth_package_2'],true))throw new InvalidArgumentException('Unknown content key.');
    db()->beginTransaction();try {
        // A permanent settings row provides a lock even for previously absent content.
        query('INSERT IGNORE INTO '.table('settings').' (name,value) VALUES (?,?)',[$name,'']);
        $old=(string)query('SELECT value FROM '.table('settings').' WHERE name=? FOR UPDATE',[$name])->fetchColumn();
        if(!hash_equals(hash('sha256',$old),$expected))throw new RuntimeException('Another session changed this content. Reload and review the newer version before saving.');
        query('INSERT INTO '.table('studio_revisions').' (setting_name,old_value,new_value,author_id,created_at) VALUES (?,?,?,?,?)',[$name,$old,$value,(int)$_SESSION['uid'],gmdate('Y-m-d H:i:s')]);
        save_setting($name,$value);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
