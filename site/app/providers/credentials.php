<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Only current-authorized callers holding the staff mutex may open or create provider credentials. */
function provider_settings_vault_key(bool $create=false): string {
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) throw new RuntimeException('Protected provider credentials require PHP OpenSSL.');
    $configured=config()['storage'];$storage=realpath($configured);$root=realpath(CWP_ROOT);
    $document=empty($_SERVER['DOCUMENT_ROOT'])?false:realpath($_SERVER['DOCUMENT_ROOT']);
    if (is_link($configured) || !$storage || !$root || !$document || !is_dir($storage)) throw new RuntimeException('Provider credentials require verified private storage outside the document root.');
    $normal=fn(string $path):string=>strtolower(str_replace('\\','/',rtrim($path,'/\\'))).'/';
    if (str_starts_with($normal($storage),$normal($root)) || str_starts_with($normal($storage),$normal($document))) throw new RuntimeException('Provider credentials require private storage outside the document root. Existing storage was not moved.');
    $path=$storage.'/provider-master.key';clearstatcache(true,$path);
    if (is_link($path) || (file_exists($path) && !is_file($path))) throw new RuntimeException('The provider master key must be a regular private file.');
    if (!is_file($path)) {
        if (!$create || (int)query('SELECT COUNT(*) FROM '.table('provider_profiles')." WHERE credentials<>''")->fetchColumn()>0) throw new RuntimeException('Provider master key unavailable. Restore the original key and database together; saved credentials were not replaced.');
        write_private_file($path,random_bytes(32));
    }
    clearstatcache(true,$path);
    if ((fileperms($path)&0777)!==0600 || filesize($path)!==32) throw new RuntimeException('The provider master key requires intact contents and owner-only permissions.');
    $key=file_get_contents($path);
    if ($key===false || strlen($key)!==32) throw new RuntimeException('Provider master key unavailable.');
    return $key;
}

function provider_settings_key_value(#[\SensitiveParameter] string $value): string {
    if ($value==='' || strlen($value)>4096 || preg_match('//u',$value)!==1 || preg_match('/[\x00-\x1F\x7F]/',$value)) throw new InvalidArgumentException('Enter the raw General API key as nonempty text up to 4,096 bytes without control characters.');
    return $value;
}
function provider_settings_context(string $id,string $environment): string {
    if (!preg_match('/^[a-f0-9]{32}$/D',$id) || $environment!=='live') throw new InvalidArgumentException('Provider profile unavailable.');
    return 'cwp-provider-v1|'.config()['db']['prefix'].'|twenty-i|'.$id.'|'.$environment;
}
function provider_settings_seal(string $id,string $environment,#[\SensitiveParameter] string $secret): string {
    $context=provider_settings_context($id,$environment);$secret=provider_settings_key_value($secret);
    $key=provider_settings_vault_key(true);$iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt(json_encode(['key'=>$secret],JSON_THROW_ON_ERROR),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$context);
    if ($cipher===false || strlen($tag)!==16) throw new RuntimeException('Provider credentials could not be protected.');
    return base64_encode($iv.$tag.$cipher);
}
function provider_settings_unseal(string $id,string $environment,#[\SensitiveParameter] string $encoded): string {
    $context=provider_settings_context($id,$environment);if ($encoded==='') return '';
    $raw=base64_decode($encoded,true);
    if ($raw===false || strlen($raw)<29 || base64_encode($raw)!==$encoded) throw new RuntimeException('Protected provider credentials are damaged.');
    $key=provider_settings_vault_key();
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$context);
    if ($plain===false) throw new RuntimeException('Protected provider credentials could not be opened. Restore the original key and database together.');
    try {
        $values=json_decode($plain,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($values) || array_keys($values)!==['key'] || !is_string($values['key'])) throw new InvalidArgumentException('Invalid protected record.');
        return provider_settings_key_value($values['key']);
    } catch (JsonException|InvalidArgumentException) {throw new RuntimeException('Protected provider credentials have an invalid format.');}
}
