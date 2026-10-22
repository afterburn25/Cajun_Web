<?php
/** Test server auto-prepend only; no production file or HTTP option loads this connector. */
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport {
    function dns_get_record(string $host,int $type):array|false {
        if(PHP_SAPI!=='cli-server'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!in_array($host,['inbox-fixture.example.net','submission-fixture.example.net','api.20i.com'],true)||$type!==(DNS_A|DNS_AAAA|DNS_CNAME))throw new \RuntimeException('Isolated fixture forbids external DNS');
        return [['type'=>'A','ip'=>'8.8.8.8']];
    }
}
namespace { require_once __DIR__.'/twentyi/tls_connector.php'; require_once __DIR__.'/mail_submission_socket_connector.php'; }
namespace CajunWebPro\Foundation\MailReceiving {
    function stream_socket_client(string $remote,?int &$errno=null,?string &$errstr=null,?float $timeout=null,int $flags=STREAM_CLIENT_CONNECT,$context=null) {
        $trace=function(array $row):void {@\file_put_contents(__DIR__.'/../qa/imap-connect-attempts.jsonl',\json_encode($row+['time'=>\microtime(true)])."\n",FILE_APPEND);};
        $trace(['event'=>'invoke','remote'=>$remote]);
        if(PHP_SAPI!=='cli-server'||getenv('CWP_TEST_DATABASE')!=='cwp_test'){$trace(['event'=>'isolation']);throw new \RuntimeException('Not an isolated HTTP fixture');}
        $ssl=\stream_context_get_options($context)['ssl'];
        if($remote!=='tcp://8.8.8.8:993'||$timeout!==5.0||$flags!==STREAM_CLIENT_CONNECT||$ssl['verify_peer']!==true||$ssl['verify_peer_name']!==true||$ssl['allow_self_signed']!==false||$ssl['peer_name']!=='inbox-fixture.example.net'||$ssl['SNI_enabled']!==true||isset($ssl['cafile'])||$ssl['crypto_method']!==(STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)){$trace(['event'=>'policy','remote'=>$remote,'timeout'=>$timeout,'flags'=>$flags,'ssl'=>$ssl]);throw new \RuntimeException('Invalid production TLS policy');}
        try {$fixture=\json_decode(\file_get_contents(__DIR__.'/../test-runtime/mail-inbox-http/config.json'),true,flags:JSON_THROW_ON_ERROR);}
        catch(\Throwable $read_error) {$trace(['event'=>'config','error'=>$read_error->getMessage()]);throw $read_error;}
        \stream_context_set_option($context,'ssl','cafile',$fixture['ca']);
        $socket=\stream_socket_client('tcp://127.0.0.1:'.$fixture['port'],$errno,$errstr,$timeout,$flags,$context);
        $trace(['event'=>'connect','ok'=>\is_resource($socket),'port'=>$fixture['port'],'errno'=>$errno??null,'errstr'=>$errstr??null]);
        if(!\is_resource($socket))@\file_put_contents(__DIR__.'/../qa/imap-connect-failures.jsonl',\json_encode(['remote'=>'tcp://127.0.0.1:'.$fixture['port'],'errno'=>$errno,'errstr'=>$errstr,'time'=>\microtime(true)])."\n",FILE_APPEND);
        return $socket;
    }
}
