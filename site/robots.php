<?php
declare(strict_types=1);define('CWP_BOOT',true);require __DIR__.'/app/core.php';boot(false);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: public, max-age=900');
echo "User-agent: *\n";if(setting('site_published')!=='1')echo "Disallow: /\n";else{foreach(['admin.php','install.php','api.php','app/','storage/'] as $p)echo 'Disallow: '.base_path().'/'.$p."\n";echo 'Sitemap: '.site_url('sitemap.php')."\n";}
