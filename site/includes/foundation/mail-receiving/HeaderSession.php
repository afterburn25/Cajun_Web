<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;
final class HeaderSession {
 private ReadSession $session;
 public function __construct(StreamInterface $stream,callable $authorize) {$this->session=new ReadSession($stream,$authorize,ReadProfile::Headers);}
 public function read(string $encryption,#[\SensitiveParameter] string $user,#[\SensitiveParameter] string $password): array {return $this->session->headers($encryption,$user,$password);}
}
