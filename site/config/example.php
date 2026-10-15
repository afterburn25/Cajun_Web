<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
// Documentation only. The installer writes the permanent root config.php.
return ['site_url'=>'https://example.invalid', 'storage'=>'/account/private/cajunwebpro', 'db'=>['host'=>'localhost','port'=>3306,'name'=>'','user'=>'','pass'=>'','prefix'=>'cwp_'], 'key'=>'GENERATED-BY-INSTALLER',
// Absolute path to a Website Core checkout (site/ + examples/ dirs). Required
// for license bundle generation; without it licenses are still created and
// bundles can be generated later once the source is deployed.
'license_core_dir'=>'/opt/website-core'];
