<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="workspace-card" data-domain-search>
<h2>Domain search</h2>
<p>Check one domain through your saved 20i connection. Results show availability at the recorded time; they do not reserve a domain or establish its price.</p>
<?php if(!$record): ?><p>Save a 20i connection first.</p>
<?php elseif(!provider_domain_search_ready()): ?>
<p>Prepare the search history before allowing lookups. Back up the database first.</p>
<form method="post"><?php admin_action('provider_inventory_domain_search_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare domain search</button></form>
<?php else:
try{$searchConfig=provider_domain_search_configuration();}
catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p></section>';return;}
$searchTlds=implode("\n",$searchConfig['tlds']);$searchEnabled=$searchConfig['enabled'];
$searchConfigFormRevision=(int)$searchConfig['revision'];$searchRunRevision=$searchConfigFormRevision;
$searchConfigProfileRevision=(int)$record['revision'];$searchRunProfileRevision=$searchConfigProfileRevision;
// Retain the exact configuration context on rejected POSTs; a new GET is required after a stale form.
$postedSearchRevision=$_POST['search_revision']??null;$postedProfileRevision=$_POST['revision']??null;
$validRevisions=is_string($postedSearchRevision)&&preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$postedSearchRevision)===1&&(int)$postedSearchRevision<=2147483647
    &&is_string($postedProfileRevision)&&preg_match('/\A[1-9][0-9]{0,9}\z/',$postedProfileRevision)===1&&(int)$postedProfileRevision<=2147483647
    &&($_POST['profile_id']??null)===$record['id'];
if($validRevisions&&($_POST['action']??'')==='provider_inventory_domain_search_configure'){$searchConfigFormRevision=(int)$postedSearchRevision;$searchConfigProfileRevision=(int)$postedProfileRevision;}
if($validRevisions&&($_POST['action']??'')==='provider_inventory_domain_search_run'){$searchRunRevision=(int)$postedSearchRevision;$searchRunProfileRevision=(int)$postedProfileRevision;}
if(($_POST['action']??'')==='provider_inventory_domain_search_configure'){
    if(is_string($_POST['search_tlds']??null))$searchTlds=substr($_POST['search_tlds'],0,4096);
    $searchEnabled=($_POST['search_enabled']??'')==='1';
}
?>
<h3>Offered domain extensions</h3>
<p>Choose the extensions you offer. This filters searches and suggestions locally; it does not confirm 20i pricing, registration periods or account eligibility.</p>
<form method="post" data-domain-search-settings>
<?php admin_action('provider_inventory_domain_search_configure'); ?>
<input type="hidden" name="profile_id" value="<?=esc($record['id'])?>"><input type="hidden" name="revision" value="<?=$searchConfigProfileRevision?>"><input type="hidden" name="search_revision" value="<?=$searchConfigFormRevision?>">
<label for="search_tlds">Extensions, one per line (for example com or co.uk)</label>
<textarea id="search_tlds" name="search_tlds" rows="5" maxlength="4096"><?=esc($searchTlds)?></textarea>
<?php admin_check('search_enabled','Allow owners to request live domain lookups',$searchEnabled);admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Save domain search settings</button>
</form>
<?php $searchCurrent=$searchConfig['enabled']&&$searchConfig['profile_id']===$record['id']&&$searchConfig['profile_revision']===(int)$record['revision']&&(int)$record['enabled']===1&&$record['has_credentials']; ?>
<?php if($searchCurrent): ?>
<h3>Check a domain</h3>
<form method="post" data-domain-search-form>
<?php admin_action('provider_inventory_domain_search_run'); ?>
<input type="hidden" name="profile_id" value="<?=esc($record['id'])?>"><input type="hidden" name="revision" value="<?=$searchRunProfileRevision?>"><input type="hidden" name="search_revision" value="<?=$searchRunRevision?>">
<?php $searchQuery=is_string($_POST['domain_query']??null)?substr($_POST['domain_query'],0,1000):'';admin_input('domain_query','Complete domain name',$searchQuery,'text','required maxlength="253" autocapitalize="none" spellcheck="false"'); ?>
<button class="btn gold">Check availability</button>
</form>
<?php else: ?><p>Enable domain lookups for the current saved connection and choose at least one extension. Changing the connection requires saving these search settings again.</p><?php endif; ?>
<p class="hint">All owners share a 30-second cooldown and a limit of 20 lookups per hour. A rate-limit response from 20i pauses searches for 24 hours. Opening this page never sends a lookup.</p>
<?php $searchPage=max(1,min((int)($_GET['domain_search_page']??1),100000));$searchHistory=provider_domain_search_history($record['id'],$searchPage); ?>
<div data-domain-search-history><h3>Domain search history</h3>
<?php if(!$searchHistory): ?><p>No domain lookup has been recorded.</p><?php endif; ?>
<?php foreach(array_slice($searchHistory,0,20) as $searchAttempt):
$searchResult=null;
try{$searchResult=$searchAttempt['result']!==null?json_decode($searchAttempt['result'],true,8,JSON_THROW_ON_ERROR):null;}catch(JsonException){}
$searchHistorical=(int)$searchAttempt['profile_revision']!==(int)$record['revision']||(int)$searchAttempt['config_revision']!==$searchConfig['revision'];
$searchAvailable=is_array($searchResult)&&$searchAttempt['state']==='checked'&&($searchResult['registration_available']??null)===true;
?>
<article class="workspace-card"><h4><?=esc($searchAttempt['query_name'])?></h4>
<p><?=esc($searchAttempt['started_at'])?> UTC &middot; <?=esc($searchAttempt['actor_name']??'Former owner')?><?php if($searchHistorical): ?> &middot; Earlier settings<?php endif; ?></p>
<p><?=esc($searchAvailable?'Available when checked':($searchAttempt['state']===null?'Incomplete lookup; no availability was established.':'Availability was not established.'))?></p>
<?php if($searchAttempt['reason']==='rate_limited'): ?><p>20i limited this lookup. Search is paused for 24 hours from the response.</p>
<?php elseif($searchAttempt['state']==='access_changed'): ?><p>Access or connection settings changed during this lookup. Its result was discarded.</p>
<?php elseif($searchAttempt['state']==='partial'): ?><p>20i returned an incomplete or unverified result.</p>
<?php elseif($searchAttempt['state']==='unavailable'): ?><p>The lookup could not establish availability. Check the connection and try again after the cooldown.</p><?php endif; ?>
<?php if(is_array($searchResult)&&is_array($searchResult['suggestions']??null)&&$searchResult['suggestions']): ?><p>Suggestions requiring a separate check:</p><ul><?php foreach($searchResult['suggestions'] as $suggestion):if(!is_string($suggestion))continue; ?><li><?=esc($suggestion)?></li><?php endforeach; ?></ul><?php endif; ?>
</article>
<?php endforeach; ?>
<nav aria-label="Domain search history pages"><?php if($searchPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['domain_search_page'=>$searchPage-1]))?>">Newer searches</a><?php endif; ?><?php if(count($searchHistory)>20): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['domain_search_page'=>$searchPage+1]))?>">Older searches</a><?php endif; ?></nav></div>
<?php endif; ?>
</section>
