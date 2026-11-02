<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
/** Three fixed operations; callers cannot supply arbitrary byte budgets. */
enum ReadProfile {case Headers; case Message; case Append; case Flags;
 public function literalBytes(): int {return match($this){self::Headers=>16384,self::Message=>262144,self::Append=>0,self::Flags=>1024};}
}
