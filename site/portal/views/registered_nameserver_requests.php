<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
registered_nameserver_requests_render($screen, false, $retry);
