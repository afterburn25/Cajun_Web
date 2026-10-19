<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/StackCpHandoffContext.php';
require_once __DIR__.'/StackCpSensitiveResponse.php';

/** Native admission must commit handoff_attempted before this one-shot browser delivery. */
final class StackCpLaunchDelivery implements \JsonSerializable
{
    private bool $consumed = false;
    private function __construct(private StackCpSensitiveResponse $session) {}
    public static function fromSession(StackCpHandoffContext $context, StackCpSensitiveResponse $session): self
    {
        $context = StackCpHandoffContext::validated($context);
        if ($session->phase !== 'session' || $session->state !== 'accepted_subset'
            || !hash_equals($context->sha256,$session->contextSha256)) throw new \InvalidArgumentException('Invalid StackCP delivery.');
        return new self($session);
    }
    public function consume(callable $consumer): mixed
    {
        if ($this->consumed) throw new \LogicException('StackCP delivery has already been consumed.');
        $this->consumed = true;
        return $this->session->consume('session', static fn(#[\SensitiveParameter] string $session): mixed =>
            $consumer('https://www.stackcp.com/login/sso?PHPSESSID='.rawurlencode($session)));
    }
    public function jsonSerialize(): never { throw new \LogicException('StackCP delivery cannot be serialized.'); }
    public function __serialize(): never { throw new \LogicException('StackCP delivery cannot be serialized.'); }
    public function __unserialize(array $data): never { throw new \LogicException('StackCP delivery cannot be restored.'); }
    public function __debugInfo(): array { return ['consumed'=>$this->consumed]; }
    private function __clone() {}
}
