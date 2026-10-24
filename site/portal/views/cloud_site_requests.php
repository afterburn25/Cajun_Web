<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
if($cloudWebsiteUnavailable){
    echo '<p class="notice">Website request history is temporarily unavailable. Please contact the studio.</p>';
}else{
    provider_cloud_site_requests_render($cloudWebsiteOptions,$cloudWebsiteHistory,false,$cloudWebsiteReady,$cloudWebsitePage,provider_cloud_site_requests_retry($_POST));
}
