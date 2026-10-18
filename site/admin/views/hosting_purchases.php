<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
echo '<nav class="button-row" aria-label="Hosting"><a class="btn outline" href="'.esc(admin_url('hosting-offers')).'">Hosting offers</a><a class="btn outline" aria-current="page" href="'.esc(hosting_purchases_ui_url(true)).'">Hosting purchases</a><a class="btn outline" href="'.esc(admin_url('hosting-offers',['tab'=>'requests'])).'">Client requests</a></nav>';
hosting_purchases_ui_render($page,true);
