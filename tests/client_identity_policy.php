<?php
/** Pure UX-09 identity, token-domain, and closed-resume policy checks. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
$GLOBALS['cwp_config']=['key'=>str_repeat('a',64),'site_url'=>'https://example.invalid','db'=>['prefix'=>'cwp_']];
$GLOBALS['cwp_identity_settings']=['client_identity_schema_version'=>'1','email_mode'=>'smtp','mail_from'=>'studio@example.invalid','smtp_host'=>'smtp.example.invalid','smtp_port'=>'587','smtp_security'=>'tls','smtp_user'=>'','smtp_password'=>''];
function config():array{return $GLOBALS['cwp_config'];}
function studio_ready():bool{return true;}
function contacts_ready():bool{return true;}
function setting(string $name,string $default=''):string{return $GLOBALS['cwp_identity_settings'][$name]??$default;}
function studio_url(array $query=[]):string{return 'https://example.invalid/portal/index.php'.($query?'?'.http_build_query($query):'');}
require CWP_CODE_ROOT.'/app/clients/public_identity.php';
$checks=[];$failure=null;
function ci_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function ci_refuses(callable $operation):bool{try{$operation();return false;}catch(InvalidArgumentException|RuntimeException|LogicException){return true;}}
try{
    ci_ok('Email normalization is lowercase and trimmed',client_identity_email('  Person@Example.INVALID ')==='person@example.invalid');
    ci_ok('Configured bounded SMTP exposes public account requests',client_identity_request_ready());
    $GLOBALS['cwp_identity_settings']['email_mode']='disabled';ci_ok('Disabled mail hides public account requests without disabling existing security links',!client_identity_request_ready()&&client_identity_ready());$GLOBALS['cwp_identity_settings']['email_mode']='smtp';
    foreach(['','not-an-email',str_repeat('a',191).'@example.invalid'] as $i=>$email)ci_ok('Invalid email is refused '.$i,ci_refuses(fn()=>client_identity_email($email)));
    $fingerprint=hash_hmac('sha256','client-email-v1|person@example.invalid',str_repeat('a',64));
    ci_ok('Email fingerprint freezes the v1 domain separator',hash_equals($fingerprint,client_identity_email_fingerprint('Person@Example.INVALID')));
    $token=str_repeat('b',64);$verify=hash_hmac('sha256','client-token-v1|verify|'.$token,str_repeat('a',64));$recover=hash_hmac('sha256','client-token-v1|recover|'.$token,str_repeat('a',64));
    ci_ok('Verification digest freezes the purpose-bound v1 domain',hash_equals($verify,client_identity_token_digest('verify',$token)));
    ci_ok('Recovery digest is distinct for the same raw token',hash_equals($recover,client_identity_token_digest('recover',$token))&&!hash_equals($verify,$recover));
    foreach([['other',$token],['verify','ABC'],['recover',str_repeat('0',63)]] as $i=>$case)ci_ok('Invalid token input is refused '.$i,ci_refuses(fn()=>client_identity_token_digest($case[0],$case[1])));
    $id=str_repeat('1',32);$hash=str_repeat('2',64);$destination=client_identity_destination(['hosting'=>'checkout','offer'=>$id,'offer_sha256'=>$hash,'return'=>'https://attacker.invalid','invoice'=>'7']);
    ci_ok('Only exact checkout identity survives normalization',$destination===['hosting'=>'checkout','offer'=>$id,'offer_sha256'=>$hash]);
    $json=client_identity_destination_json($destination);
    ci_ok('Canonical destination round trips exactly',client_identity_destination_from_json($json)===$destination);
    ci_ok('Noncanonical stored destination is refused',ci_refuses(fn()=>client_identity_destination_from_json('{"return":"https://attacker.invalid","hosting":"checkout","offer":"'.$id.'","offer_sha256":"'.$hash.'"}')));
    ci_ok('Serialized or list destination is refused',ci_refuses(fn()=>client_identity_destination_from_json('["https://attacker.invalid"]')));
    ci_ok('Support discards checkout continuation',client_identity_destination(['support'=>'session','hosting'=>'checkout','offer'=>$id,'offer_sha256'=>$hash])===[]);
    $url=client_identity_url('verify',$token,$destination);
    ci_ok('Generated link contains only route and closed checkout descriptor',$url==='https://example.invalid/portal/index.php?verify='.$token.'&hosting=checkout&offer='.$id.'&offer_sha256='.$hash);
    ci_ok('Unknown generated route is refused',ci_refuses(fn()=>client_identity_url('return',$token,$destination)));
    $router=file_get_contents(CWP_CODE_ROOT.'/portal/controllers/router.php');$actions=file_get_contents(CWP_CODE_ROOT.'/portal/controllers/actions.php');$view=file_get_contents(CWP_CODE_ROOT.'/portal/views/auth.php');$service=file_get_contents(CWP_CODE_ROOT.'/app/clients/public_identity.php');
    ci_ok('All public mutations remain behind the common CSRF gate',strpos($actions,'check_csrf()')<strpos($actions,"elseif(\$action==='public_register')"));
    ci_ok('Verification and reset tokens come from the validated query route',str_contains($actions,'client_identity_verify($identityToken,$password)')&&str_contains($actions,'client_identity_recovery_reset($identityToken,$password)')&&!str_contains($actions,"posted('verify'")&&!str_contains($actions,"posted('reset'"));
    ci_ok('Router applies no-store and refuses support-route mixing',str_contains($router,"header('Cache-Control: no-store, private')")&&str_contains($router,"array_key_exists('support',\$_GET)"));
    ci_ok('GET pages preview without consuming security tokens',str_contains($view,"client_identity_token_preview('verify',\$identityToken)")&&str_contains($view,"client_identity_token_preview('recover',\$identityToken)"));
    ci_ok('Mail follows committed token issuance with exact-predecessor accepted uncertain and unsent finalization',strpos($service,'if($created)client_identity_deliver')>strpos($service,'staff_locked(fn()=>staff_transaction')&&str_contains($service,"if(\$outcome==='accepted'&&\$retireDigest!==null)")&&str_contains($service,"elseif(\$outcome==='not_configured')")&&str_contains($service,"\$outcome='uncertain'"));
    ci_ok('Token issuance retains at most two overlapping links without pre-send eviction',str_contains($service,'if(count($active)>=2)return null')&&!str_contains($service,"DELETE FROM '.table('studio_client_tokens').' WHERE client_id=? AND purpose=?',"));
    $registrationAction=substr($actions,strpos($actions,"elseif(\$action==='public_register')"),strpos($actions,"elseif(\$action==='public_verify')")-strpos($actions,"elseif(\$action==='public_register')"));
    ci_ok('Registration stores no attacker-selected password before mailbox proof',str_contains($service,"VALUES (?,?,\\'\\',?,1,NULL,NULL,?)")&&!str_contains($registrationAction,"posted('password'"));
    ci_ok('Password hashing is purpose-token throttled before verification and recovery',substr_count($service,"client_identity_token_throttle(")>=3&&strpos($service,"client_identity_token_throttle('verify',\$token)")<strpos($service,"password_hash(\$password,PASSWORD_DEFAULT)",strpos($service,'function client_identity_verify'))&&strpos($service,"client_identity_token_throttle('recover',\$token)")<strpos($service,"password_hash(\$password,PASSWORD_DEFAULT)",strpos($service,'function client_identity_recovery_reset')));
    ci_ok('Neutral public identity requests share a floor longer than the delivery budget',substr_count($service,'client_identity_neutral_finish($started)')===3&&str_contains($service,'7000000000-(hrtime(true)-$started)')&&str_contains($service,',null,6.0)'));
    ci_ok('Registration source has no commercial or provider writes',!preg_match('/INSERT INTO .*?(invoice|payment|subscription|entitlement|provider|service_orders)/i',$service));
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>$failure===null,'scope'=>'Pure policy and shipping-source ordering only; no SQL, mail, HTTP, browser, or concurrency execution.','checks'=>$checks,'failure'=>$failure],JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failure===null?0:1);
