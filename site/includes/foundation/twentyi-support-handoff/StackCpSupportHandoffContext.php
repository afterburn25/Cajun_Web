<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Detached support review; the staff actor is distinct from the client and delegating owner. The native caller must authenticate delegation, current authority and read witnesses. */
final readonly class StackCpSupportHandoffContext
{
    public const OPERATION = 'stackcp.staff-support-handoff';
    public array $data;
    public string $json;
    public string $sha256;
    public string $scope;

    public function __construct(array $data)
    {
        $keys = ['package','staff_id','staff_auth_version','support_id','support_binding_sha256','support_expires_epoch','client_id','client_auth_version','delegation_id','delegation_revision','delegation_sha256',
            'delegation_owner_id','delegation_owner_auth_version','auth_credential_id','auth_revision','auth_credential_sha256',
            'stack_user_identity','membership_source_id','membership_source_seq','membership_source_sha256',
            'grants_source_id','grants_source_seq','grants_source_sha256','observed_grants','observed_at',
            'permission_id','permission_revision','permission_sha256'];
        self::keys($data, $keys);
        if (!is_array($data['package'])) self::deny();
        $packageKeys = ['profile_id','profile_revision','key_fingerprint','credential_sha256','package_id','client_id',
            'link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','source_id','source_seq','source_sha256'];
        self::keys($data['package'], $packageKeys);
        foreach (['profile_id','link_id','source_id'] as $key) self::hex($data['package'][$key], 32);
        foreach (['key_fingerprint','credential_sha256','link_sha256','event_sha256','audit_sha256','source_sha256'] as $key) self::hex($data['package'][$key], 64);
        foreach (['profile_revision','client_id','event_id','audit_id','source_seq','package_id'] as $key) self::positive($data['package'][$key]);
        if ($data['package']['package_id'] > 999999999999999999) self::deny();
        foreach (['staff_id','support_expires_epoch','client_id','delegation_revision','delegation_owner_id','auth_revision','membership_source_seq','grants_source_seq','permission_revision'] as $key) self::positive($data[$key]);
        foreach (['staff_auth_version','support_id','client_auth_version','delegation_id','delegation_owner_auth_version','auth_credential_id','membership_source_id','grants_source_id','permission_id'] as $key) self::hex($data[$key], 32);
        foreach (['support_binding_sha256','delegation_sha256','auth_credential_sha256','membership_source_sha256','grants_source_sha256','permission_sha256'] as $key) self::hex($data[$key], 64);
        if ($data['client_id'] !== $data['package']['client_id'] || !is_int($data['observed_at']) || $data['observed_at'] < 0
            || $data['observed_at'] >= $data['support_expires_epoch']
            || !is_string($data['stack_user_identity'])
            || preg_match('/\Astack-user:[1-9][0-9]{0,17}\z/', $data['stack_user_identity']) !== 1) self::deny();
        // The native admission must prove the fresh complete reads. This local singleton
        // policy does not narrow the whole-user token or prevent later remote grant changes.
        if ($data['observed_grants'] !== ['package:'.$data['package']['package_id']]) self::deny();
        $data['package'] = array_replace(array_fill_keys($packageKeys, null), $data['package']);
        $canonical = ['operation'=>self::OPERATION,'actor_kind'=>'support','provider_session_scope'=>'whole-stack-user',
            'remote_scope_restricted'=>false,'review'=>array_replace(array_fill_keys($keys, null), $data)];
        $this->json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        // Rebuild from bytes to detach nested references, including restored objects.
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
    private static function deny(): never { throw new \InvalidArgumentException('Invalid support StackCP handoff review context.'); }
}
