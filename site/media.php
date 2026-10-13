<?php
declare(strict_types=1);
define('CWP_BOOT',true);require __DIR__.'/app/core.php';boot(false);
$id=is_string($_GET['id']??null)?$_GET['id']:'';if(!preg_match('/^[a-f0-9]{32}$/D',$id)){http_response_code(404);exit;}
$m=one('SELECT * FROM '.table('media').' WHERE id=?',[$id]);if(!$m||!preg_match('/^[a-f0-9]{32}\.php$/D',$m['filename'])){http_response_code(404);exit;}
$directory=realpath(config()['storage'].'/media');$path=realpath(config()['storage'].'/media/'.$m['filename']);if(!$directory||!$path||dirname($path)!==$directory||is_link(config()['storage'].'/media/'.$m['filename'])){http_response_code(404);exit;}
$fp=@fopen($path,'rb');if(!$fp){http_response_code(404);exit;}$guard=fread($fp,strlen(CWP_GUARD));if($guard!==CWP_GUARD){fclose($fp);http_response_code(404);exit;}
$etag='"'.$id.'"';header('Content-Type: image/png');header('Cache-Control: public, max-age=31536000, immutable');header('ETag: '.$etag);header('Content-Disposition: inline; filename="cajunwebpro-image.png"');header('X-Content-Type-Options: nosniff');
if(($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag){fclose($fp);http_response_code(304);exit;}
header('Content-Length: '.(int)$m['bytes']);fpassthru($fp);fclose($fp);
