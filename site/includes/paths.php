<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
// Code can be staged for an authenticated update. Installed identity never follows the endpoint.
if (!defined('CWP_CODE_ROOT')) define('CWP_CODE_ROOT', dirname(__DIR__));
if (!defined('CWP_ROOT')) define('CWP_ROOT', CWP_CODE_ROOT);
