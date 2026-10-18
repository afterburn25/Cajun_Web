<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function registration_drafts_key(bool $create=false):string {
    if(!function_exists('openssl_encrypt')||!function_exists('openssl_decrypt'))throw new RuntimeException('Private registration drafts require PHP OpenSSL.');
    $configured=config()['storage'];$storage=realpath($configured);$root=realpath(CWP_ROOT);$document=empty($_SERVER['DOCUMENT_ROOT'])?false:realpath($_SERVER['DOCUMENT_ROOT']);
    $normal=static fn(string $path):string=>strtolower(str_replace('\\','/',rtrim($path,'/\\'))).'/';
    if(is_link($configured)||!$storage||!$root||!$document||!is_dir($storage)||str_starts_with($normal($storage),$normal($root))||str_starts_with($normal($storage),$normal($document)))throw new RuntimeException('Registration drafts require verified private storage outside the website.');
    $path=$storage.'/registration-master.key';clearstatcache(true,$path);
    if(is_link($path)||(file_exists($path)&&!is_file($path)))throw new RuntimeException('The registration key must be a regular private file.');
    if(!is_file($path)){
        if(!$create||registration_drafts_has_history())throw new RuntimeException('Registration key unavailable. Restore the original key and draft history together.');
        foreach(registration_drafts_tables() as $name=>$exists)if($exists&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)throw new RuntimeException('Registration history needs recovery before key setup.');
        write_private_file($path,random_bytes(32));
    }
    clearstatcache(true,$path);
    if((fileperms($path)&0777)!==0600||filesize($path)!==32)throw new RuntimeException('The registration key requires intact contents and owner-only permissions.');
    $key=file_get_contents($path);if($key===false||strlen($key)!==32)throw new RuntimeException('Registration key unavailable.');return $key;
}
function registration_drafts_aad(string $draft,int $client,int $revision,string $command):string {
    registration_drafts_id($draft);registration_drafts_id($command);
    if($client<1||$client>4294967295||$revision<1||$revision>256)throw new InvalidArgumentException('Invalid protected draft context.');
    return 'cwp-registration-v1|'.config()['db']['prefix'].'|'.$draft.'|'.$client.'|'.$revision.'|'.$command;
}
function registration_drafts_encrypt(#[SensitiveParameter] string $plain,#[SensitiveParameter] string $key,string $aad):string {
    if(strlen($key)!==32||strlen($plain)>32768)throw new InvalidArgumentException('Invalid protected draft.');
    $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag,$aad,16);
    if($cipher===false||strlen($tag)!==16)throw new RuntimeException('The private draft could not be saved.');return base64_encode($nonce.$tag.$cipher);
}
function registration_drafts_decrypt(#[SensitiveParameter] string $encoded,#[SensitiveParameter] string $key,string $aad):string {
    $raw=base64_decode($encoded,true);
    if(strlen($key)!==32||strlen($encoded)>44000||$raw===false||strlen($raw)<29||base64_encode($raw)!==$encoded)throw new RuntimeException('Protected registration draft needs recovery.');
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$aad);
    if($plain===false||strlen($plain)>32768)throw new RuntimeException('Protected registration draft needs recovery.');return $plain;
}
