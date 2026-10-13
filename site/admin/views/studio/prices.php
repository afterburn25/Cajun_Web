<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

      $r=growth_rules();?><section class="workspace-card"><p>All prices start unpublished. Base prices include five pages. Additional pages and each product are added using these ranges. Blank rules and custom integrations require a consultation.</p><form method="post"><?php admin_action('studio_prices');studio_hidden('expected',hash('sha256',setting('growth_prices')));admin_check('enabled','Publish these estimate ranges',($r['enabled']??false)===true);foreach(growth_types()+['page'=>'Each additional page','product'=>'Each product']+growth_options() as $k=>$label):?><fieldset><legend><?=esc($label)?></legend><div class="field-grid"><?php admin_input($k.'_min','Minimum USD',gmoney($r[$k][0]??null));admin_input($k.'_max','Maximum USD',gmoney($r[$k][1]??null));?></div></fieldset><?php endforeach;?><button class="btn gold">Save price rules</button></form></section><?php
