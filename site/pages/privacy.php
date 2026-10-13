<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

site_head('Privacy Notice','privacy');?><section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR INFORMATION</span><h1>Privacy notice.</h1><p>How information is used on this website.</p></div></section><section class="section"><div class="container narrow prose"><?=nl2br(esc(setting('privacy_text')))?></div></section><?php site_foot(false);
