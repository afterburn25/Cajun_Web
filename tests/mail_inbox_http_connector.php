<?php
/** Test server auto-prepend only; no production file or HTTP option loads this connector. */
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport {
    function dns_get_record(string $host,int $type):array|false {
        if(PHP_SAPI!=='cli-server'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||$host!=='inbox-fixture.example.net'||$type!==(DNS_A|DNS_AAAA|DNS_CNAME))throw new \RuntimeException('Isolated fixture forbids external mail DNS');
        return [['type'=>'A','ip'=>'8.8.8.8']];
    }
}
namespace CajunWebPro\Foundation\MailReceiving {
    function stream_socket_client(string $remote,?int &$errno=null,?string &$errstr=null,?float $timeout=null,int $flags=STREAM_CLIENT_CONNECT,$context=null) {
        if(PHP_SAPI!=='cli-server'||getenv('CWP_TEST_DATABASE')!=='cwp_test')throw new \RuntimeException('Not an isolated HTTP fixture');
        $ssl=\stream_context_get_options($context)['ssl'];
        if($remote!=='tcp://8.8.8.8:993'||$timeout!==5.0||$flags!==STREAM_CLIENT_CONNECT||$ssl['verify_peer']!==true||$ssl['verify_peer_name']!==true||$ssl['allow_self_signed']!==false||$ssl['peer_name']!=='inbox-fixture.example.net'||$ssl['SNI_enabled']!==true||isset($ssl['cafile'])||$ssl['crypto_method']!==(STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT))throw new \RuntimeException('Invalid production TLS policy');
        $fixture=\json_decode(\file_get_contents(__DIR__.'/../test-runtime/mail-inbox-http/config.json'),true,flags:JSON_THROW_ON_ERROR);
        \stream_context_set_option($context,'ssl','cafile',$fixture['ca']);
        return \stream_socket_client('tcp://127.0.0.1:'.$fixture['port'],$errno,$errstr,$timeout,$flags,$context);
    }
}
