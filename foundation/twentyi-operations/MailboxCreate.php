<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{OperationResult,ProviderOperation,TransportRequest,TransportResponse};
use InvalidArgumentException;

/** Exact creation contract: https://docs.20i.com/api/provision-mailbox-api (read 2026-10-15). */
final class MailboxCreate
{
    public const OPERATION = 'mailboxes.create';

    /** Safe operation metadata only. Passwords must never enter a grant or ledger fingerprint. */
    public static function parameters(ProviderOperation $operation): array
    {
        $p = $operation->params;
        if ($operation->name !== self::OPERATION || !self::keys($p, ['packageId','domain','local','password_ref'])
            || !is_int($p['packageId']) || $p['packageId'] < 1 || strlen((string)$p['packageId']) > 18
            || !is_string($p['password_ref']) || !preg_match('/\A[a-f0-9]{32}\z/', $p['password_ref'])) {
            throw new InvalidArgumentException('Mailbox creation requires one exact resource and protected password reference.');
        }
        self::domain($p['domain']);
        self::local($p['local']);
        if (strlen($p['local'].'@'.$p['domain']) > 254) throw new InvalidArgumentException('The mailbox address is too long.');
        return $p;
    }

    public static function domain(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 253 || !str_contains($value, '.') || $value !== strtolower($value)) {
            throw new InvalidArgumentException('Use the exact lowercase ASCII domain from the approved hosting resource.');
        }
        foreach (explode('.', $value) as $label) {
            if (!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $label)) {
                throw new InvalidArgumentException('The mailbox domain is invalid.');
            }
        }
        if (filter_var($value, FILTER_VALIDATE_IP)) throw new InvalidArgumentException('A mailbox requires a domain name.');
        return $value;
    }

    /** A deliberately bounded application offering; this is not a claim about every provider-supported local part. */
    public static function local(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 64 || !preg_match('/\A[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?\z/', $value)
            || str_contains($value, '..')) throw new InvalidArgumentException('Use a lowercase mailbox name without leading, trailing or repeated dots.');
        return $value;
    }

    /** Application password policy, not a provider quota or documented provider limit. */
    public static function password(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 12 || strlen($value) > 128 || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $value)) throw new InvalidArgumentException('Use a mailbox password of 12–128 bytes without control characters.');
        return $value;
    }

    /** Resolver must reauthorize the immutable approval and resolve its protected secret only at dispatch. */
    public static function request(ProviderOperation $operation, ?callable $resolvePassword): TransportRequest
    {
        $p = self::parameters($operation);
        if ($resolvePassword === null) throw new InvalidArgumentException('Mailbox creation requires its dedicated authorization and secret resolver.');
        $password = self::password($resolvePassword($p['password_ref'], $operation));
        try {
            $body = self::body($p['local'], $password);
            return new TransportRequest('POST', 'https://api.20i.com/package/'.$p['packageId'].'/email/'.$p['domain'],
                ['Content-Type'=>'application/json','Accept'=>'application/json'], $body, 15.0);
        } finally { unset($password, $body); }
    }

    private static function body(string $local, #[\SensitiveParameter] string $password): string
    {
        // The official example uses string values, not JSON booleans.
        return json_encode(['new'=>['mailbox'=>['local'=>$local,'send'=>'true','receive'=>'true','password'=>$password]]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Fixed transport admission: no other mail-object actions, paths or extra JSON fields. Never persist this return value. */
    public static function wire(#[\SensitiveParameter] TransportRequest $request): array
    {
        if ($request->method !== 'POST' || $request->timeoutSeconds > 15
            || !preg_match('~\Ahttps://api\.20i\.com/package/([1-9][0-9]{0,17})/email/([^/]+)\z~', $request->url, $m)
            || !is_string($request->body) || strlen($request->body) > 8192) throw new InvalidArgumentException('Unsupported mailbox request.');
        $domain = self::domain($m[2]);
        try { $body = json_decode($request->body, true, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new InvalidArgumentException('Unsupported mailbox body.'); }
        if (!is_array($body) || !self::keys($body, ['new']) || !is_array($body['new']) || !self::keys($body['new'], ['mailbox'])
            || !is_array($body['new']['mailbox']) || !self::keys($body['new']['mailbox'], ['local','send','receive','password'])) {
            throw new InvalidArgumentException('Unsupported mailbox fields.');
        }
        $mailbox = $body['new']['mailbox'];
        $local = self::local($mailbox['local']);
        $password = self::password($mailbox['password']);
        if ($mailbox['send'] !== 'true' || $mailbox['receive'] !== 'true' || strlen($local.'@'.$domain) > 254
            || $request->body !== self::body($local, $password)) throw new InvalidArgumentException('Unsupported mailbox configuration.');
        return ['packageId'=>(int)$m[1], 'domain'=>$domain, 'local'=>$local, 'password'=>$password];
    }

    /** Only the exact documented response for this request can report creation. Raw/echoed passwords never leave this boundary. */
    public static function result(ProviderOperation $operation, #[\SensitiveParameter] TransportRequest $request, #[\SensitiveParameter] TransportResponse $response): OperationResult
    {
        if (!in_array($response->status, [200,201], true)) return TwentyIResultClassifier::fromResponse(self::OPERATION, $response);
        if (strlen($response->body) > 65536) return OperationResult::uncertain(self::OPERATION, 'response_limit');
        try {
            $p = self::parameters($operation);
            $sent = self::wire($request);
            if ($sent['packageId'] !== $p['packageId'] || $sent['domain'] !== $p['domain'] || $sent['local'] !== $p['local']) {
                return OperationResult::uncertain(self::OPERATION, 'request_context');
            }
            $body = json_decode($response->body, false, 8, JSON_THROW_ON_ERROR);
            if (!$body instanceof \stdClass || !self::keys(get_object_vars($body), ['result']) || !$body->result instanceof \stdClass
                || !self::keys(get_object_vars($body->result), ['result','name']) || $body->result->name !== $p['domain']
                || !is_array($body->result->result) || count($body->result->result) !== 1) return OperationResult::uncertain(self::OPERATION, 'response_unverified');
            $row = $body->result->result[0];
            if (!$row instanceof \stdClass || !self::keys(get_object_vars($row), ['type','id','generatedId','password'])
                || $row->type !== 'mailbox' || $row->id !== $p['local'] || $row->generatedId !== null || !is_string($row->password)
                || !hash_equals($sent['password'], $row->password)) return OperationResult::uncertain(self::OPERATION, 'response_unverified');
            // Preserve the provider's bounded local ID; package/domain stay in the original approval.
            return OperationResult::succeeded(self::OPERATION, 'The provider confirmed mailbox creation.', $row->id);
        } catch (\JsonException|InvalidArgumentException) {
            return OperationResult::uncertain(self::OPERATION, 'response_shape');
        } finally { unset($sent, $body, $row); }
    }

    private static function keys(array $value, array $expected): bool
    {
        $keys = array_keys($value); sort($keys); sort($expected); return $keys === $expected;
    }
}
