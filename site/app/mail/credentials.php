<?php
/** Protected mailbox passwords. Callers authorize/serialize account changes first. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_accounts_vault_key(bool $create=false): string {
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
        throw new RuntimeException('Protected email credentials require the PHP OpenSSL extension. Ask your hosting administrator to enable it.');
    }
    $configured=config()['storage'];$storage=realpath($configured);$root=realpath(CWP_ROOT);
    $document=empty($_SERVER['DOCUMENT_ROOT'])?false:realpath($_SERVER['DOCUMENT_ROOT']);
    if (is_link($configured) || !$storage || !$root || !$document || !is_dir($storage)) {
        throw new RuntimeException('Email credentials require verified private storage outside the document root. Existing storage was not moved.');
    }
    $normal=fn(string $path): string=>strtolower(str_replace('\\','/',rtrim($path,'/\\'))).'/';
    if (str_starts_with($normal($storage),$normal($root)) || str_starts_with($normal($storage),$normal($document))) {
        throw new RuntimeException('Email credentials require private storage outside the document root. Ask your hosting administrator to configure it.');
    }
    $path=$storage.'/mail-master.key';
    clearstatcache(true,$path);
    if (is_link($path) || (file_exists($path) && !is_file($path))) throw new RuntimeException('The email master key must be a regular private file.');
    if (!is_file($path)) {
        if (!$create || (int)query('SELECT COUNT(*) FROM '.table('mail_accounts')." WHERE credentials<>''")->fetchColumn()>0) {
            throw new RuntimeException('Email master key unavailable. Restore the original key and database together; saved credentials were not replaced.');
        }
        write_private_file($path,random_bytes(32));
    }
    clearstatcache(true,$path);
    if ((fileperms($path)&0777)!==0600 || filesize($path)!==32) throw new RuntimeException('The email master key requires intact contents and owner-only permissions.');
    $key=file_get_contents($path);
    if ($key===false || strlen($key)!==32) throw new RuntimeException('Email master key unavailable.');
    return $key;
}

function mail_accounts_passwords(#[\SensitiveParameter] array $values): array {
    if (array_keys($values)!==['imap_password','smtp_password']) throw new InvalidArgumentException('Enter both incoming and outgoing account passwords.');
    foreach ($values as $value) {
        if (!is_string($value) || $value==='' || strlen($value)>1024 || !preg_match('//u',$value) || preg_match('/[\x00-\x1F\x7F]/',$value)) {
            throw new InvalidArgumentException('Account passwords must be nonempty text, at most 1,024 bytes, without control characters.');
        }
    }
    return $values;
}

function mail_accounts_vault_context(string $id): string {
    if (!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new InvalidArgumentException('Email account unavailable.');
    return 'cwp-mail-v1|'.config()['db']['prefix'].'|'.$id.'|passwords';
}

function mail_accounts_seal(string $id,#[\SensitiveParameter] array $values): string {
    $context=mail_accounts_vault_context($id);$values=mail_accounts_passwords($values);
    $key=mail_accounts_vault_key(true);$iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt(json_encode($values,JSON_THROW_ON_ERROR),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$context);
    if ($cipher===false || strlen($tag)!==16) throw new RuntimeException('Email credentials could not be protected.');
    return base64_encode($iv.$tag.$cipher);
}

function mail_accounts_unseal(string $id,#[\SensitiveParameter] string $encoded): array {
    $context=mail_accounts_vault_context($id);
    if ($encoded==='') return ['imap_password'=>'','smtp_password'=>''];
    $raw=base64_decode($encoded,true);
    if ($raw===false || strlen($raw)<29) throw new RuntimeException('Protected email credentials are damaged.');
    $key=mail_accounts_vault_key();
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$context);
    if ($plain===false) throw new RuntimeException('Protected email credentials could not be opened. Restore the original key and database together.');
    try {
        $values=json_decode($plain,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($values)) throw new InvalidArgumentException('Invalid protected record.');
        return mail_accounts_passwords($values);
    } catch (JsonException|InvalidArgumentException $e) {
        throw new RuntimeException('Protected email credentials have an invalid format.');
    }
}
