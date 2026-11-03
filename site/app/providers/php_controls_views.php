<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Presentation only: callers supply authorized, closed screen/review projections. */
function provider_php_controls_view_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function provider_php_controls_view_id(mixed $value, bool $empty=false): string
{
    if (!is_string($value) || ($value!=='' || !$empty) && preg_match('/\A[a-f0-9]{32}\z/', $value)!==1) throw new InvalidArgumentException('Invalid PHP form identity.');
    return $value;
}
function provider_php_controls_view_hash(mixed $value): string
{
    if (!is_string($value) || preg_match('/\A[a-f0-9]{64}\z/', $value)!==1) throw new InvalidArgumentException('Invalid PHP form review.');
    return $value;
}
function provider_php_controls_view_time(?int $epoch): string
{
    if ($epoch===null) return 'Not completed';
    if ($epoch<0 || $epoch>2147483647) throw new InvalidArgumentException('Invalid PHP observation time.');
    return '<time datetime="'.gmdate('Y-m-d\TH:i:s\Z',$epoch).'">'.gmdate('Y-m-d H:i:s',$epoch).' UTC</time>';
}
function provider_php_controls_view_url(string $url, ?int $page=null): string
{
    // Host and selected-website binding belongs to the controller, not a posted value.
    $parts=parse_url($url);
    $local=preg_match('/\A(?:\/(?!\/)|\?)/',$url)===1;
    $absolute=is_array($parts) && in_array($parts['scheme']??null,['http','https'],true) && is_string($parts['host']??null) && $parts['host']!=='';
    if ((!$local && !$absolute) || $parts===false || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20\x7f\\\\#]/',$url)) throw new InvalidArgumentException('Invalid PHP form destination.');
    if ($page===null) return $url;
    if ($page<1 || $page>100000) throw new InvalidArgumentException('Invalid PHP history page.');
    [$path,$query]=array_pad(explode('?',$url,2),2,'');
    parse_str($query,$parameters);$parameters['php_page']=$page;
    return $path.'?'.http_build_query($parameters,'','&',PHP_QUERY_RFC3986).'#php-controls-history';
}
function provider_php_controls_view_hidden(string $name,string $value): void
{
    echo '<input type="hidden" name="'.provider_php_controls_view_escape($name).'" value="'.provider_php_controls_view_escape($value).'">';
}
function provider_php_controls_view_form(string $action,string $linkId,string $url,string $attribute): void
{
    echo '<form method="post" action="'.provider_php_controls_view_escape($url).'" '.$attribute.'>';
    echo csrf_field();
    provider_php_controls_view_hidden('action',$action);
    provider_php_controls_view_hidden('link_id',$linkId);
}
function provider_php_controls_view_password(): void
{
    echo '<label>Your current password<input type="password" name="current_password" value="" required autocomplete="current-password"></label>';
}
function provider_php_controls_view_confirmation(string $label): void
{
    echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> '.provider_php_controls_view_escape($label).'</label>';
}

function provider_php_controls_view_observation(?array $read,string $kind): void
{
    $current=$kind==='current';
    echo '<section class="workspace-card" data-php-'.($current?'current':'available').'><h3>'.($current?'Current version':'Available versions').'</h3>';
    if ($read===null) echo '<p>No '.($current?'current version':'available versions').' check has been recorded for this connection.</p>';
    elseif ($read['state']==='pending') echo '<p>The latest check has not finished. No earlier result is used.</p>';
    elseif ($read['state']==='complete') {
        if ($current) echo '<p>Reported value: <strong>'.provider_php_controls_view_escape($read['value']).'</strong></p>';
        elseif ($read['options']===[]) echo '<p>The latest check returned no selectable versions.</p>';
        else {
            echo '<ul class="php-controls-options">';
            foreach ($read['options'] as $option) echo '<li><strong>'.provider_php_controls_view_escape($option['label']).'</strong> <span>('.provider_php_controls_view_escape($option['value']).')</span> · '.provider_php_controls_view_escape($option['handler']).'</li>';
            echo '</ul>';
        }
        if (!$read['usable']) echo '<p class="notice">This saved result cannot be used for a new change. Refresh and review the latest details.</p>';
    } elseif (in_array($read['state'],['unavailable','unknown'],true)) {
        echo '<p>'.($current?'The current version could not be checked.':'Available versions could not be checked. No version can be selected.').'</p>';
        if ($read['reason']==='access_changed') echo '<p>The connection or permission changed. Review the hosting assignment with the studio.</p>';
    } else throw new InvalidArgumentException('Invalid PHP observation state.');
    if ($read!==null) echo '<p class="hint">Latest check: '.provider_php_controls_view_time($read['finished_epoch']).'</p>';
    echo '</section>';
}

/** Inner single-website page only; no SQL, authorization, credential, DNS or provider calls. */
function provider_php_controls_render(array $screen,bool $owner,string $actionUrl,?array $review=null,array $retry=[]): void
{
    $linkId=provider_php_controls_view_id($screen['link_id']);
    $actionUrl=provider_php_controls_view_url($actionUrl);
    if (!in_array($screen['setup'],['prerequisites','delegation','lifecycle','ready'],true)) throw new InvalidArgumentException('Invalid PHP setup state.');
    foreach (['access','blocked','can_select','has_more'] as $key) if (!is_bool($screen[$key])) throw new InvalidArgumentException('Invalid PHP screen state.');
    if (!is_int($screen['page']) || $screen['page']<1 || $screen['page']>100000 || !array_is_list($screen['history']) || count($screen['history'])>10) throw new InvalidArgumentException('Invalid PHP history page.');
    $ready=$screen['setup']==='ready';$access=$ready && $screen['access'];
    $select=$access && !$screen['blocked'] && $screen['can_select']
        && ($screen['current']['state']??null)==='complete' && ($screen['current']['usable']??false)===true
        && ($screen['available']['state']??null)==='complete' && ($screen['available']['usable']??false)===true
        && is_array($screen['available']['options']??null) && $screen['available']['options']!==[];
    echo '<div class="php-controls" data-php-controls><section class="workspace-card"><h2>PHP settings</h2><p>Check the versions for this website, then review a change before confirming it.</p>';
    if (!$ready) {
        if (!$owner) echo '<p class="notice">PHP settings are not ready. Contact the studio for help.</p>';
        elseif ($screen['setup']==='prerequisites') echo '<p class="notice">Prepare PHP observations, client accounts and provider operation history in hosting settings first.</p>';
        else {
            echo '<p>Prepare the local permission and change history before using these controls. This does not contact 20i.</p>';
            provider_php_controls_view_form('php_controls_prepare',$linkId,$actionUrl,'data-php-prepare');provider_php_controls_view_password();
            echo '<button class="btn gold" type="submit">Prepare PHP settings</button></form>';
        }
    } elseif (!$access) echo '<p class="notice">Changes are unavailable for the current connection or permission. '.($owner?'Review the hosting assignment and saved connection.':'Contact the studio for help.').'</p>';
    if ($screen['blocked']) echo '<p class="notice">An earlier change request is unresolved. Check its result before requesting another change. It will not be sent again.</p>';
    echo '</section><div class="workspace-grid php-controls-observations">';
    provider_php_controls_view_observation($screen['current'],'current');
    provider_php_controls_view_observation($screen['available'],'available');
    echo '</div>';
    if ($access) {
        echo '<section class="workspace-card"><h3>Refresh observations</h3><p>Each button checks one value. Wait at least 30 seconds between checks; recent checks of the same hosting connection share this limit.</p><div class="field-grid">';
        foreach (['current'=>'Check current version','available'=>'Check available versions'] as $kind=>$label) {
            provider_php_controls_view_form('php_controls_read',$linkId,$actionUrl,'data-php-read="'.$kind.'"');
            provider_php_controls_view_hidden('kind',$kind);if ($owner) provider_php_controls_view_password();
            echo '<button class="btn outline" type="submit">'.$label.'</button></form>';
        }
        echo '</div></section>';
    }
    if ($review!==null) {
        foreach (['request_id','current_read_id','options_read_id'] as $key) provider_php_controls_view_id($review[$key]);
        provider_php_controls_view_hash($review['review_sha256']);
        echo '<section class="workspace-card" data-php-review><h3>Review PHP change</h3><dl class="php-controls-facts"><dt>Previous value</dt><dd>'.provider_php_controls_view_escape($review['before_value']).'</dd><dt>Selected version</dt><dd>'.provider_php_controls_view_escape($review['label']).' ('.provider_php_controls_view_escape($review['desired_value']).')</dd><dt>Handler</dt><dd>'.provider_php_controls_view_escape($review['handler']).'</dd><dt>Current version checked</dt><dd>'.provider_php_controls_view_time($review['current_observed_epoch']).'</dd><dt>Available versions checked</dt><dd>'.provider_php_controls_view_time($review['options_observed_epoch']).'</dd></dl>';
        echo '<p>Changing PHP can affect your website. Confirm only after checking that your website supports this version. A later check is needed to learn which value 20i reports.</p>';
        if ($select && $review['current_read_id']===$screen['current']['id'] && $review['options_read_id']===$screen['available']['id']) {
            provider_php_controls_view_form('php_controls_change',$linkId,$actionUrl,'data-php-confirm');
            foreach (['request_id','current_read_id','options_read_id','review_sha256'] as $key) provider_php_controls_view_hidden($key,$review[$key]);
            provider_php_controls_view_hidden('desired',$review['desired_value']);
            provider_php_controls_view_confirmation('I have reviewed this exact PHP change.');provider_php_controls_view_password();
            echo '<button class="btn gold" type="submit">Confirm PHP change</button></form>';
        } else echo '<p class="notice">These details changed or are unavailable. Reload and review the version before trying again.</p>';
        echo '</section>';
    } elseif ($select) {
        $requestId=array_key_exists('request_id',$retry)?provider_php_controls_view_id($retry['request_id']):bin2hex(random_bytes(16));
        $desired=is_string($retry['desired']??null)?$retry['desired']:null;$found=false;
        foreach ($screen['available']['options'] as $option) if ($option['value']===$desired) $found=true;
        echo '<section class="workspace-card"><h3>Choose a version</h3>';
        if ($desired!==null && !$found) echo '<p class="notice">Your previous choice is no longer available. Choose from the latest versions and review it again.</p>';
        provider_php_controls_view_form('php_controls_review',$linkId,$actionUrl,'data-php-select');
        provider_php_controls_view_hidden('request_id',$requestId);
        provider_php_controls_view_hidden('current_read_id',provider_php_controls_view_id($screen['current']['id']));
        provider_php_controls_view_hidden('options_read_id',provider_php_controls_view_id($screen['available']['id']));
        echo '<label>Available version<select name="desired" required><option value=""'.(!$found?' selected':'').'>Choose a version</option>';
        foreach ($screen['available']['options'] as $option) echo '<option value="'.provider_php_controls_view_escape($option['value']).'"'.($desired===$option['value']?' selected':'').'>'.provider_php_controls_view_escape($option['label'].' · '.$option['value'].' · '.$option['handler']).'</option>';
        echo '</select></label><button class="btn gold" type="submit">Review PHP change</button></form></section>';
    }
    if ($owner && $screen['delegation']!==null) {
        $delegation=$screen['delegation'];
        echo '<section class="workspace-card" data-php-delegation><h3>Client permission</h3><p>';
        echo $delegation['current']?'The primary client currently has permission to change PHP.':($delegation['enabled']?'A saved permission is no longer current. Review the assignment before enabling it again.':'The primary client does not have permission to change PHP.');
        echo '</p>';
        foreach (['enable'=>true,'disable'=>false] as $key=>$enabled) {
            $candidate=$delegation[$key];if ($candidate===null) continue;
            $expected=provider_php_controls_view_id($candidate['expected_permission_id'],true);$hash=provider_php_controls_view_hash($candidate['review_sha256']);
            $retained=$retry['delegation']??null;$eventId=bin2hex(random_bytes(16));
            if (is_array($retained) && ($retained['enabled']??null)===$enabled) {
                if (($retained['expected_permission_id']??null)!==$expected || ($retained['review_sha256']??null)!==$hash) throw new InvalidArgumentException('Reload the client permission review.');
                $eventId=provider_php_controls_view_id($retained['event_id']);
            }
            provider_php_controls_view_form('php_controls_delegate',$linkId,$actionUrl,'data-php-delegate="'.$key.'"');
            foreach (['expected_permission_id'=>$expected,'event_id'=>$eventId,'enabled'=>$enabled?'1':'0','review_sha256'=>$hash] as $name=>$value) provider_php_controls_view_hidden($name,$value);
            provider_php_controls_view_confirmation($enabled?'Allow the primary client to change PHP for this website.':'Remove the client’s permission to change PHP for this website.');provider_php_controls_view_password();
            echo '<button class="btn outline" type="submit">'.($enabled?'Enable client permission':'Disable client permission').'</button></form>';
        }
        echo '</section>';
    }
    echo '<section class="workspace-card" id="php-controls-history" data-php-history><h3>Change requests</h3>';
    if ($screen['history']===[]) echo '<p>No change requests are available on this page.</p>';
    foreach ($screen['history'] as $request) {
        $id=provider_php_controls_view_id($request['id']);
        if ($request['link_id']!==$linkId) throw new InvalidArgumentException('PHP history belongs to another website.');
        $phase=match($request['phase']) {'pending'=>'Awaiting a result record','not_sent'=>'Not sent','attempted'=>'Change attempted',default=>throw new InvalidArgumentException('Invalid PHP request state.')};
        $readback=match($request['readback']) {'pending'=>$request['phase']==='attempted'?'Awaiting a check':'No later check recorded','observed'=>'Requested value observed','different'=>'Value differs','unavailable'=>'Could not check',default=>throw new InvalidArgumentException('Invalid PHP check state.')};
        echo '<article class="workspace-card" id="php-request-'.$id.'" data-php-request="'.$id.'"><h4>'.$phase.'</h4><p>Requested '.provider_php_controls_view_time($request['approved_epoch']).'</p><p>From <strong>'.provider_php_controls_view_escape($request['before_value']).'</strong> to <strong>'.provider_php_controls_view_escape($request['desired_value']).'</strong></p>';
        if ($request['phase']==='not_sent') echo '<p>No provider change was started by this request.</p>';
        elseif ($request['phase']==='pending') echo '<p>The saved request is unresolved. Do not send it again.</p>';
        else echo '<p>An attempted change is not proof of the current version.</p>';
        echo '<p><strong>'.$readback.'</strong></p>';
        if ($request['observed_value']!==null) echo '<p>Later reported value: <strong>'.provider_php_controls_view_escape($request['observed_value']).'</strong>. This observation does not prove what caused the value.</p>';
        if ($request['readback_epoch']!==null) echo '<p>Checked '.provider_php_controls_view_time($request['readback_epoch']).'</p>';
        if ($request['recovered_from_ledger']) echo '<p>The request record was recovered from saved operation history. This did not resend the change.</p>';
        if ($access && $request['can_verify'] && $request['phase']==='attempted') {
            provider_php_controls_view_form('php_controls_verify',$linkId,$actionUrl,'data-php-verify');provider_php_controls_view_hidden('request_id',$id);provider_php_controls_view_password();
            echo '<button class="btn outline" type="submit">Check result</button></form>';
        }
        if ($owner && $access && $request['phase']==='pending' && $request['recovery_sha256']!==null) {
            provider_php_controls_view_form('php_controls_recover',$linkId,$actionUrl,'data-php-recover');provider_php_controls_view_hidden('request_id',$id);provider_php_controls_view_hidden('ledger_sha256',provider_php_controls_view_hash($request['recovery_sha256']));
            echo '<p>Restore the missing result record from the saved operation history, then check the reported version. This does not send a change.</p>';
            provider_php_controls_view_confirmation('Restore this request record without sending it again.');provider_php_controls_view_password();
            echo '<button class="btn outline" type="submit">Recover request record</button></form>';
        }
        echo '</article>';
    }
    if ($screen['page']>1 || $screen['has_more']) {
        echo '<nav class="button-row" aria-label="PHP change request pages">';
        if ($screen['page']>1) echo '<a class="btn outline" href="'.provider_php_controls_view_escape(provider_php_controls_view_url($actionUrl,$screen['page']-1)).'">Newer requests</a>';
        if ($screen['has_more'] && $screen['page']<100000) echo '<a class="btn outline" href="'.provider_php_controls_view_escape(provider_php_controls_view_url($actionUrl,$screen['page']+1)).'">Older requests</a>';
        echo '</nav>';
    }
    echo '</section></div>';
}
