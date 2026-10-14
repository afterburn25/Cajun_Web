<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI;
if (PHP_SAPI !== 'cli' && !(PHP_SAPI==='cli-server' && getenv('CWP_TEST_DATABASE')==='cwp_test')) exit(2);

/** Only this test file remaps the fixed production destination to the synthetic loopback server. */
function curl_init(?string $url=null): \CurlHandle|false {
    if($url!==TwentyIAdapter::URL)throw new \RuntimeException('Unexpected fixture URL.');
    if(PHP_SAPI==='cli-server')$GLOBALS['twentyi_fixture']=\json_decode(\file_get_contents(__DIR__.'/../../test-runtime/provider-http/config.json'),true,flags:JSON_THROW_ON_ERROR);
    $port=$GLOBALS['twentyi_fixture']['port'];
    return \curl_init('https://api.20i.com:'.$port.'/package');
}
function curl_setopt_array(\CurlHandle $curl,array $options): bool {
    $input=$GLOBALS['twentyi_fixture'];$address=$input['address']??'8.8.8.8';
    $pin=str_contains($address,':')?'['.$address.']':$address;
    $expected=[CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.base64_encode('SYNTHETIC_RAW_GENERAL_KEY'),'Accept: application/json'],
        CURLOPT_RETURNTRANSFER=>false,CURLOPT_HEADER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_PRE_PROXY=>'',CURLOPT_NOPROXY=>'*',
        CURLOPT_NETRC=>CURL_NETRC_IGNORED,CURLOPT_HTTPAUTH=>CURLAUTH_NONE,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_SSLVERSION=>CURL_SSLVERSION_TLSv1_2,CURLOPT_RESOLVE=>['api.20i.com:443:'.$pin],CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTP09_ALLOWED=>false,CURLOPT_FRESH_CONNECT=>true,CURLOPT_FORBID_REUSE=>true,CURLOPT_NOSIGNAL=>true,
        CURLOPT_CONNECTTIMEOUT_MS=>5000,CURLOPT_TIMEOUT_MS=>(int)ceil(($input['budget']??15)*1000),CURLOPT_LOW_SPEED_LIMIT=>1,CURLOPT_LOW_SPEED_TIME=>5,
        CURLOPT_BUFFERSIZE=>16384];
    foreach($expected as $key=>$value)if(($options[$key]??null)!==$value)throw new \RuntimeException('Unexpected fixture option.');
    if(count($options)!==count($expected)+2||!is_callable($options[CURLOPT_HEADERFUNCTION]??null)||!is_callable($options[CURLOPT_WRITEFUNCTION]??null))throw new \RuntimeException('Unexpected fixture options or callbacks.');
    $GLOBALS['twentyi_options_verified']=true;
    // Preserve verification, SNI, request, parsing, limits, proxy refusal and all other options.
    $options[CURLOPT_RESOLVE]=['api.20i.com:'.$input['port'].':127.0.0.1'];
    $options[CURLOPT_CAINFO]=$input['ca'];
    return \curl_setopt_array($curl,$options);
}
