<?php
/** Escaped offer content only. Callers supply a validated public or historical projection. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** No queries, arbitrary URLs, private fields or current-availability claim. */
function hosting_catalog_card(array $offer,string $headingLevel='h3'):void {
    if (!in_array($headingLevel,['h2','h3','h4'],true)) throw new InvalidArgumentException('Invalid offer heading.');
    if (($offer['currency']??null)!=='USD'||!in_array($offer['cadence']??null,['monthly','annually'],true)||!is_int($offer['amount_cents']??null)||$offer['amount_cents']<1) throw new InvalidArgumentException('Hosting terms are unavailable.');
    $image=$offer['image_id']??null;
    if ($image!==null&&(!is_string($image)||!preg_match('/\A[a-f0-9]{32}\z/',$image))) throw new InvalidArgumentException('Hosting image is unavailable.');
    if ($image!==null) echo '<img class="hosting-catalog-image" src="'.esc(site_url('media/index.php?id='.$image)).'" alt="" loading="lazy" decoding="async">';
    echo '<'.$headingLevel.'>'.esc($offer['title']).'</'.$headingLevel.'><p>'.nl2br(esc($offer['summary'])).'</p>';
    echo '<p><strong>$'.esc(gmoney($offer['amount_cents'])).' USD</strong> '.($offer['cadence']==='monthly'?'per month':'per year').'</p><p class="hint">One package · No setup fee</p>';
    echo '<details><summary>Plan terms</summary>';
    foreach (['Included'=>$offer['inclusions'],'Not included'=>$offer['exclusions']] as $label=>$items) {
        echo '<p><strong>'.esc($label).'</strong></p>';
        if ($items===[]) echo '<p>No additional items listed.</p>';
        else {echo '<ul>';foreach ($items as $item) echo '<li>'.esc($item).'</li>';echo '</ul>';}
    }
    echo '<p><strong>Usage limits</strong></p>';
    if ($offer['limits']===[]) echo '<p>No separately metered limits are listed. The included service scope still applies.</p>';
    else {echo '<dl>';foreach ($offer['limits'] as $name=>$limit) echo '<dt>'.esc(str_replace('_',' ',$name)).'</dt><dd>'.esc((string)$limit).'</dd>';echo '</dl>';}
    echo '<p><strong>Support</strong></p><p>'.nl2br(esc($offer['support'])).'</p></details>';
}

/** The public page supplies only hosting_catalog_public() output; failures become a generic empty state. */
function hosting_catalog_public_cards(array $page):void {
    if (($page['unavailable']??true)===true) echo '<p class="notice">Some hosting plans are temporarily unavailable. Contact us if you need help choosing a plan.</p>';
    if (empty($page['offers'])) {
        if (($page['unavailable']??true)!==true) echo '<p class="notice">No hosting plans are available to request right now. Contact us to discuss your website.</p>';
        return;
    }
    echo '<div class="workspace-grid">';
    foreach ($page['offers'] as $offer) {echo '<article class="workspace-card">';hosting_catalog_card($offer);echo '</article>';}
    echo '</div><p class="hint">A request does not take payment or activate hosting.</p><p><a class="btn gold" href="'.esc(site_url('portal/index.php?hosting=offers')).'">View plans in Client Portal</a></p>';
    if (($page['has_more']??false)===true) echo '<p class="hint">More available plans can be browsed in the Client Portal.</p>';
}
