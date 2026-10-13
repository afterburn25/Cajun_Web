<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
/** Staging lives under the configured store. Never use the public upload scaffold. */
function upload_staging_directory(): string {
    $storage=realpath(config()['storage']);
    if (!$storage || is_link(config()['storage'])) throw new RuntimeException('Private storage is unavailable.');
    $dir=$storage.'/upload';
    if (is_link($dir) || (file_exists($dir) && !is_dir($dir))) throw new RuntimeException('Upload staging needs owner attention.');
    if (!is_dir($dir) && !@mkdir($dir,0700)) throw new RuntimeException('Cannot create private upload staging.');
    if (!@chmod($dir,0700)) throw new RuntimeException('Cannot protect upload staging.');
    foreach (['.htaccess'=>"Require all denied\n",'index.php'=>"<?php http_response_code(404); exit;\n"] as $name=>$bytes) {
        $file=$dir.'/'.$name;
        if (is_link($file)) throw new RuntimeException('Unexpected staging protection link.');
        if (!file_exists($file)) write_private_file($file,$bytes);
        elseif (file_get_contents($file)!==$bytes) throw new RuntimeException('Unexpected staging protection file.');
    }
    return $dir;
}
/** Guarded, short-lived copy, removed in finally by callers; original upload remains untouched. */
function with_staged_upload(string $bytes, callable $consume): mixed {
    $path=upload_staging_directory().'/'.bin2hex(random_bytes(16)).'.php';
    write_private_file($path,CWP_GUARD.$bytes);
    try { return $consume($path); } finally { if (is_file($path) && !is_link($path)) @unlink($path); }
}
