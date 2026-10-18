<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/provider_stackcp_auth_schema.php';
function provider_stackcp_assignment_schema(string $prefix): array {return provider_stackcp_journal_schema($prefix,'assignment');}
