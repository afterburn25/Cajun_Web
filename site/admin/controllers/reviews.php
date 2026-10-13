<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/marketing/reviews.php';

function reviews_post_findings(): array {
    $items=$_POST['findings']??[];
    if (!is_array($items) || !array_is_list($items) || count($items)>20) throw new InvalidArgumentException('Use from 1 to 20 finding rows.');
    return $items;
}
function reviews_admin_action(string $action): void {
    $id=posted('id',10);if (!ctype_digit($id) || (int)$id<1) throw new InvalidArgumentException('Choose a website review inquiry.');$id=(int)$id;
    if ($action==='reviews_setup') reviews_migrate(posted('current_password',200));
    elseif ($action==='reviews_adopt') reviews_adopt($id,posted('website',500));
    else {
        reviews_actor();$revision=posted('revision',10);
        if (!ctype_digit($revision)) throw new InvalidArgumentException('Reload the current review form.');$revision=(int)$revision;
        if ($action==='reviews_report') {
            $items=reviews_post_findings();
            if (array_key_exists('remove_finding',$_POST)) {
                $remove=posted('remove_finding',2);
                if (!ctype_digit($remove) || count($items)<=1 || (int)$remove>=count($items)) throw new InvalidArgumentException('Choose a finding to remove; keep at least one.');
                return;
            }
            if (posted('add_finding',1)==='1') {
                if (count($items)>=20) throw new InvalidArgumentException('A report can have at most 20 findings.');
                // Render one extra row without changing saved state or validating unfinished content.
                return;
            }
            reviews_save_report($id,$revision,$_POST['summary']??null,$items);
        } elseif ($action==='reviews_stage') reviews_stage($id,$revision,posted('stage',30));
        elseif ($action==='reviews_delivery') reviews_deliver($id,$revision,posted('delivered_at',19),posted('channel',30),posted('reference',500),posted('current_password',200));
        else throw new InvalidArgumentException('Unknown review action.');
    }
    flash('Review record saved. No website was fetched or report sent.');redirect(admin_url('website-review',['id'=>$id]));
}
function reviews_admin_render(string $error): void {
    try {$actor=reviews_actor(false);} catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        http_response_code(403);admin_head('Review access unavailable','security',require_admin());echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;
    }
    $id=(int)($_GET['id']??0);admin_head('Website review','leads',$actor);
    if ($error!=='') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    try {
        $lead=reviews_lead($id);
        if (!reviews_ready()) {
            echo '<section class="workspace-card"><h2>Prepare website reviews</h2><p>Track human review findings and report history alongside your inquiries. Existing inquiries stay unchanged.</p>';
            if ($actor['role']==='owner') {
                echo '<form method="post">';admin_action('reviews_setup');echo '<input type="hidden" name="id" value="'.$id.'">';admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable website review storage</button></form>';
            } else echo '<p>An owner must enable review storage first.</p>';
            echo '</section>';
        } else require CWP_CODE_ROOT.'/admin/views/reviews.php';
    } catch (InvalidArgumentException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';
    }
    echo '<p><a href="'.esc(admin_url('leads')).'">Back to inquiries</a></p>';admin_foot();exit;
}
