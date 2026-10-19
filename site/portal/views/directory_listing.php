<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<section class="workspace-card" data-client-directory-listing><?php provider_directory_indexing_render($view);?></section>

<?php if(function_exists('provider_directory_indexing_requests_session_blocked')&&!provider_directory_indexing_requests_session_blocked()): ?>
<a class="btn outline" href="<?=esc(studio_url(['directory_requests'=>1]))?>">Request a directory listing change</a>
<?php endif; ?>
