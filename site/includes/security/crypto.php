<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function encrypt_secret(string $text): string { if($text==='') return ''; $iv=random_bytes(12); $key=hash('sha256',config()['key'],true); $cipher=openssl_encrypt($text,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag); if($cipher===false) throw new RuntimeException('Cannot protect the credential.'); return base64_encode($iv.$tag.$cipher); }

function decrypt_secret(string $encoded): string { if($encoded==='') return ''; $raw=base64_decode($encoded,true); if($raw===false || strlen($raw)<28) throw new RuntimeException('Invalid stored credential.'); $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',config()['key'],true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16)); if($plain===false) throw new RuntimeException('Could not decrypt the saved credential.'); return $plain; }
