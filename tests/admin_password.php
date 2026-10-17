<?php
/** Password boundaries only; no configuration, database or customer data. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
if(!defined('CWP_BOOT'))define('CWP_BOOT',true);
require_once __DIR__.'/../site/includes/security/input.php';

function admin_password_checks(): array {
    $checks=[];
    $accepts=static function(callable $validator,string $password): bool {
        try{$validator($password);return true;}catch(InvalidArgumentException){return false;}
    };
    $cases=[
        'Administrator rejects empty password'=>['',false],
        'Administrator rejects eleven ASCII characters'=>[str_repeat('a',11),false],
        'Administrator accepts twelve ASCII characters'=>['AdminPass12!',true],
        'Administrator rejects eleven multibyte characters'=>[str_repeat('é',11),false],
        'Administrator accepts twelve multibyte characters'=>[str_repeat('é',12),true],
        'Administrator rejects eleven supplementary characters'=>[str_repeat('🔑',11),false],
        'Administrator accepts twelve supplementary characters'=>[str_repeat('🔑',12),true],
        'Administrator accepts exactly seventy-two ASCII bytes'=>[str_repeat('a',72),true],
        'Administrator rejects seventy-three ASCII bytes'=>[str_repeat('a',73),false],
        'Administrator accepts exactly seventy-two UTF-8 bytes'=>[str_repeat('é',36),true],
        'Administrator rejects UTF-8 beyond seventy-two bytes'=>[str_repeat('é',37),false],
        'Administrator rejects embedded NUL'=>['AdminPass12!' . "\0",false],
        'Administrator rejects malformed UTF-8'=>['AdminPass12!' . "\xC3\x28",false],
        'Administrator retains intentional surrounding spaces'=>[' AdminPass! ',true],
    ];
    foreach($cases as $name=>[$password,$expected])$checks[]=['name'=>$name,'passed'=>$accepts('validate_admin_password',$password)===$expected];
    $checks[]=['name'=>'Client still rejects twelve-byte password','passed'=>!$accepts('validate_password','AdminPass12!')];
    $checks[]=['name'=>'Client still rejects thirteen-byte password','passed'=>!$accepts('validate_password',str_repeat('a',13))];
    $checks[]=['name'=>'Client still accepts fourteen-byte password','passed'=>$accepts('validate_password',str_repeat('a',14))];
    return $checks;
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $checks=admin_password_checks();$passed=!in_array(false,array_column($checks,'passed'),true);
    echo json_encode(['passed'=>$passed,'scope'=>'Administrator and unchanged client password validation; no database or install','php'=>PHP_VERSION,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
    exit($passed?0:1);
}
