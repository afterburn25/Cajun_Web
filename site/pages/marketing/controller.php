<?php
declare(strict_types=1);defined('CWP_BOOT') || exit;
$title=['why-custom'=>'Why Custom','compare-wix'=>'CajunWebPro vs Wix','compare-squarespace'=>'CajunWebPro vs Squarespace','switch'=>'Plan Your Website Move','packages'=>'Website Packages','faq'=>'Your Questions, Answered','industries'=>'Websites Built Around Your Business','estimate'=>'Plan Your Project','website-review'=>'Request a Website Review','consultation'=>'Request a Consultation'][$page];
$error='';$success=null;$estimate=null;$a=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{check_csrf();$action=gtext($_POST,'action',40);if($action==='calculate'&&$page==='estimate'){$estimate=growth_estimate($_POST,growth_rules());}
 elseif($action==='growth_submit'&&in_array($page,['estimate','website-review','consultation'],true)){$_POST['kind']=$page;$success=growth_submit($_POST);}
 else throw new InvalidArgumentException('Unknown action.');
 }catch(InvalidArgumentException|RuntimeException $e){$error=$e->getMessage();}catch(Throwable $e){$error='This request could not complete. Reference: '.report_error($e);}
 if(($_SERVER['HTTP_ACCEPT']??'')==='application/json'){header('Content-Type: application/json');http_response_code($error!==''?422:200);echo json_encode(['ok'=>$error==='','message'=>$error,'result'=>$success,'estimate'=>$estimate],JSON_HEX_TAG|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
}
site_head($title,$page);
require_once CWP_CODE_ROOT.'/includes/layout/marketing.php';
require CWP_CODE_ROOT.'/pages/marketing/content.php';
