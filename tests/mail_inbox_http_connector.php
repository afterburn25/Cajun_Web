<?php
/** Test server auto-prepend only; no production file or HTTP option loads this connector. */
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport {
    function dns_get_record(string $host,int $type):array|false {
        if(PHP_SAPI!=='cli-server'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!in_array($host,['inbox-fixture.example.net','submission-fixture.example.net','api.20i.com'],true)||$type!==(DNS_A|DNS_AAAA|DNS_CNAME)){\error_log('connector dns refused: '.json_encode(['host'=>$host,'type'=>$type]));throw new \RuntimeException('Isolated fixture forbids external DNS');}
        \error_log('connector dns: '.json_encode(['host'=>$host]));
        return [['type'=>'A','ip'=>'8.8.8.8']];
    }
}
namespace { require_once __DIR__.'/twentyi/tls_connector.php'; require_once __DIR__.'/mail_submission_socket_connector.php'; }
namespace CajunWebPro\Foundation\MailReceiving {
    function stream_socket_client(string $remote,?int &$errno=null,?string &$errstr=null,?float $timeout=null,int $flags=STREAM_CLIENT_CONNECT,$context=null) {
        if(PHP_SAPI!=='cli-server'||getenv('CWP_TEST_DATABASE')!=='cwp_test')throw new \RuntimeException('Not an isolated HTTP fixture');
        $ssl=\stream_context_get_options($context)['ssl'];
        $policy=['remote'=>$remote==='tcp://8.8.8.8:993','timeout'=>$timeout===5.0,'flags'=>$flags===STREAM_CLIENT_CONNECT,'verify_peer'=>($ssl['verify_peer']??null)===true,'verify_peer_name'=>($ssl['verify_peer_name']??null)===true,'allow_self_signed'=>($ssl['allow_self_signed']??null)===false,'peer_name'=>($ssl['peer_name']??null)==='inbox-fixture.example.net','sni'=>($ssl['SNI_enabled']??null)===true,'cafile_absent'=>!isset($ssl['cafile']),'crypto'=>($ssl['crypto_method']??null)===(STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)];
        if(in_array(false,$policy,true)){\error_log('connector refused: '.json_encode(['remote'=>$remote,'timeout'=>$timeout,'peer_name'=>$ssl['peer_name']??null,'failed'=>array_keys($policy,false,true)]));throw new \RuntimeException('Invalid production TLS policy');}
        $fixture=\json_decode(\file_get_contents(__DIR__.'/../test-runtime/mail-inbox-http/config.json'),true,flags:JSON_THROW_ON_ERROR);
        \stream_context_set_option($context,'ssl','cafile',$fixture['ca']);
        return \stream_socket_client('tcp://127.0.0.1:'.$fixture['port'],$errno,$errstr,$timeout,$flags,$context);
    }
}
