<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
echo '<p><a class="btn outline" href="'.esc(admin_url('provider-settings')).'#registered-nameservers">Domain observations and direct owner changes</a></p>';
registered_nameserver_requests_render($screen, true, $retry);
