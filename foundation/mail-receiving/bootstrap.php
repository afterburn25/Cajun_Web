<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/BoundedTokenizer.php';
