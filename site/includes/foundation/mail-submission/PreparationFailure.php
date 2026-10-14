<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
final class PreparationFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        if(!in_array($reason,['unsupported_runtime','recipient_mismatch','encoding_failed','message_bounds'],true))throw new \InvalidArgumentException('Unknown preparation outcome.');
        parent::__construct('The email could not be prepared ('.$reason.').');
    }
}
