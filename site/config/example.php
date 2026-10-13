<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
// Documentation only. The installer writes the permanent root config.php.
return ['site_url'=>'https://example.invalid', 'storage'=>'/account/private/cajunwebpro', 'db'=>['host'=>'localhost','port'=>3306,'name'=>'','user'=>'','pass'=>'','prefix'=>'cwp_'], 'key'=>'GENERATED-BY-INSTALLER'];
