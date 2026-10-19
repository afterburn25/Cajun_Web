<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
echo '<nav class="studio-tabs" aria-label="Hosting"><a href="'.esc(studio_url(['hosting'=>'offers'])).'">Available plans</a><a href="'.esc(hosting_purchases_ui_url(false)).'" aria-current="page">My hosting purchases</a><a href="'.esc(studio_url(['hosting'=>'requests'])).'">My requests</a></nav>';
hosting_purchases_ui_render($page,false);
