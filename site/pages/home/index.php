<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

    $projects = all('SELECT * FROM ' . table('projects') . " WHERE status='published' AND featured=1 ORDER BY sort_order,id LIMIT 3");
    $testimonials = all('SELECT * FROM ' . table('testimonials') . ' WHERE published=1 AND consent=1 ORDER BY sort_order,id LIMIT 3');
    site_head('Custom Web Design & Development');
    require CWP_CODE_ROOT.'/pages/home/body.php';
    site_foot();
