<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
echo '<p><a class="btn outline" href="'.esc(admin_url('provider-settings')).'">Review 20i connection</a> <a class="btn outline" href="'.esc(admin_url('provider-settings')).'#subdomain-requests">Direct owner additions and hosting observations</a></p>';
provider_subdomain_requests_render($screen,true,$retry);
