<?php
/** Public PHP front controller. MySQL data is loaded on the server, never from a mockup. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }


boot();
require CWP_CODE_ROOT.'/includes/layout/public.php';
require_once CWP_CODE_ROOT.'/app/marketing/growth.php';
require_once CWP_CODE_ROOT.'/app/marketing/comparisons.php';
require_once CWP_CODE_ROOT.'/app/portfolio/browse.php';
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
$allowed = ['home','about','services','work','project','process','contact','privacy','thanks'];
if(in_array($page,growth_routes(),true)){require CWP_CODE_ROOT.'/pages/marketing/controller.php';exit;}
if (!in_array($page, $allowed, true)) {
    $page = '404';
    http_response_code(404);
}
$services = all('SELECT * FROM ' . table('services') . ' WHERE active=1 ORDER BY sort_order,id');
if ($page === 'home') {require CWP_CODE_ROOT.'/pages/home/index.php';
}elseif($page==='work'){require CWP_CODE_ROOT.'/pages/portfolio/index.php';
}elseif($page==='project'){require CWP_CODE_ROOT.'/pages/portfolio/project.php';
}elseif($page==='services'){require CWP_CODE_ROOT.'/pages/services/index.php';
}elseif($page==='about'){require CWP_CODE_ROOT.'/pages/about.php';
}elseif($page==='process'){require CWP_CODE_ROOT.'/pages/process.php';
}elseif($page==='contact'){require CWP_CODE_ROOT.'/pages/contact/index.php';
}elseif($page==='privacy'){require CWP_CODE_ROOT.'/pages/privacy.php';
}elseif($page==='thanks'){require CWP_CODE_ROOT.'/pages/contact/thanks.php';
}else{
site_head('Page Not Found');?><section class="page-hero"><div class="container"><span class="eyebrow">404 / A DIFFERENT DIRECTION</span><h1>Let's get you<br><span class="gold-text">back on track.</span></h1><p>This page could not be found.</p><a class="btn gold" href="<?=esc(page_url())?>">Back to the studio →</a></div></section><?php site_foot(false);
}
