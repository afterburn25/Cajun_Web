<?php
/** Direct-staff-only immutable project notes. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="workspace-card">
  <h2>Private staff notes</h2>
  <p>These immutable notes are visible only to directly signed-in staff with client-management access. They are never added to the client conversation, notifications, activity, email, or support sessions.</p>
  <?php if(!project_private_notes_ready()): ?>
    <div class="notice warning">Private project note storage is not prepared. Review the additive setup under <a href="<?=esc(admin_url('studio-upgrade'))?>">Version &amp; database</a>.</div>
  <?php else: ?>
    <?php foreach(project_private_notes_list($id) as $entry): $note=$entry['note']->forStaffProject($id); ?>
      <div class="workspace-message" id="private-note-<?=(int)$note['id']?>">
        <small><?=esc($entry['staff_name'].' · '.gmdate('Y-m-d H:i:s',(int)$note['created_at']).' UTC')?></small>
        <p><?=nl2br(esc($note['body']),false)?></p>
      </div>
    <?php endforeach; ?>
    <form method="post">
      <?php admin_action('studio_private_note');studio_hidden('job_id',$id);admin_textarea('private_note_body','Private staff note','',6,5000); ?>
      <button class="btn gold">Save immutable private note</button>
    </form>
  <?php endif; ?>
</section>
