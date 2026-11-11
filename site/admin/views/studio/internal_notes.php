<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/projects/internal_notes.php';
$internalNotes=project_internal_notes((int)$id);
?>
<section class="workspace-card">
  <h2>Staff-only notes</h2>
  <p class="hint">Internal notes are never shown to clients, contacts or support sessions.</p>
  <?php foreach($internalNotes as $note): ?>
    <div class="workspace-message" id="note-<?=(int)$note['id']?>"><small><?=esc(($note['staff_name']??'Staff').' · staff only · '.$note['created_at'])?> UTC</small><?=esc($note['body'])?></div>
  <?php endforeach; ?>
  <form method="post"><?php admin_action('studio_note');studio_hidden('job_id',$id);admin_textarea('body','Staff-only note');?><button class="btn outline">Save internal note</button></form>
</section>
