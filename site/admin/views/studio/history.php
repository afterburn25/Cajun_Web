<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

      ?><p>Restoring creates a new recorded revision. Current content is checked for changes before a restore can apply.</p><?php foreach(all('SELECT * FROM '.table('studio_revisions').' ORDER BY id DESC LIMIT 100') as $r):?><section class="workspace-card"><h3><?=esc($r['setting_name'])?> · <?=esc($r['created_at'])?> UTC</h3><details><summary>Compare saved values</summary><h4>Before</h4><pre class="history-text"><?=esc($r['old_value'])?></pre><h4>After</h4><pre class="history-text"><?=esc($r['new_value'])?></pre></details><form method="post"><?php admin_action('studio_restore_content');studio_hidden('id',$r['id']);studio_hidden('expected',hash('sha256',setting($r['setting_name'])));admin_check('confirm','Restore the Before value as a new revision.',false);?><button class="btn outline">Restore this value</button></form></section><?php endforeach;
    