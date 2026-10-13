<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Provider account identity plus a secret that is resolved lazily and
 * never stored on the object. The resolver is supplied by the caller
 * (the site adapter decrypts the stored secret with the installation
 * private key, matching the SMTP credential convention); this component
 * cannot persist, log or serialize the secret.
 */
final class ProviderCredential
{
    /** @var callable():string */
    private $secretResolver;

    /**
     * @param callable():string $secretResolver Returns the raw secret at
     *        call time, or '' when no secret is configured.
     */
    public function __construct(
        public readonly string $providerId,
        public readonly string $accountIdentity,
        callable $secretResolver,
        public readonly ?string $defaultRegion = null,
    ) {
        if (trim($providerId) === '') {
            throw new InvalidArgumentException('A provider credential needs a provider identifier.');
        }
        if (trim($accountIdentity) === '') {
            throw new InvalidArgumentException('A provider credential needs an account identity.');
        }
        $this->secretResolver = $secretResolver;
    }

    public function secret(): string
    {
        return ($this->secretResolver)();
    }

    public function hasSecret(): bool
    {
        return $this->secret() !== '';
    }

    /**
     * Display-safe form of the secret for settings screens. Never
     * returns more than the last four characters, and never the whole
     * secret.
     */
    public function masked(): string
    {
        $secret = $this->secret();
        if ($secret === '') {
            return 'not configured';
        }
        return str_repeat('*', 8) . substr($secret, -4);
    }
}
