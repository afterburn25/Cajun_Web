<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

 $rows=all('SELECT * FROM '.table('services').' ORDER BY sort_order,id');?><div class="admin-toolbar"><p>Manage the services shown on your homepage, services page and project inquiry form.</p><a class="btn gold small" href="<?=esc(admin_url('service'))?>">Add Service +</a></div><div class="admin-service-grid"><?php foreach($rows as $s):?><a class="admin-card" href="<?=esc(admin_url('service',['id'=>$s['id']]))?>"><?=icon($s['icon'])?><h2><?=esc($s['title'])?></h2><p><?=esc($s['summary'])?></p><span class="text-link"><?=$s['active']?'Active':'Hidden'?> · Edit ↗</span></a><?php endforeach;?></div><?php
