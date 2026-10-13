<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Invitations;

/** The raw token is returned once for trusted delivery, never persisted or logged. */
final readonly class IssuedInvitation implements \JsonSerializable
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
        public int $expiresAt,
    ) {}

    public function __debugInfo(): array
    {
        return ['token' => '[redacted]', 'expiresAt' => $this->expiresAt];
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
