<?php
/** Load both real notification interfaces: no database, network or transport. */
declare(strict_types=1);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/admin/views/payment-notifications.php';
require __DIR__.'/../site/includes/layout/notifications.php';
$checks=[];
foreach(['notifications_admin_action','notifications_admin_render'] as $name){$r=new ReflectionFunction($name);$ok=realpath($r->getFileName())===realpath(__DIR__.'/../site/admin/views/payment-notifications.php');$checks[]=['name'=>'Existing payment callback preserved '.$name,'passed'=>$ok];}
foreach(['admin_action','admin_render','portal_action','portal_render'] as $part){$r=new ReflectionFunction('notification_center_'.$part);$ok=realpath($r->getFileName())===realpath(__DIR__.'/../site/includes/layout/notifications.php');$checks[]=['name'=>'Distinct center callback loads '.$part,'passed'=>$ok];}
foreach(['actions'=>'action','router'=>'render'] as $path=>$part){$text=file_get_contents(__DIR__.'/../site/admin/controllers/'.$path.'.php');$checks[]=['name'=>'Center router selects distinct '.$part,'passed'=>str_contains($text,'notification_center_admin_'.$part.'(')];}
$text=file_get_contents(__DIR__.'/../site/admin/controllers/payments.php');
$checks[]=['name'=>'Payment router keeps its original notification callbacks','passed'=>str_contains($text,'notifications_admin_action($action)')&&str_contains($text,'notifications_admin_render($user,$error)')];
$passed=!in_array(false,array_column($checks,'passed'),true);echo json_encode(['passed'=>$passed,'scope'=>'Real PHP interfaces co-loaded; database and installed-browser checks remain separate','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
