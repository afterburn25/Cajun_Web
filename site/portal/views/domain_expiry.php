<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<?php if($datesUnavailable): ?><p class="notice error" role="alert">The studio needs to review the saved expiry timestamps. Please contact us.</p>
<?php elseif(!$dates): ?><p>No expiry timestamps are available for your registered domains yet.</p>
<?php else:foreach($dates as $p): ?><section class="workspace-card"><?php domain_expiry_render($p);?></section><?php endforeach;endif; ?>
<div class="button-row"><a class="btn outline" href="<?=esc(studio_url(['domain_expiry'=>1]))?>">Latest saved observations</a><a class="btn outline" href="<?=esc(studio_url(['domain_dates'=>1]))?>">Renewal dates</a><a class="btn gold" href="<?=esc(studio_url(['domain_renewals'=>1]))?>">Request renewal review</a></div>
