<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<?php if($historyUnavailable): ?><p class="notice error" role="alert">The studio needs to review the saved hosting history. Please contact us.</p>
<?php elseif(!$histories): ?><p>No hosting history is shared with this account yet.</p>
<?php else:foreach($histories as $entry): ?><section class="workspace-card"><?php service_observations_render($entry['history'],$entry['link_id']);?></section><?php endforeach;endif; ?>
<a class="btn outline" href="<?=esc(studio_url(['service_history'=>1]))?>">Latest saved history</a>
