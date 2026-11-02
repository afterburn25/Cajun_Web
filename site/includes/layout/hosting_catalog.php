<?php
/** Escaped offer content only. Callers supply a validated public or historical projection. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_billing.php';

/** Only fixed local navigation is produced; choosing a period never changes an offer. */
function hosting_catalog_billing_url(string $billing,int $page=1,bool $portal=false): string {
    $browse=hosting_catalog_billing_query(['billing'=>$billing,'p'=>(string)$page]);
    return $portal ? studio_url(['hosting'=>'offers','billing'=>$browse['billing'],'p'=>$browse['page']])
        : page_url('hosting',['billing'=>$browse['billing'],'hosting_page'=>$browse['page']]).'#hosting-plans';
}
function hosting_catalog_billing_options(string $billing,bool $portal=false): void {
    $billing=hosting_catalog_billing($billing);
    echo '<nav class="hosting-billing-selector" aria-label="Hosting billing period"><span>Billing period</span><div>';
    foreach (['all'=>'All plans','monthly'=>'Monthly','annually'=>'Annual'] as $value=>$label) {
        echo '<a class="btn outline" href="'.esc(hosting_catalog_billing_url($value,1,$portal)).'"'.($billing===$value?' aria-current="page"':'').'>'.esc($label).'</a>';
    }
    echo '</div><p>Prices show the full charge for each month or year. Each option has its own published terms.</p></nav>';
}
function hosting_catalog_billing_pages(array $page,array $browse,bool $portal=false): void {
    $browse=hosting_catalog_billing_query(['billing'=>$browse['billing'],'p'=>(string)$browse['page']]);
    echo '<nav class="button-row hosting-billing-pages" aria-label="Hosting plan pages">';
    if ($browse['page']>1) echo '<a class="btn outline" href="'.esc(hosting_catalog_billing_url($browse['billing'],$browse['page']-1,$portal)).'">Previous plans</a>';
    if ($page['has_more']&&$browse['page']<100000) echo '<a class="btn outline" href="'.esc(hosting_catalog_billing_url($browse['billing'],$browse['page']+1,$portal)).'">More plans</a>';
    echo '</nav>';
}

/** No queries, arbitrary URLs, private fields or current-availability claim. */
function hosting_catalog_card(array $offer,string $headingLevel='h3'):void {
    if (!in_array($headingLevel,['h2','h3','h4'],true)) throw new InvalidArgumentException('Invalid offer heading.');
    if (($offer['currency']??null)!=='USD'||!in_array($offer['cadence']??null,['monthly','annually'],true)||!is_int($offer['amount_cents']??null)||$offer['amount_cents']<1) throw new InvalidArgumentException('Hosting terms are unavailable.');
    $image=$offer['image_id']??null;
    if ($image!==null&&(!is_string($image)||!preg_match('/\A[a-f0-9]{32}\z/',$image))) throw new InvalidArgumentException('Hosting image is unavailable.');
    if ($image!==null) echo '<img class="hosting-catalog-image" src="'.esc(site_url('media/index.php?id='.$image)).'" alt="" loading="lazy" decoding="async">';
    echo '<'.$headingLevel.'>'.esc($offer['title']).'</'.$headingLevel.'><p>'.nl2br(esc($offer['summary'])).'</p>';
    echo '<p><strong>$'.esc(gmoney($offer['amount_cents'])).' USD</strong> '.($offer['cadence']==='monthly'?'per month':'per year').'</p><p class="hint">One package · No setup fee</p>';
    if (isset($offer['checkout_policy'])) {
        $policy=hosting_checkout_policy($offer['checkout_policy']);
        echo '<p class="hint">Confirm the terms in your client portal to create an unpaid first invoice. Billing starts on the confirmation date in '.esc($policy['timezone']).'. Hosting setup follows verified payment and owner approval.</p>';
        echo '<p class="hint">'.($policy['alignment']==='month-end'?'Month-end billing: confirmation is available only on the last day of a month.':'Recurring billing keeps the original day; short months use their last day.').'</p>';
    }
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
    echo '</div><p class="hint">Review the available starting method and exact terms in your client portal.</p><p><a class="btn gold" href="'.esc(site_url('portal/index.php?hosting=offers')).'">View plans in Client Portal</a></p>';
    if (($page['has_more']??false)===true) echo '<p class="hint">More available plans can be browsed in the Client Portal.</p>';
}


/** Presentation only: accept a fresh, safe hosting_catalog_public() page, never private documents. */
function hosting_catalog_comparison(array $page,?array $browse=null): void {
    if (!is_array($page['offers']??null) || !array_is_list($page['offers']) || count($page['offers'])>20
        || !is_bool($page['has_more']??null) || !is_bool($page['unavailable']??null)) throw new InvalidArgumentException('Hosting comparison is unavailable.');
    // Validate the whole bounded page before rendering any link or image.
    foreach ($page['offers'] as $offer) hosting_catalog_comparison_offer($offer);
    if ($browse!==null) {
        $browse=hosting_catalog_billing_query(['billing'=>$browse['billing'],'p'=>(string)$browse['page']]);
        hosting_catalog_billing_options($browse['billing']);
    }
    if ($page['unavailable']) echo '<p class="notice" role="status">Some hosting plans are temporarily unavailable. The plans below are available to review.</p>';
    if (!$page['offers']) {
        $empty=$browse!==null?'No '.match($browse['billing']){'monthly'=>'monthly','annually'=>'annual',default=>'shared hosting'}.' plans are available on this page. Choose another billing period or contact us.':'No shared hosting plans are published yet. Tell us about your website and we’ll help you plan the next step.';
        echo '<div class="hosting-comparison-empty"><h3>'.($page['unavailable']?'We cannot show hosting plans right now.':'Let’s find the right hosting for you.').'</h3><p>'.esc($page['unavailable']?'Please try again later, or contact us to discuss your website.':$empty).'</p><a class="btn outline" href="'.esc(page_url('consultation')).'">Discuss your hosting needs</a></div>';
        if ($browse!==null) hosting_catalog_billing_pages($page,$browse);
        return;
    }
    echo '<p class="hosting-comparison-intro">Compare the published price, included services and support. Each plan covers one hosting package, with no separate setup fee.</p>';
    foreach (array_chunk($page['offers'],4) as $index=>$offers) {
        $id='hosting-compare-'.($index+1);$start=$index*4+1;$end=$start+count($offers)-1;
        $caption=count($page['offers'])>4?'Shared hosting plans '.$start.'–'.$end:'Shared hosting plan comparison';
        echo '<div class="hosting-comparison-group"><p id="'.$id.'-help" class="hosting-comparison-help">On a small screen, scroll across to compare plans. Keyboard users can focus the table and use the arrow keys.</p>';
        echo '<div class="hosting-comparison-scroll" role="region" aria-labelledby="'.$id.'-caption" aria-describedby="'.$id.'-help" tabindex="0"><table class="hosting-comparison-table hosting-comparison-cols-'.count($offers).'"><caption id="'.$id.'-caption">'.esc($caption).'</caption><thead><tr><th scope="col" class="hosting-comparison-label">Your plan</th>';
        foreach ($offers as $offer) {
            echo '<th scope="col"><div class="hosting-comparison-plan"><span class="hosting-comparison-eyebrow">Shared hosting</span>';
            if ($offer['image_id']!==null) echo '<img class="hosting-catalog-image hosting-comparison-image" src="'.esc(site_url('media/index.php?id='.$offer['image_id'])).'" alt="" width="240" height="144" loading="lazy" decoding="async">';
            echo '<h3>'.esc($offer['title']).'</h3><p>'.nl2br(esc($offer['summary'])).'</p></div></th>';
        }
        echo '</tr></thead><tbody><tr><th scope="row">Price &amp; billing period</th>';
        foreach ($offers as $offer) echo '<td><strong class="hosting-comparison-price">$'.esc(gmoney($offer['amount_cents'])).'</strong><span class="hosting-comparison-period">USD '.($offer['cadence']==='monthly'?'per month':'per year').'</span></td>';
        echo '</tr>';
        foreach (['inclusions'=>'Included','exclusions'=>'Not included'] as $field=>$label) {
            echo '<tr><th scope="row">'.$label.'</th>';
            foreach ($offers as $offer) {
                echo '<td>';
                if (!$offer[$field]) echo '<span class="hosting-comparison-muted">No additional items listed.</span>';
                else {echo '<ul>';foreach ($offer[$field] as $item) echo '<li>'.esc($item).'</li>';echo '</ul>';}
                echo '</td>';
            }
            echo '</tr>';
        }
        $limits=[];foreach ($offers as $offer)foreach ($offer['limits'] as $key=>$value)$limits[$key]=true;
        ksort($limits,SORT_STRING);
        if (!$limits) {
            echo '<tr><th scope="row">Usage limits</th>';
            foreach ($offers as $offer) echo '<td>No separately metered limits listed. The included scope still applies.</td>';
            echo '</tr>';
        } else foreach (array_keys($limits) as $key) {
            echo '<tr><th scope="row">'.esc(ucfirst(str_replace('_',' ',$key))).'</th>';
            foreach ($offers as $offer) echo '<td>'.(array_key_exists($key,$offer['limits'])?esc((string)$offer['limits'][$key]):'<span class="hosting-comparison-muted">Not specified</span>').'</td>';
            echo '</tr>';
        }
        echo '<tr><th scope="row">Support</th>';
        foreach ($offers as $offer) echo '<td>'.nl2br(esc($offer['support'])).'</td>';
        echo '</tr><tr><th scope="row">How to get started</th>';
        foreach ($offers as $offer) {
            echo '<td>';
            if (isset($offer['checkout_policy'])) {
                $policy=hosting_checkout_policy($offer['checkout_policy']);
                echo '<p>Confirm your terms to create the first unpaid invoice. Your full billing period starts that day in '.esc($policy['timezone']).'.</p>';
                echo '<p>'.($policy['alignment']==='month-end'?'Confirmation is available on the last day of a month.':'Future billing follows the same day, using the last day of shorter months.').'</p><p>Hosting setup follows verified payment and owner approval.</p>';
                $url=site_url('portal/index.php?'.http_build_query(['hosting'=>'checkout','offer'=>$offer['publication_id'],'offer_sha256'=>$offer['publication_sha256']]));
                echo '<a class="btn gold" href="'.esc($url).'" aria-label="'.esc('Review '.$offer['title'].' and first invoice').'">Choose this plan</a>';
            } else {
                $requestUrl=$browse===null?site_url('portal/index.php?hosting=offers'):hosting_catalog_billing_url($browse['billing'],$browse['page'],true);
                echo '<p>Send a request from your client portal. We’ll review it with you before offering an agreement. A request does not take payment or activate hosting.</p><a class="btn outline" href="'.esc($requestUrl).'">View request options</a>';
            }
            echo '</td>';
        }
        echo '</tr></tbody></table></div></div>';
    }
    echo '<p class="hosting-comparison-note">A missing limit is not an unlimited allowance. Review the complete terms before confirming. Payment and hosting activation are separate steps.</p>';
    if ($browse!==null) hosting_catalog_billing_pages($page,$browse);
    elseif ($page['has_more']) echo '<p><a class="btn outline" href="'.esc(site_url('portal/index.php?hosting=offers&p=2')).'">Browse more plans in your portal</a></p>';
}

/** Reject malformed presentation inputs; this does not establish publication or purchasing authority. */
function hosting_catalog_comparison_offer(array $offer): void {
    foreach (['publication_id'=>32,'publication_sha256'=>64] as $key=>$length) {
        if (!is_string($offer[$key]??null)||!preg_match('/\A[a-f0-9]{'.$length.'}\z/',$offer[$key])) throw new InvalidArgumentException('Hosting offer identity is unavailable.');
    }
    if (($offer['currency']??null)!=='USD'||!in_array($offer['cadence']??null,['monthly','annually'],true)||!is_int($offer['amount_cents']??null)||$offer['amount_cents']<1||$offer['amount_cents']>99999999) throw new InvalidArgumentException('Hosting terms are unavailable.');
    foreach (['title','summary','support'] as $key) if (!is_string($offer[$key]??null)) throw new InvalidArgumentException('Hosting offer text is unavailable.');
    if (!array_key_exists('image_id',$offer)||($offer['image_id']!==null&&(!is_string($offer['image_id'])||!preg_match('/\A[a-f0-9]{32}\z/',$offer['image_id'])))) throw new InvalidArgumentException('Hosting image is unavailable.');
    foreach (['inclusions','exclusions'] as $key) {
        if (!is_array($offer[$key]??null)||!array_is_list($offer[$key])) throw new InvalidArgumentException('Hosting scope is unavailable.');
        foreach ($offer[$key] as $item) if (!is_string($item)) throw new InvalidArgumentException('Hosting scope is unavailable.');
    }
    if (!is_array($offer['limits']??null)) throw new InvalidArgumentException('Hosting limits are unavailable.');
    foreach ($offer['limits'] as $key=>$value) if (!is_string($key)||!is_int($value)||$value<0) throw new InvalidArgumentException('Hosting limits are unavailable.');
    if (array_key_exists('checkout_policy',$offer)) {
        if (!is_array($offer['checkout_policy'])) throw new InvalidArgumentException('Hosting checkout terms are unavailable.');
        hosting_checkout_policy($offer['checkout_policy']);
    }
}
