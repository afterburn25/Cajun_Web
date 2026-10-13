<?php
declare(strict_types=1);define('CWP_BOOT',true);require __DIR__.'/app/core.php';boot(false);require_once __DIR__.'/app/growth.php';header('Content-Type: application/xml; charset=utf-8');header('Cache-Control: public, max-age=900');
function xml_escape(string $s): string{return htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
if(setting('site_published')==='1'){foreach(array_merge(['home','about','services','work','process','contact','privacy'],growth_routes()) as $p)echo '<url><loc>'.xml_escape(page_url($p)).'</loc></url>'."\n";foreach(all('SELECT slug,updated_at FROM '.table('projects').' WHERE status=\'published\'') as $p)echo '<url><loc>'.xml_escape(page_url('project',['slug'=>$p['slug']])).'</loc><lastmod>'.gmdate('Y-m-d',strtotime($p['updated_at'])).'</lastmod></url>'."\n";}
echo '</urlset>';
