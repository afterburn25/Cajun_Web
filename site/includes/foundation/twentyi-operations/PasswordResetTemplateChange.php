<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI\Operations;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/../twentyi-provider-templates/PasswordResetTemplate.php';

use CajunWebPro\Foundation\Provider\{ProviderOperation, TransportRequest, TransportResponse, OperationResult};
use CajunWebPro\Foundation\TwentyIProviderTemplates\PasswordResetTemplate;

/** Owner reference 34e713 lines 1012-1019: entire content string and integer affected-row acknowledgement. */
final class PasswordResetTemplateChange
{
    public const OPERATION = 'reseller.passwordresettemplate';
    public const URL = 'https://api.20i.com/reseller/*/passwordResetEmail';
    public const MAX_WIRE_BYTES = PasswordResetTemplate::MAX_BYTES * 6 + 14;
    public const MAX_BODY_BYTES = self::MAX_WIRE_BYTES;

    /** Compact metadata only: plaintext belongs to the separately protected request record. */
    public static function params(array $params): array
    {
        $keys = array_keys($params); sort($keys);
        if ($keys !== ['content_sha256','request_id','resellerId'] || $params['resellerId'] !== '*'
            || !is_string($params['request_id']) || preg_match('/\A[a-f0-9]{32}\z/', $params['request_id']) !== 1
            || !is_string($params['content_sha256']) || preg_match('/\A[a-f0-9]{64}\z/', $params['content_sha256']) !== 1) {
            throw new \InvalidArgumentException('Template change requires one protected request and exact content digest.');
        }
        return ['resellerId'=>'*', 'request_id'=>(string)$params['request_id'], 'content_sha256'=>(string)$params['content_sha256']];
    }

    /** Resolver reauthorizes current authority and opens only this request's private content. */
    public static function request(ProviderOperation $operation, ?callable $resolveContent): TransportRequest
    {
        if ($operation->name !== self::OPERATION) throw new \InvalidArgumentException('Unsupported template operation.');
        $params = self::params($operation->params);
        if ($resolveContent === null) throw new \InvalidArgumentException('Template change requires its dedicated content resolver.');
        try { $content = $resolveContent($params['request_id'], $operation); }
        catch (\Throwable) { throw new \InvalidArgumentException('Approved template content is unavailable.'); }
        try {
            if (!is_string($content)) throw new \InvalidArgumentException('Approved template content is unavailable.');
            $template = PasswordResetTemplate::fromString($content);
            if (!hash_equals($params['content_sha256'], $template->sha256)) throw new \InvalidArgumentException('Approved template content changed.');
            return new TransportRequest('POST', self::URL, ['Content-Type'=>'application/json','Accept'=>'application/json'], self::body($template->text()), 15.0);
        } finally { unset($content, $template); }
    }

    private static function body(#[\SensitiveParameter] string $content): string
    {
        $template = PasswordResetTemplate::fromString($content);
        if ($template->bytes === 0) throw new \InvalidArgumentException('Empty template writes have no documented reset meaning.');
        return json_encode(['content'=>$template->text()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Exact fixed route/canonical JSON only. Never return or persist the private body. */
    public static function wire(#[\SensitiveParameter] TransportRequest $request): void
    {
        if ($request->method !== 'POST' || $request->url !== self::URL || $request->timeoutSeconds > 15.0
            || !is_string($request->body) || strlen($request->body) > self::MAX_WIRE_BYTES) {
            throw new \InvalidArgumentException('Unsupported template request.');
        }
        try { $body = json_decode($request->body, true, 4, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \InvalidArgumentException('Unsupported template body.'); }
        if (!is_array($body) || array_keys($body) !== ['content'] || !is_string($body['content'])
            || $request->body !== self::body($body['content'])) {
            throw new \InvalidArgumentException('Unsupported template body.');
        }
    }

    /** An affected-row acknowledgement never proves current content or mail delivery. */
    public static function result(#[\SensitiveParameter] TransportResponse $response, #[\SensitiveParameter] string $credential): OperationResult
    {
        if ($response->status !== 200) return TwentyIResultClassifier::fromResponse(self::OPERATION, $response);
        if (strlen($response->body) > 128) return OperationResult::uncertain(self::OPERATION, 'response_limit');
        try { $count = json_decode($response->body, false, 4, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return OperationResult::uncertain(self::OPERATION, 'response_shape'); }
        if (!is_int($count) || $count < 0) return OperationResult::uncertain(self::OPERATION, 'response_unverified');
        if ($credential !== '' && (str_contains($response->body, $credential) || str_contains($response->body, base64_encode($credential)))) {
            return OperationResult::uncertain(self::OPERATION, 'response_unverified');
        }
        return $count > 0 ? OperationResult::acceptedPending(self::OPERATION) : OperationResult::uncertain(self::OPERATION, 'no_rows_reported');
    }
}
