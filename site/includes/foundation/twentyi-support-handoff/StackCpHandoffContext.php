<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** A detached local review document, never proof of current DB or remote authority. */
final readonly class StackCpHandoffContext
{
    public const OPERATION = 'stackcp.support-handoff';
    public array $data;
    public string $json;
    public string $sha256;
    public string $scope;

    public function __construct(array $data)
    {
        $keys = ['package','actor_id','actor_auth_version','client_auth_version','auth_credential_id',
            'auth_revision','auth_credential_sha256','stack_user_identity','membership_source_id',
            'membership_source_seq','membership_source_sha256','grants_source_id','grants_source_seq',
            'grants_source_sha256','observed_grants','observed_at','permission_id','permission_revision','permission_sha256'];
        self::keys($data, $keys);
        if (!is_array($data['package'])) self::deny();
        $packageKeys = ['profile_id','profile_revision','key_fingerprint','credential_sha256','package_id','client_id',
            'link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','source_id','source_seq','source_sha256'];
        self::keys($data['package'], $packageKeys);
        foreach (['profile_id','link_id','source_id'] as $k) self::hex($data['package'][$k], 32);
        foreach (['key_fingerprint','credential_sha256','link_sha256','event_sha256','audit_sha256','source_sha256'] as $k) self::hex($data['package'][$k], 64);
        foreach (['profile_revision','client_id','event_id','audit_id','source_seq'] as $k) self::positive($data['package'][$k]);
        self::positive($data['package']['package_id']);
        if ($data['package']['package_id'] > 999999999999999999) self::deny();
        foreach (['actor_id','auth_revision','membership_source_seq','grants_source_seq','permission_revision'] as $k) self::positive($data[$k]);
        foreach (['actor_auth_version','client_auth_version','auth_credential_id','membership_source_id','grants_source_id','permission_id'] as $k) self::hex($data[$k], 32);
        foreach (['auth_credential_sha256','membership_source_sha256','grants_source_sha256','permission_sha256'] as $k) self::hex($data[$k], 64);
        if (!is_int($data['observed_at']) || $data['observed_at'] < 0
            || !is_string($data['stack_user_identity'])
            || preg_match('/\Astack-user:[1-9][0-9]{0,17}\z/', $data['stack_user_identity']) !== 1) self::deny();
        // This is only a restrictive admission policy on a recorded snapshot. It does
        // NOT narrow an OAuth token or promise a package-only provider session.
        if ($data['observed_grants'] !== ['package:'.$data['package']['package_id']]) self::deny();
        $data['package'] = array_replace(array_fill_keys($packageKeys, null), $data['package']);
        $canonical = ['operation'=>self::OPERATION,'actor_kind'=>'owner','provider_session_scope'=>'whole-stack-user',
            'remote_scope_restricted'=>false,'review'=>array_replace(array_fill_keys($keys, null), $data)];
        $this->json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        // Rebuild from bytes rather than retaining references in a readonly array.
        $this->data = json_decode($this->json, true, 8, JSON_THROW_ON_ERROR);
        $this->sha256 = hash('sha256', $this->json);
        $this->scope = 'twenty-i|'.$this->data['review']['package']['profile_id'].'|'.self::OPERATION.'|'.$this->sha256;
    }

    public static function validated(self $context): self
    {
        if (!isset($context->data, $context->json, $context->sha256, $context->scope)
            || !is_array($context->data['review'] ?? null)) self::deny();
        $copy = new self($context->data['review']);
        if ($copy->data !== $context->data || $copy->json !== $context->json
            || !hash_equals($copy->sha256, $context->sha256) || $copy->scope !== $context->scope) self::deny();
        return $copy;
    }

    private static function keys(array $data, array $keys): void
    {
        $actual = array_keys($data); sort($actual); sort($keys);
        if ($actual !== $keys) self::deny();
    }
    private static function positive(mixed $value): void { if (!is_int($value) || $value < 1) self::deny(); }
    private static function hex(mixed $value, int $size): void
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{'.$size.'}\z/', $value) !== 1) self::deny();
    }
    private static function deny(): never { throw new \InvalidArgumentException('Invalid StackCP handoff review context.'); }
}
