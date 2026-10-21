<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_http.php';
require_once CWP_CODE_ROOT.'/app/providers/php_controls_screen.php';
require_once CWP_CODE_ROOT.'/app/providers/php_controls_views.php';

function provider_php_controls_admin_action(string $action): ?array
{
    try { $result=provider_php_controls_http_action(true,null,$action); }
    catch (PDOException|InvalidArgumentException|RuntimeException $e) { throw $e; }
    catch (Throwable $e) { throw new RuntimeException('The PHP request could not be completed. Reference: '.report_error($e)); }
    if ($result['kind']==='review') return $result['review'];
    flash($result['message'],$result['tone']);
    redirect(admin_url('provider-php-controls',['service_link'=>provider_php_controls_http_link(true,true)]));
}

function provider_php_controls_admin_render(string $error,?array $review=null): never
{
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try { $owner=provider_settings_manager(false); }
    catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        http_response_code(403);admin_head('PHP settings unavailable','provider-settings',require_admin());
        echo '<p class="notice error" role="alert">An active owner must open these PHP settings outside client support.</p>';admin_foot();exit;
    }
    admin_head('Website PHP settings','provider-settings',$owner);
    echo '<p><a class="btn outline" href="'.esc(admin_url('provider-settings')).'#package-php-version">Back to hosting websites</a></p>';
    if ($error!=='') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try {
        $link=provider_php_controls_http_link(true);
        $page=is_string($_GET['php_page']??null)?max(1,min(10000,(int)$_GET['php_page'])):1;
        $screen=provider_php_controls_screen_owner($link,$page);
        [$review,$retry]=provider_php_controls_http_retry(true,null,$screen,$review);
        if ($review!==null && (!$screen['can_select'] || ($screen['current']['id']??null)!==$review['current_read_id'] || ($screen['available']['id']??null)!==$review['options_read_id'])) {
            $review=null;echo '<p class="notice error" role="alert">PHP information changed. Check the current and available versions before reviewing another change.</p>';
        }
        if (($_POST['action']??null)==='php_controls_delegate' && !isset($retry['delegation'])) echo '<p class="notice error" role="alert">Reload this page to review the current client permission before submitting it again.</p>';
        provider_php_controls_render($screen,true,admin_url('provider-php-controls',['service_link'=>$link]),$review,$retry);
    } catch (PDOException $e) {
        echo '<p class="notice error" role="alert">PHP settings could not be loaded. Reference: '.esc(report_error($e)).'</p>';
    } catch (InvalidArgumentException|RuntimeException $e) {
        echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';
    } catch (Throwable $e) {
        echo '<p class="notice error" role="alert">PHP settings could not be loaded. Reference: '.esc(report_error($e)).'</p>';
    }
    admin_foot();exit;
}
