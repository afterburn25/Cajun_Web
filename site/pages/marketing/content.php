<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
if(in_array($page,['why-custom','compare-wix','compare-squarespace'],true)){require CWP_CODE_ROOT.'/pages/comparisons/index.php';
}elseif($page==='switch'){require CWP_CODE_ROOT.'/pages/comparisons/switch.php';
}elseif($page==='packages'){require CWP_CODE_ROOT.'/pages/services/packages.php';
}elseif($page==='industries'){require CWP_CODE_ROOT.'/pages/services/industries.php';
}elseif($page==='faq'){require CWP_CODE_ROOT.'/pages/services/faq.php';
}else{require CWP_CODE_ROOT.'/pages/contact/request.php';
}
site_foot();
