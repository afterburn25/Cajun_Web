<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

      foreach(['growth_intro'=>['Homepage introduction','Bring your brand to life with purposeful design and development.'],'growth_faq'=>['FAQ items: JSON question/answer pairs',json_encode(growth_faq(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)],'growth_packages'=>['Package items: JSON title/summary/inclusions',json_encode(growth_packages(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)]] as $name=>[$label,$fallback]):?><section class="workspace-card"><form method="post"><?php admin_action('studio_content');studio_hidden('setting_name',$name);studio_hidden('expected',hash('sha256',setting($name)));admin_textarea('value',$label,setting($name,$fallback),10,24000);?><button class="btn gold">Save this section</button></form></section><?php endforeach;
