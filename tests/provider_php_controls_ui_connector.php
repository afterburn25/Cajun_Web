<?php
/** Test-only auto_prepend_file. No production route, option or config can load it. */
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport {
    function dns_get_record(string $host,int $type):array|false {
        if(PHP_SAPI!=='cli-server'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||$host!=='api.20i.com'||$type!==(DNS_A|DNS_AAAA|DNS_CNAME))throw new \RuntimeException('Isolated PHP fixture forbids external DNS.');
        return [['type'=>'A','ip'=>'8.8.8.8']];
    }
}
namespace CajunWebPro\Foundation\TwentyI {
    /** Fixed private runner config; no caller-selected path, remote host or credentials. */
    function php_ui_fixture_config():array {
        if(PHP_SAPI!=='cli-server'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test')throw new \RuntimeException('Not an isolated PHP UI server.');
        $root=\realpath(__DIR__.'/..');if(!$root)throw new \RuntimeException('Missing fixture checkout.');
        $dir=$root.'/test-runtime/php-controls-ui';
        foreach([$root.'/test-runtime',$dir,$dir.'/tls'] as $part)if(\is_link($part)||!\is_dir($part))throw new \RuntimeException('Invalid fixture parent.');
        $path=$dir.'/transport.json';$ca=$dir.'/tls/ca.pem';
        if(\is_link($path)||!\is_file($path)||\filesize($path)>1024||\is_link($ca)||!\is_file($ca)||\filesize($ca)>16384||\realpath($ca)!==$ca)throw new \RuntimeException('Invalid private fixture config.');
        $input=\json_decode(\file_get_contents($path),true,8,JSON_THROW_ON_ERROR);
        if(!\is_array($input)||\array_keys($input)!==['port','ca']||!\is_int($input['port'])||$input['port']<1024||$input['port']>65535||$input['ca']!==$ca)throw new \RuntimeException('Invalid local TLS fixture binding.');
        return $input;
    }
    /** Only the two assigned fixture packages may reach the loopback TLS server. */
    function curl_init(?string $url=null): \CurlHandle|false {
        if(!\is_string($url)||\preg_match('~\Ahttps://api\.20i\.com/package/(?:701|702)/web/phpVersion\z~',$url)!==1)throw new \RuntimeException('Unexpected PHP fixture URL.');
        $input=php_ui_fixture_config();$GLOBALS['php_ui_tls_fixture']=$input;
        return \curl_init('https://api.20i.com:'.$input['port'].\substr($url,\strlen('https://api.20i.com')));
    }
    function curl_setopt_array(\CurlHandle $curl,array $options): bool {
        $input=$GLOBALS['php_ui_tls_fixture']??null;
        if($input!==php_ui_fixture_config())throw new \RuntimeException('Fixture TLS binding changed.');
        $auth='Authorization: Bearer '.\base64_encode('SYNTHETIC_PHP_UI_KEY');
        if(($options[CURLOPT_POST]??false)===true){
            $expected=[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$options[CURLOPT_POSTFIELDS]??null,
                CURLOPT_HTTPHEADER=>[$auth,'Accept: application/json','Content-Type: application/json']];
            if(!\is_string($options[CURLOPT_POSTFIELDS]??null)||\strlen($options[CURLOPT_POSTFIELDS])>8192)throw new \RuntimeException('Unexpected fixture write body.');
            $body=\json_decode($options[CURLOPT_POSTFIELDS],true,4,JSON_THROW_ON_ERROR);
            if(!\is_array($body)||\array_keys($body)!==['value']||!\is_string($body['value'])||\json_encode($body,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)!==$options[CURLOPT_POSTFIELDS])throw new \RuntimeException('Unexpected canonical PHP setter body.');
        }else{
            $expected=[CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>[$auth,'Accept: application/json']];
        }
        $expected+=[
            CURLOPT_RETURNTRANSFER=>false,CURLOPT_HEADER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_PRE_PROXY=>'',CURLOPT_NOPROXY=>'*',
            CURLOPT_NETRC=>CURL_NETRC_IGNORED,CURLOPT_HTTPAUTH=>CURLAUTH_NONE,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_SSLVERSION=>CURL_SSLVERSION_TLSv1_2,CURLOPT_RESOLVE=>['api.20i.com:443:8.8.8.8'],CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTP09_ALLOWED=>false,CURLOPT_FRESH_CONNECT=>true,CURLOPT_FORBID_REUSE=>true,CURLOPT_NOSIGNAL=>true,
            CURLOPT_CONNECTTIMEOUT_MS=>5000,CURLOPT_TIMEOUT_MS=>15000,CURLOPT_LOW_SPEED_LIMIT=>1,CURLOPT_LOW_SPEED_TIME=>5,
            CURLOPT_BUFFERSIZE=>16384];
        foreach($expected as $key=>$value)if(($options[$key]??null)!==$value)throw new \RuntimeException('Unexpected production TLS option.');
        if(\count($options)!==\count($expected)+2||!\is_callable($options[CURLOPT_HEADERFUNCTION]??null)||!\is_callable($options[CURLOPT_WRITEFUNCTION]??null))throw new \RuntimeException('Unexpected fixture options or callbacks.');
        // Only local destination/CA change. Verification, SNI, body, proxy/redirect
        // refusal, original parser callbacks and all real transport limits stay intact.
        $options[CURLOPT_RESOLVE]=['api.20i.com:'.$input['port'].':127.0.0.1'];
        $options[CURLOPT_CAINFO]=$input['ca'];
        return \curl_setopt_array($curl,$options);
    }
}
