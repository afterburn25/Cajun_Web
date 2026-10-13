<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/../mail-transport/EndpointPolicy.php';
require_once __DIR__.'/ReceivingFailure.php';
require_once __DIR__.'/BoundedTokenizer.php';
require_once __DIR__.'/ReceivingStream.php';
require_once __DIR__.'/HeaderDecoder.php';
require_once __DIR__.'/HeaderSession.php';
require_once __DIR__.'/InboxReader.php';
