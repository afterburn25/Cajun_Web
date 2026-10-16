<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<section class="workspace-card" data-client-domain-search>
<h2>Check availability</h2>
<p>Search for a complete domain name. Availability is recorded at the time of the lookup; it is not a reservation, price quote or purchase.</p>
<?php if($searchContext): ?>
<p>Offered extensions: <?=esc(implode(', ',$searchContext['tlds']))?></p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="client_domain_search">
<input type="hidden" name="search_policy" value="<?=esc($searchContext['policy'])?>"><input type="hidden" name="search_request" value="<?=esc(bin2hex(random_bytes(16)))?>">
<?php $query=is_string($_POST['domain_query']??null)?substr($_POST['domain_query'],0,1000):''; ?>
<label>Domain name<input name="domain_query" value="<?=esc($query)?>" placeholder="example.com" maxlength="253" required autocapitalize="none" spellcheck="false"></label>
<button class="btn gold">Check availability</button></form>
<p class="hint">Please wait between searches. Lookup limits are shared, so searches may pause temporarily.</p>
<?php else: ?><p>Domain search is temporarily unavailable. Please contact the studio if you need help choosing a domain.</p><?php endif; ?>
</section>
<?php if($searchContext&&($registrationPeriods??null)!==null): ?>
<section class="workspace-card" data-client-registration-periods><h2>Registration options</h2>
<?php require_once CWP_CODE_ROOT.'/app/providers/registration_period_views.php';provider_registration_period_rows($registrationPeriods); ?>
</section>
<?php endif; ?>
<section class="workspace-card" data-client-domain-history><h2>Your recent searches</h2>
<?php if($historyUnavailable): ?><p>Search history is temporarily unavailable.</p>
<?php elseif(!$searchHistory): ?><p>You have no recorded domain searches.</p><?php endif; ?>
<?php foreach(array_slice($searchHistory,0,20) as $search): ?>
<article class="workspace-card"><h3><?=esc($search['domain'])?></h3><p><?=esc($search['at'])?> UTC</p>
<p><?=esc($search['available']?'Available when checked':($search['state']===null?'The lookup did not finish. Availability was not established.':'Availability was not established.'))?></p>
<?php if($search['reason']==='rate_limited'): ?><p>Searches are paused because the provider limited a recent lookup.</p>
<?php elseif($search['state']==='access_changed'): ?><p>Search access changed during this lookup. Its result was discarded.</p>
<?php elseif($search['state']==='partial'): ?><p>The lookup returned an incomplete result.</p><?php endif; ?>
<?php if($search['suggestions']): ?><p>Suggestions to check separately:</p><ul><?php foreach($search['suggestions'] as $suggestion): ?><li><?=esc($suggestion)?></li><?php endforeach; ?></ul><?php endif; ?>
</article>
<?php endforeach; ?>
<?php if(count($searchHistory)>20): ?><p>Showing your 20 most recent searches.</p><?php endif; ?>
</section>
