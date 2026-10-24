<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIProviderTemplates;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/PasswordResetTemplateObservation.php';

use CajunWebPro\Foundation\Provider\{HttpTransport, ProviderCredential, TransportRequest, TransportResponse, TransportException};

/** Owner reference 34e713 lines 1005-1011: bare string; no guessed result envelope. */
final class PasswordResetTemplateReader
{
    public const OPERATION = 'reseller.passwordresettemplate.read';
    public const URL = 'https://api.20i.com/reseller/*/passwordResetEmail';
    /** Allows worst-case JSON escaping within the local decoded text limit. */
    public const MAX_BODY_BYTES = PasswordResetTemplate::MAX_BYTES * 6 + 2;

    public static function request(): TransportRequest
    {
        return new TransportRequest('GET', self::URL, ['Accept'=>'application/json'], null, 15.0);
    }

    public static function project(#[\SensitiveParameter] TransportResponse $response, string $profileId, int $at): PasswordResetTemplateObservation
    {
        $fail = static fn(string $reason) => new PasswordResetTemplateObservation($profileId, $at, 'unavailable', $reason, null);
        // Validate context even for malformed/error provider responses.
        $fail('unexpected_status');
        if ($response->status !== 200) return $fail(match (true) {
            in_array($response->status, [401,403], true) => 'authentication',
            $response->status === 429 => 'rate_limited', $response->status >= 500 => 'remote_error', default => 'unexpected_status',
        });
        if (strlen($response->body) > self::MAX_BODY_BYTES) return $fail('response_limit');
        try { $text = json_decode($response->body, false, 4, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return $fail('malformed_body'); }
        if (!is_string($text)) return $fail('unexpected_shape');
        try { $template = PasswordResetTemplate::fromString($text); }
        catch (\InvalidArgumentException) { return $fail('invalid_value'); }
        return new PasswordResetTemplateObservation($profileId, $at, 'complete', '', $template);
    }

    /** Caller owns current authorization and pinned transport. No implicit network implementation. */
    public static function read(HttpTransport $transport, #[\SensitiveParameter] ProviderCredential $credential, string $profileId, int $at): PasswordResetTemplateObservation
    {
        $fail = static fn(string $reason) => new PasswordResetTemplateObservation($profileId, $at, 'unavailable', $reason, null);
        $fail('unexpected_status');
        if ($credential->providerId !== 'twenty-i' || $credential->accountIdentity !== $profileId) {
            throw new \InvalidArgumentException('Template observation account does not match.');
        }
        try { $secret = $credential->secret(); }
        catch (\Throwable) { throw new \InvalidArgumentException('Provider credentials are unavailable.'); }
        if ($secret === '' || strlen($secret) > 4096 || preg_match('//u', $secret) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $secret)) {
            throw new \InvalidArgumentException('Provider credentials are unavailable.');
        }
        $request = new TransportRequest('GET', self::URL, ['Authorization'=>'Bearer '.base64_encode($secret), 'Accept'=>'application/json'], null, 15.0);
        try {
            try { $response = $transport->send($request); }
            catch (TransportException $e) { return $fail('transport_'.$e->kind); }
            catch (\Throwable) { return $fail('transport_unknown'); }
            if (strlen($response->body) > self::MAX_BODY_BYTES) return $fail('response_limit');
            foreach ([$secret, base64_encode($secret)] as $needle) {
                if (str_contains($response->body, $needle)) return $fail('invalid_value');
            }
            $observation = self::project($response, $profileId, $at);
            if ($observation->state === 'complete') {
                $text = $observation->template()->text();
                foreach ([$secret, base64_encode($secret)] as $needle) {
                    if (str_contains($text, $needle)) return $fail('invalid_value');
                }
            }
            return $observation;
        } finally { unset($secret, $request, $response, $text); }
    }
}
