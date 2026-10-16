<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIDomainExpiry;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\TwentyIDomainContracts\DomainContractBinding;

/**
 * Operation-specific projection of an existing exact registrar binding.
 * This value validates identity syntax; the application must still establish
 * current owner/account/link/source authority before and after any future read.
 */
final readonly class DomainExpiryBinding
{
    public DomainContractBinding $registrar;
    public string $json;
    public string $sha256;
    public string $scope;

    public function __construct(DomainContractBinding $registrar)
    {
        // Rebuild the validated scalar document rather than trusting derived
        // fields of a possibly restored object or retaining caller references.
        $validated = new DomainContractBinding($registrar->data);
        if ($registrar->json !== $validated->json
            || $registrar->sha256 !== $validated->sha256
            || $registrar->scope !== $validated->scope) {
            throw new \InvalidArgumentException('Invalid registrar expiry binding.');
        }
        $this->registrar = $validated;
        $this->json = json_encode(
            ['operation' => DomainExpiry::OPERATION, 'registrar' => $validated->data],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $this->sha256 = hash('sha256', $this->json);
        $this->scope = 'twenty-i|' . $validated->data['profile_id']
            . '|' . DomainExpiry::OPERATION . '|' . $this->sha256;
    }
}
