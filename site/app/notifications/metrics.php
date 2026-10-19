<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/notifications/MetricSample.php';
require_once CWP_CODE_ROOT.'/app/projects/deliveries.php';
require_once CWP_CODE_ROOT.'/app/notifications/feed.php';
use CajunWebPro\Foundation\Notifications\MetricSample;

/** Epoch of a stored UTC datetime, or null when the source row is absent. */
function metric_epoch(?string $recorded): ?int {
    return $recorded===null?null:(int)strtotime($recorded.' UTC');
}

/**
 * P-07 operational freshness — each sample's checkedAt is the real recorded
 * time of the newest underlying row (never the render time). A feature that is
 * not enabled reports absent; a quiet table reports stale past its maxAge —
 * no invented "current" values. Sources stay inside first-party tables;
 * provider/hosting metrics remain outside this slice.
 * @return array<int,array{sample:MetricSample,label:string,max_age:int}>
 */
function metric_samples(): array {
    $samples=[];
    $audit=one('SELECT MAX(created_at) AS last,COUNT(*) AS n FROM '.table('audit'));
    $samples[]=['sample'=>new MetricSample('audit.activity',$audit&&$audit['n']?$audit['n'].' recorded actions':null,metric_epoch($audit['last']??null)),'label'=>'Administration activity','max_age'=>30*86400];
    $lead=one('SELECT MAX(created_at) AS last,COUNT(*) AS n FROM '.table('leads'));
    $samples[]=['sample'=>new MetricSample('inquiry.latest',$lead&&$lead['n']?$lead['n'].' inquiries on record':null,metric_epoch($lead['last']??null)),'label'=>'Newest inquiry','max_age'=>30*86400];
    if(function_exists('delivery_ready')&&delivery_ready()){
        $release=one('SELECT MAX(published_at) AS last,COUNT(*) AS n FROM '.table('website_releases')." WHERE status='published' AND published_at IS NOT NULL");
        $samples[]=['sample'=>new MetricSample('delivery.published',$release&&$release['n']?$release['n'].' published releases':null,metric_epoch($release['last']??null)),'label'=>'Latest website delivery','max_age'=>30*86400];
    }else $samples[]=['sample'=>new MetricSample('delivery.published',null,null),'label'=>'Latest website delivery','max_age'=>30*86400];
    if(function_exists('notifications_feed_ready')&&notifications_feed_ready()){
        $delivery=one('SELECT MAX(updated_at) AS last,COUNT(*) AS n FROM '.table('notification_deliveries'));
        $samples[]=['sample'=>new MetricSample('notification.deliveries',$delivery&&$delivery['n']?$delivery['n'].' recorded attempts':null,metric_epoch($delivery['last']??null)),'label'=>'Notification deliveries','max_age'=>7*86400];
    }else $samples[]=['sample'=>new MetricSample('notification.deliveries',null,null),'label'=>'Notification deliveries','max_age'=>7*86400];
    return $samples;
}
