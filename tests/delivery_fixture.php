<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
function d_zip(string $path,array $extra=[],bool $base=true): string {
    $zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
    $entries=($base?['index.html'=>'<!doctype html><title>Client website</title><h1>Delivered website</h1>','HANDOVER.md'=>'Open index.html in a browser. This synthetic site needs no database.','LICENSES.md'=>'Synthetic content created for testing. All example files may be transferred.']:[])+$extra;
    foreach($entries as $name=>$contents){$zip->addFromString($name,$contents);$zip->setCompressionName($name,ZipArchive::CM_STORE);}$zip->close();return $path;
}
