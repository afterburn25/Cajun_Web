<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }boot(false);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: public, max-age=900');
echo "User-agent: *\n";if(setting('site_published')!=='1')echo "Disallow: /\n";else{foreach(['admin/','portal/','install.php','install/','api/','includes/','app/','config/','database/','storage/','upload/'] as $p)echo 'Disallow: '.base_path().'/'.$p."\n";echo 'Sitemap: '.site_url('api/sitemap.php')."\n";}
