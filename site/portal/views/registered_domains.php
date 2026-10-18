<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
try{$registeredDomains=provider_registrar_client_domains((int)$client['id']);}
catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<article class="workspace-card"><h2>Registered domains</h2><p>The studio needs to review the registered-domain records. Contact the studio for an update.</p></article>';return;}
foreach($registeredDomains as $registered): ?>
<article class="workspace-card" data-client-registered-domain><h2 style="overflow-wrap:anywhere"><?=esc($registered['domain']??'Registered domain')?></h2>
<?php if($registered['available']): ?><p>Registered-domain identity confirmed in the studio's latest 20i read.</p><p class="hint">Observed <?=esc($registered['observed_at'])?> UTC. Renewal, expiry and nameserver details are not available here yet.</p>
<?php else: ?><p>The studio needs to refresh or confirm this registered-domain assignment. Details are hidden until the current record is confirmed. This does not indicate cancellation or expiry.</p><?php endif; ?>
</article><?php endforeach; ?>
