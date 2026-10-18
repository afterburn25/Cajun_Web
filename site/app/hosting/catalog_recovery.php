<?php
/** Guard owner file recovery; this never changes hosting schema or retained history. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once __DIR__.'/catalog_offers_storage.php';
require_once CWP_CODE_ROOT.'/app/updates/structure.php';

/** Caller owns the existing updater file lock and has checked CSRF and backup confirmation. */
function hosting_catalog_recover_files(string $directory,int $ownerId,string $ownerAuthVersion,#[\SensitiveParameter] string $password): array {
    // A pre-existing repeatable-read snapshot could hide a publication committed before this lock.
    if (db()->inTransaction()) throw new LogicException('File recovery cannot run inside a database transaction.');
    return staff_locked(function()use($directory,$ownerId,$ownerAuthVersion,$password):array {
        // Re-read current authority after waiting for the same lock as publication and account changes.
        // The pre-lock owner lookup may have cached a previously absent staff marker.
        unset($GLOBALS['cwp_settings']);
        $current=admin_user();
        if (!$current || $current['role']!=='owner' || (int)$current['id']!==$ownerId
            || !hash_equals($ownerAuthVersion,(string)$current['auth_version'])
            || !password_verify($password,$current['password_hash'])) {
            throw new RuntimeException('Your current owner account and password are required for file recovery.');
        }
        $ready=hosting_catalog_ready();
        // Refuse unmarked partial storage and unknown future tables, including empty ones.
        // Prefix comparison is literal: underscores in an installation prefix are not wildcards.
        $prefix=config()['db']['prefix'];$namespace=$prefix.'hosting_catalog_';
        $actual=array_column(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen($namespace),$namespace]),'TABLE_NAME');
        $expected=$ready?array_map(static fn($name)=>$prefix.$name,hosting_catalog_tables()):[];
        sort($actual,SORT_STRING);sort($expected,SORT_STRING);
        if ($actual!==$expected) {
            throw new RuntimeException('Hosting offer storage is incomplete or unrecognized. Keep the current files and restore or review its original history before file recovery.');
        }
        if ($ready && hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('hosting_catalog_publications'))->fetchColumn(),999999999)!==0) {
            // Every currently recognized old inventory lacks hosting-history image retention.
            // There is deliberately no version cutoff, module probe or future-target exemption.
            throw new RuntimeException('Published hosting offers need the current website files to preserve their history and images. Automatic file recovery is blocked. Restore a matching backup of website files, database and private storage with your hosting provider if recovery is needed.');
        }
        // Keep the staff mutex across all file and policy changes: no new publication can
        // commit between the history check and restoration of the original .htaccess.
        return structure_recover($directory);
    });
}
