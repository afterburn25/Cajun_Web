<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
/** Two fixed operations; callers cannot supply arbitrary byte budgets. */
enum ReadProfile {case Headers; case Message;
 public function literalBytes(): int {return $this===self::Headers?16384:262144;}
}
