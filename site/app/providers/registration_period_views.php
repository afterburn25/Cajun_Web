<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function provider_registration_period_message(string $state):string {
    return match($state){
        'not_prepared','not_observed'=>'Registration period options have not been observed yet.',
        'complete'=>'Observed registration period options',
        'partial'=>'Partial registration period observation; some extensions could not be validated.',
        'pending'=>'The latest registration period lookup did not finish. Current options are unknown.',
        'settings_changed'=>'The provider settings changed after this observation. Refresh the options under the current settings.',
        default=>'The latest registration period lookup was unavailable. Current options are unknown.',
    };
}
function provider_registration_period_rows(array $context):void {
    echo '<p>'.esc(provider_registration_period_message($context['state'])).'</p>';
    if($context['at']!==null)echo '<p class="hint">Recorded '.esc($context['at']).' UTC</p>';
    if(in_array($context['state'],['complete','partial'],true)&&$context['rows']===[])echo '<p>No registration period options were returned in this observation.</p>';
    if($context['rows']){echo '<dl class="registration-period-options">';foreach($context['rows'] as $row){echo '<dt>'.esc($row['tld']).'</dt><dd>'.esc($row['years']===null?'Not returned in this observation':($row['years']===[]?'No options returned':implode(', ',array_map(static fn(int $year):string=>$year.' '.($year===1?'year':'years'),$row['years'])))).'</dd>';}echo '</dl>';}
    echo '<p class="hint">These are saved registration options for planning. They do not establish a price, domain availability, renewal eligibility or a reservation.</p>';
}
