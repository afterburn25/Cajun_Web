<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Narrow credential boundary. Production construction always uses the protected master-key guard.
 * Injectable crypto functions permit no-decrypt assertions without weakening that guard.
 * No instance or decrypted value is accepted from HTTP input or returned by status adapters.
 */
final class ProviderStackCpAuthVault {
    private Closure $master;
    private Closure $encrypt;
    private Closure $decrypt;
    private ?string $cachedMaster=null;
    public function __construct(callable $master, ?callable $encrypt=null, ?callable $decrypt=null) {
        $this->master=Closure::fromCallable($master);
        $this->encrypt=Closure::fromCallable($encrypt ?? static function(string $plain,string $key,string $iv,string $aad):array {
            $tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$aad);
            if ($cipher===false || strlen($tag)!==16) throw new RuntimeException('StackCP authentication key could not be protected.');
            return [$cipher,$tag];
        });
        $this->decrypt=Closure::fromCallable($decrypt ?? static fn(string $cipher,string $key,string $iv,string $tag,string $aad)=>openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$aad));
    }
    public static function keyValue(#[SensitiveParameter] string $secret): string {
        if ($secret==='' || strlen($secret)>4096 || preg_match('//u',$secret)!==1 || preg_match('/[\x00-\x20\x7f]/',$secret)) throw new InvalidArgumentException('Enter the separate OAuth client key as nonempty text up to 4,096 bytes without whitespace or control characters.');
        return $secret;
    }
    public static function context(string $prefix,string $profileId,string $id,int $revision): string {
        if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix)!==1 || $revision<1 || $revision>2147483647) throw new InvalidArgumentException('Invalid StackCP authentication context.');
        provider_stackcp_hex($profileId);provider_stackcp_hex($id);
        return 'cwp-stackcp-auth-client-v1|'.$prefix.'|twenty-i|'.$profileId.'|'.$id.'|'.$revision.'|live';
    }
    private function key(): string {
        if ($this->cachedMaster!==null) return $this->cachedMaster;
        $key=($this->master)();if (!is_string($key) || strlen($key)!==32) throw new RuntimeException('StackCP history master key is unavailable.');return $this->cachedMaster=$key;
    }
    public function proof(string $kind,string $document): string { return hash_hmac('sha256','cwp-stackcp-prerequisite-v1|'.$kind.'|'.$document,$this->key()); }
    public function seal(string $context,#[SensitiveParameter] string $secret): string {
        $plain=provider_stackcp_json(['key'=>self::keyValue($secret)]);$iv=random_bytes(12);[$cipher,$tag]=($this->encrypt)($plain,$this->key(),$iv,$context);
        if (!is_string($cipher)||!is_string($tag)||strlen($tag)!==16) throw new RuntimeException('StackCP authentication key could not be protected.');
        return base64_encode($iv.$tag.$cipher);
    }
    /** Internal boundary only; the prerequisite status/save/disable adapters never call this. */
    public function open(string $context,#[SensitiveParameter] string $encoded): string {
        $raw=base64_decode($encoded,true);
        if ($raw===false || strlen($raw)<29 || base64_encode($raw)!==$encoded) throw new RuntimeException('Protected StackCP authentication is damaged.');
        $plain=($this->decrypt)(substr($raw,28),$this->key(),substr($raw,0,12),substr($raw,12,16),$context);
        if (!is_string($plain)) throw new RuntimeException('Protected StackCP authentication could not be opened.');
        try {$d=json_decode($plain,true,4,JSON_THROW_ON_ERROR);if (!is_array($d)||array_keys($d)!==['key']||!is_string($d['key'])) throw new InvalidArgumentException();return self::keyValue($d['key']);}
        catch (JsonException|InvalidArgumentException) {throw new RuntimeException('Protected StackCP authentication has an invalid format.');}
    }
    public function __debugInfo(): array {return ['credential_boundary'=>'protected'];}
    public function __serialize(): array {throw new LogicException('StackCP credential boundaries cannot be serialized.');}
    public function __unserialize(array $data): void {throw new LogicException('StackCP credential boundaries cannot be restored.');}
    private function __clone() {}
}
function provider_stackcp_vault(): ProviderStackCpAuthVault {return new ProviderStackCpAuthVault(static fn()=>provider_settings_vault_key());}
