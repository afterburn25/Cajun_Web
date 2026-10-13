<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
 ?>
      <section class="workspace-card"><span class="eyebrow">ONE EXPLICIT UPDATE</span><h2>Application <?=esc(CWP_VERSION)?></h2><p>Workspace schema: <?=studio_ready()?'current':'not enabled / needs update'?>. This creates or verifies the client-workspace and content-history tables. It does not replace existing website tables or reset your settings.</p><p>Back up your files and database before applying an update. This is a database update, not an application-file updater or a complete backup service.</p><form method="post"><?php admin_action('studio_migrate');admin_check('confirm','I have a current database and file backup.',false);?><button class="btn gold">Verify & apply database update →</button></form></section>
    <?php
