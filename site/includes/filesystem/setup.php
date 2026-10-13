<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function acquire_installation_mutex() {
    $path=CWP_ROOT.'/.installation-mutex.php';
    if (is_link($path)) throw new RuntimeException('Installation lock is a symbolic link. Ask the hosting owner to inspect it.');
    $oldMask=umask(0077);
    try { $fp=@fopen($path,'c+b'); } finally { umask($oldMask); }
    if (!$fp) throw new RuntimeException('Cannot create the installation lock. Check the website-directory owner and permissions.');
    if (!flock($fp,LOCK_EX|LOCK_NB)) { fclose($fp); throw new RuntimeException('Another installation is in progress. Wait for it to finish.'); }
    try {
        if (!@chmod($path,0600)) throw new RuntimeException('Cannot secure the installation lock. Ask your host to align file ownership.');
        rewind($fp);
        if (fwrite($fp,CWP_GUARD)!==strlen(CWP_GUARD) || !fflush($fp)) throw new RuntimeException('Cannot write the installation lock. Check available disk space.');
        return $fp;
    } catch (Throwable $e) { flock($fp,LOCK_UN);fclose($fp);throw $e; }
    // The lock inode remains permanently in place. Unlinking a live lock can
    // let another process acquire a different inode and bypass serialization.
}

/** Fixed allowlist: never recursively deletes files or accepts a user path. */

function cleanup_installer_files(): array {
    if (!is_file(CWP_ROOT.'/config.php') && !is_file(CWP_ROOT.'/.installed.php')) {
        throw new RuntimeException('Installer cleanup is only available after installation is confirmed.');
    }
    $deleted=[];
    foreach (['install-key.php', 'install.php', 'assets/install.js', 'assets/install.css', 'setup-1.0.4.php', 'install/bootstrap.generated.php', 'install/inventory.generated.php', 'install/actions/package.php', 'install/actions/storage.php', 'install/actions/checks.php', 'install/actions/cleanup.php', 'install/actions/deploy.php', 'install/controller.php', 'install/views/form.php', 'install/views/styles.php', 'install/views/script.php'] as $file) {
        $path=CWP_ROOT.'/'.$file;
        if (file_exists($path) || is_link($path)) @unlink($path);
        clearstatcache(true,$path);
        $deleted[$file]=!file_exists($path) && !is_link($path);
    }
    return $deleted;
}
