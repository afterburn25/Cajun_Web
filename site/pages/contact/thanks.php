<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

site_head('Thank You','contact');?><section class="page-hero"><div class="container"><span class="eyebrow">MESSAGE RECEIVED</span><h1>Your next chapter<br><span class="gold-text">starts with a conversation.</span></h1><?php show_flash();?><a class="btn gold" href="<?=esc(page_url())?>">Back to the studio →</a></div></section><?php site_foot(false);
