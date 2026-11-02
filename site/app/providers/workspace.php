<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function provider_workspace_sections(): array {
    return ['overview'=>'Overview','connection'=>'Connection','services'=>'Existing services',
        'cloud'=>'Cloud','domains'=>'Domains & mail','website'=>'Website controls','advanced'=>'Advanced'];
}
function provider_workspace_section(array $query): string {
    if (($query['focus']??null)==='cloud-assignment') return 'cloud';
    $section=$query['section']??'overview';
    return is_string($section)&&array_key_exists($section,provider_workspace_sections())?$section:'overview';
}
function provider_workspace_url(array $extra=[]): string {
    return admin_url('provider-settings',array_replace(['section'=>provider_workspace_section($_GET)],$extra));
}
