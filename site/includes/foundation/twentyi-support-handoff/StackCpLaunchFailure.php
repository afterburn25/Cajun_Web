<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Closed local outcome, never provider text. A possible POST is not safe to retry. */
final class StackCpLaunchFailure extends \RuntimeException
{
    public function __construct(public readonly string $phase, public readonly string $reason,
        public readonly bool $possibly_sent)
    {
        if (!in_array($phase, ['brand','token','session'], true)
            || !in_array($reason, ['dns','credentials','access_changed','transport','http_status',
                'authentication','rate_limited','upstream_unavailable','body_limit','malformed_body',
                'shape_unsupported','field_unsupported','field_limit','duplicate_field',
                'credential_echo','custom_brand','unavailable'], true)) {
            throw new \InvalidArgumentException('Invalid StackCP launch outcome.');
        }
        parent::__construct('StackCP launch '.$phase.': '.$reason.'.');
    }
    public function __debugInfo(): array { return ['phase'=>$this->phase,'reason'=>$this->reason,'possibly_sent'=>$this->possibly_sent]; }
}
