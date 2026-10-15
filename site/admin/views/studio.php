<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_admin_render(string $view,array $user,string $error): void {
    if($view==='studio-job'&&isset($_GET['file']))studio_download(is_string($_GET['file'])?$_GET['file']:'',null);
    admin_head(studio_admin_views()[$view],$view,$user);if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(!studio_ready()&&$view!=='studio-upgrade'){echo '<div class="notice warning">The new database tables are not enabled. Your existing website is unchanged. Open <a href="'.esc(admin_url('studio-upgrade')).'">Version &amp; database</a> to apply the additive update.</div>';admin_foot();return;}
    if($view==='studio-upgrade'){require CWP_CODE_ROOT.'/admin/views/studio/upgrade.php';
}elseif($view==='studio-clients'){require CWP_CODE_ROOT.'/admin/views/studio/clients.php';
}elseif($view==='studio-job'){require CWP_CODE_ROOT.'/admin/views/studio/job.php';
}elseif($view==='studio-pipeline'){require CWP_CODE_ROOT.'/admin/views/studio/pipeline.php';
}elseif($view==='studio-prices'){require CWP_CODE_ROOT.'/admin/views/studio/prices.php';
}elseif($view==='studio-content'){require CWP_CODE_ROOT.'/admin/views/studio/content.php';
}elseif($view==='studio-history'){require CWP_CODE_ROOT.'/admin/views/studio/history.php';
}elseif($view==='studio-licenses'){require CWP_CODE_ROOT.'/admin/views/studio/licenses.php';
}elseif($view==='studio-portfolio'){require CWP_CODE_ROOT.'/admin/views/studio/portfolio.php';
    }
    admin_foot();
}
