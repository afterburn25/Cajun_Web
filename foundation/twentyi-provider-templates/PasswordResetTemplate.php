<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIProviderTemplates;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Opaque private template text. No placeholder, HTML or mail-delivery interpretation. */
final readonly class PasswordResetTemplate implements \JsonSerializable
{
    /** Local editor policy, not a provider limit. Empty observed strings remain valid. */
    public const MAX_BYTES = 65536;
    private string $content;
    public int $bytes;
    public string $sha256;

    private function __construct(#[\SensitiveParameter] string $content)
    {
        if (strlen($content) > self::MAX_BYTES || preg_match('//u', $content) !== 1
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $content)) {
            throw new \InvalidArgumentException('Use UTF-8 template text of at most 65536 bytes with only tab and line-break controls.');
        }
        $this->content = (string)$content;
        $this->bytes = strlen($content);
        $this->sha256 = hash('sha256', $content);
    }

    public static function fromString(#[\SensitiveParameter] string $content): self
    {
        return new self($content);
    }

    /** Explicit private export for the editor, encryption or fixed provider request only. */
    public function text(): string
    {
        return $this->validated()->content;
    }

    public function validated(): self
    {
        if (!isset($this->content, $this->bytes, $this->sha256)) {
            throw new \InvalidArgumentException('Incomplete template document.');
        }
        $copy = new self($this->content);
        if ($copy->bytes !== $this->bytes || !hash_equals($copy->sha256, $this->sha256)) {
            throw new \InvalidArgumentException('Inconsistent template document.');
        }
        return $copy;
    }

    public function jsonSerialize(): array
    {
        $copy = $this->validated();
        return ['bytes'=>$copy->bytes, 'sha256'=>$copy->sha256, 'content'=>'[redacted]'];
    }

    public function __debugInfo(): array { return $this->jsonSerialize(); }
    public function __serialize(): array { throw new \LogicException('Private template serialization is unavailable.'); }
    public function __unserialize(array $data): void { throw new \LogicException('Private template restoration is unavailable.'); }
}
