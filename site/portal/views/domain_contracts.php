<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<?php if($datesUnavailable): ?><p class="notice error" role="alert">The studio needs to review the saved renewal dates. Please contact us.</p>
<?php elseif(!$dates): ?><p>No renewal dates are available for your registered domains yet.</p>
<?php else:foreach($dates as $p): ?><section class="workspace-card"><?php domain_contracts_render($p);?></section><?php endforeach;endif; ?>
<div class="button-row"><a class="btn outline" href="<?=esc(studio_url(['domain_dates'=>1]))?>">Latest saved dates</a><a class="btn gold" href="<?=esc(studio_url(['domain_renewals'=>1]))?>">Request renewal review</a></div>
