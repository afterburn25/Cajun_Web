<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_http.php';
require_once CWP_CODE_ROOT.'/app/providers/php_controls_screen.php';
require_once CWP_CODE_ROOT.'/app/providers/php_controls_views.php';

function provider_php_controls_client_action(string $action,array $client): ?array
{
    try { $result=provider_php_controls_http_action(false,(int)$client['id'],$action); }
    catch (PDOException|InvalidArgumentException|RuntimeException $e) { throw $e; }
    catch (Throwable $e) { throw new RuntimeException('The PHP request could not be completed. Reference: '.report_error($e)); }
    if ($result['kind']==='review') return $result['review'];
    flash($result['message'],$result['tone']);
    redirect(studio_url(['php_controls'=>1,'service_link'=>provider_php_controls_http_link(false,true)]));
}

function provider_php_controls_client_settings_render(array $client,string $error,?array $review=null): never
{
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    provider_php_controls_http_session_gate();
    try { staff_locked(fn()=>staff_transaction(fn()=>provider_php_controls_primary_client((int)$client['id']))); }
    catch (InvalidArgumentException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        fail_page('Sign in directly as the primary client to use PHP settings.',403);
    }
    site_head('Website PHP settings','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR HOSTING</span><h1>Website <span class="gold-text">PHP settings</span></h1><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    show_flash();if ($error!=='') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try {
        $link=provider_php_controls_http_link(false);
        $page=is_string($_GET['php_page']??null)?max(1,min(10000,(int)$_GET['php_page'])):1;
        $screen=provider_php_controls_screen_client((int)$client['id'],$link,$page);
        [$review,$retry]=provider_php_controls_http_retry(false,(int)$client['id'],$screen,$review);
        if ($review!==null && (!$screen['can_select'] || ($screen['current']['id']??null)!==$review['current_read_id'] || ($screen['available']['id']??null)!==$review['options_read_id'])) {
            $review=null;echo '<p class="notice error" role="alert">PHP information changed. Check the current and available versions before reviewing another change.</p>';
        }
        provider_php_controls_render($screen,false,studio_url(['php_controls'=>1,'service_link'=>$link]),$review,$retry);
    } catch (PDOException $e) {
        echo '<p class="notice error" role="alert">PHP settings could not be loaded. Reference: '.esc(report_error($e)).'</p>';
    } catch (InvalidArgumentException|RuntimeException $e) {
        echo '<p class="notice error" role="alert">PHP settings are unavailable for this website. Ask the studio to review your access.</p>';
    } catch (Throwable $e) {
        echo '<p class="notice error" role="alert">PHP settings could not be loaded. Reference: '.esc(report_error($e)).'</p>';
    }
    echo '</div></section>';site_foot(false);exit;
}
