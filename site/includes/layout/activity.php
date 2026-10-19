<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function activity_post(string $audience): never {
    check_csrf();$action=posted('action',40);
    if($action==='activity_setup'&&$audience==='admin')activity_migrate(posted('password',1000));
    elseif($action==='activity_mark'){
        $keys=json_decode(posted('keys',3000),true);$state=posted('state',10);
        if(!is_array($keys)||!in_array($state,['read','unread'],true))throw new InvalidArgumentException('Invalid activity request.');
        activity_mark($audience,$keys,$state==='read');
    }
    elseif($action==='activity_prefs'){
        $hidden=$_POST['hide']??[];if(!is_array($hidden))throw new InvalidArgumentException('Invalid preferences request.');
        activity_prefs_save(activity_actor($audience),$hidden);
    }else throw new InvalidArgumentException('Unknown activity action.');
    flash($action==='activity_setup'?'Workspace activity is enabled. Existing updates start unread.':($action==='activity_prefs'?'Your update preferences were saved.':'Your activity read status was saved.'));redirect(activity_url($audience));
}
function activity_form(string $audience,array $keys,string $state,string $label): void {
    echo '<form method="post" action="'.esc(activity_url($audience)).'">'.csrf_field().'<input type="hidden" name="action" value="activity_mark"><input type="hidden" name="keys" value="'.esc(json_encode($keys,JSON_THROW_ON_ERROR)).'"><input type="hidden" name="state" value="'.esc($state).'"><button class="btn outline small">'.esc($label).'</button></form>';
}
function activity_render(string $audience,string $error=''): never {
    try{
        if(!activity_ready()&&$audience==='admin'){
            $user=staff_require('clients.manage');admin_head('Workspace activity','activity',$user);
            if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
            echo '<section class="admin-card"><h2>Enable workspace activity</h2><p>Project updates and a personal unread list for clients and staff. Retained updates initially appear unread. This does not send email.</p>';
            if(staff_can('staff.manage',$user)){echo '<p>Back up your database first. This adds read status without changing projects, messages, invoices or website files.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="activity_setup">';admin_input('password','Your owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable workspace activity</button></form>';}
            else echo '<p>Ask an owner to enable workspace activity.</p>';
            echo '</section>';admin_foot();exit;
        }
        $actor=activity_actor($audience);
        if(isset($_GET['open']))redirect(activity_destination($audience,is_string($_GET['open'])?$_GET['open']:''));
        $unread=($_GET['filter']??'')==='unread';$before=is_string($_GET['before']??null)?$_GET['before']:'';
        $feed=activity_feed($audience,$unread,$before);$count=activity_count($audience);
    }catch(PDOException $e){report_error($e);fail_page('Workspace activity is temporarily unavailable. Your project records are unchanged.',503);}
    catch(InvalidArgumentException|RuntimeException){fail_page('Activity unavailable. Sign in with an authorized account or return to the activity center and refresh.',403);}
    if($audience==='admin')admin_head('Workspace activity','activity',staff_require('clients.manage'));
    else{site_head('Workspace activity','portal');echo '<section class="section"><div class="container"><span class="eyebrow">YOUR PROJECT UPDATES</span><h1>Workspace activity</h1><p><a href="'.esc(studio_url()).'">← All projects</a></p>';show_flash();}
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    echo '<p>Messages, reviews, files'.($audience==='client'?', stage updates, invoices, payments and website releases':', stage updates and client decisions').'. Opening an update keeps its read status until you change it. All times are UTC.</p><div class="activity-toolbar"><strong>'.($count>=100?'99+':$count).' unread</strong><nav aria-label="Activity filter"><a href="'.esc(activity_url($audience)).'"'.(!$unread?' aria-current="page"':'').'>All updates</a><a href="'.esc(activity_url($audience,['filter'=>'unread'])).'"'.($unread?' aria-current="page"':'').'>Unread</a></nav>';
    if($feed['rows'])activity_form($audience,array_column($feed['rows'],'event_key'),'read','Mark visible updates read');echo '</div><div class="activity-list">';
    $labels=['message'=>$audience==='client'?'Studio message':'Client message','file'=>'Private file added','review'=>'Review published','invoice'=>'Invoice issued','payment'=>'Payment history updated','release'=>'Website release published','decision'=>'Client review decision','milestone'=>'Milestone update','stage'=>'Project stage updated'];
    $senders=['admin'=>'Studio message','client'=>'Client message','contact'=>'Contact message'];
    foreach($feed['rows'] as $r){$read=$r['read_at']!==null;$label=$r['kind']==='message'?($senders[$r['author_type']??'']??$labels['message']):$labels[$r['kind']];
        echo '<article class="workspace-card activity-item" data-event-key="'.esc($r['event_key']).'"><div><span class="workspace-status">'.($read?'Read':'Unread').'</span><span class="eyebrow">'.esc($label).'</span><h2><a href="'.esc(activity_url($audience,['open'=>$r['event_key']])).'">'.esc($r['project_title']).'</a></h2>';
        if($r['item_title']!=='')echo '<p>'.esc($r['item_title']).'</p>';
        echo '<time>'.esc($r['occurred_at']).' UTC</time></div>';activity_form($audience,[$r['event_key']],$read?'unread':'read',$read?'Mark unread':'Mark read');echo '</article>';
    }
    if(!$feed['rows'])echo '<section class="workspace-card"><h2>'.($unread?'You’re all caught up.':'No updates yet.').'</h2><p>New project activity will appear here.</p></section>';
    echo '</div>';if($feed['next']!=='')echo '<p><a class="btn outline" href="'.esc(activity_url($audience,['filter'=>$unread?'unread':'all','before'=>$feed['next']])).'">Older updates →</a></p>';
    if($audience==='admin'&&activity_ready()&&!activity_prefs_ready()&&staff_can('staff.manage')){echo '<section class="workspace-card"><h2>Enable update preferences</h2><p>Prepare the additive storage that lets each staff member and client hide update types. Re-running setup never changes projects, messages, invoices or read history.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="activity_setup">';admin_input('password','Your owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable update preferences</button></form></section>';}
    if(activity_prefs_ready()){
        $kinds=activity_kinds($actor);$hiddenSet=array_flip(activity_disabled($actor));
        echo '<section class="workspace-card"><h2>Update preferences</h2><p>Hidden update types leave your feed and unread count until you show them again. The project record is unchanged.</p><form method="post" action="'.esc(activity_url($audience)).'">'.csrf_field().'<input type="hidden" name="action" value="activity_prefs">';
        foreach($kinds as $kind)echo '<label class="check"><input type="checkbox" name="hide[]" value="'.esc($kind).'"'.(isset($hiddenSet[$kind])?' checked':'').'> Hide '.esc($labels[$kind]??$kind).'</label>';
        echo '<button class="btn outline">Save preferences</button></form></section>';
    }
    if($audience==='admin')admin_foot();else{echo '</div></section>';site_foot(false);}exit;
}
