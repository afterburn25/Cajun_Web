<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyICloudCatalog;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/CloudProviderCatalogBinding.php';

/**
 * Unsent description of the owner reference2673-2680 specs/zones lookup.
 * POST is documented as a lookup here. No other POST or charged operation is
 * represented, and the unknown response structure has no parser/success state.
 * This object is deliberately not admitted to the application transport.
 */
final readonly class CloudProviderCatalogRequest
{
    public const METHOD = 'POST';
    public const URL = 'https://api.20i.com/reseller/*/cloudProviders';
    public string $method;
    public string $url;
    public array $headers;
    public string $body;
    public string $bindingSha256;

    private function __construct(CloudProviderCatalogBinding $binding)
    {
        $this->method = self::METHOD;
        $this->url = self::URL;
        $this->headers = ['Accept'=>'application/json', 'Content-Type'=>'application/json'];
        $this->body = json_encode(['provider'=>$binding->provider], JSON_THROW_ON_ERROR);
        $this->bindingSha256 = $binding->sha256;
    }

    /** Expected hash identifies the reviewed tuple; it does not authenticate its source. */
    public static function forBinding(CloudProviderCatalogBinding $binding, string $expectedSha256, int $now): self
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $expectedSha256) !== 1 || !hash_equals($binding->sha256, $expectedSha256)) {
            throw new \InvalidArgumentException('Cloud catalog request context changed.');
        }
        $binding->assertFresh($now);
        return new self($binding);
    }
}
