<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ConnectionCheck,HttpTransport,OperationResult,ProviderAdapter,ProviderCapability,ProviderCredential,ProviderOperation,TransportRequest};

/** Fixed read-only authentication probe. No inventory projection or provisioning. */
final class TwentyIAdapter implements ProviderAdapter
{
    public const URL = 'https://api.20i.com/package';
    public const MAX_BODY_BYTES = 1048576;
    public const MAX_KEY_BYTES = 4096;

    public function id(): string { return 'twenty-i'; }
    public function label(): string { return '20i'; }

    public function capabilities(): array
    {
        return array_map(
            static fn(string $operation): ProviderCapability => ProviderCapability::unverified(
                $operation, 'Separate response, ownership and operation acceptance is required.'
            ),
            ['packages.list','package.types','account.balance','packages.create','packages.update',
                'packages.delete','domains.search','domains.register','domains.renew','domains.transfer',
                'vps.list','vps.create','vps.power','backups.restore','ssh.connect']
        );
    }

    public function supports(string $operation): bool { return false; }

    public function execute(HttpTransport $transport, #[\SensitiveParameter] ProviderCredential $credential, ProviderOperation $operation): OperationResult
    {
        // Even a direct caller cannot bypass the gateway's capability gate.
        return OperationResult::unsupported($operation->name);
    }

    public function probe(HttpTransport $transport, #[\SensitiveParameter] ProviderCredential $credential): ConnectionCheck
    {
        if ($credential->providerId !== $this->id()) {
            return ConnectionCheck::unsupported('wrong_provider');
        }
        $secret = $credential->secret();
        if ($secret === '' || strlen($secret) > self::MAX_KEY_BYTES || preg_match('/[\x00-\x1f\x7f]/', $secret)) {
            return ConnectionCheck::unsupported('invalid_credential');
        }
        // The saved value is the raw General API key, encoded exactly once here.
        $request = new TransportRequest('GET', self::URL, [
            'Authorization' => 'Bearer '.base64_encode($secret),
            'Accept' => 'application/json',
        ], null, 15.0);
        unset($secret);
        $response = $transport->send($request);
        unset($request);
        if ($response->status === 401 || $response->status === 403) {
            return ConnectionCheck::authenticationFailed('authentication');
        }
        if ($response->status === 429) return ConnectionCheck::unknown('rate_limited');
        if ($response->status >= 500) return ConnectionCheck::unknown('remote_error');
        if ($response->status !== 200) return ConnectionCheck::unknown('unexpected_status');
        if (strlen($response->body) > self::MAX_BODY_BYTES) return ConnectionCheck::unknown('response_limit');
        try {
            // Associative decoding would incorrectly treat {} and {"0":...} as lists.
            $decoded = json_decode($response->body, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ConnectionCheck::unknown('unexpected_response');
        }
        if (!is_array($decoded)) return ConnectionCheck::unknown('unexpected_response');
        // Item fields remain unverified. Never persist or return this account-wide body.
        return ConnectionCheck::verified('connected');
    }
}
