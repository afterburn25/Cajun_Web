<?php
/** Test-only namespace interception. No production route/configuration loads this file. */
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
function stream_socket_client(string $remote,?int &$errno=null,?string &$errstr=null,?float $timeout=null,int $flags=STREAM_CLIENT_CONNECT,$context=null) {
    if(!in_array(PHP_SAPI,['cli','cli-server'],true)||getenv('CWP_TEST_DATABASE')!=='cwp_test')throw new \RuntimeException('Not an isolated submission fixture');
    $ssl=\stream_context_get_options($context)['ssl'];
    if(!in_array($remote,['tcp://8.8.8.8:465','tcp://8.8.8.8:587'],true)||$timeout!==5.0||$flags!==STREAM_CLIENT_CONNECT||$ssl['verify_peer']!==true||$ssl['verify_peer_name']!==true||$ssl['allow_self_signed']!==false||$ssl['peer_name']!=='submission-fixture.example.net'||$ssl['SNI_enabled']!==true||$ssl['disable_compression']!==true||isset($ssl['cafile'])||isset($ssl['proxy'])||$ssl['crypto_method']!==(STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT))throw new \RuntimeException('Invalid production SMTP TLS policy');
    if(isset($GLOBALS['submission_fixture_before_open']))($GLOBALS['submission_fixture_before_open'])();
    $fixture=\json_decode(\file_get_contents(__DIR__.'/../test-runtime/mail-submission/config.json'),true,flags:JSON_THROW_ON_ERROR);
    \stream_context_set_option($context,'ssl','cafile',$fixture['ca']);
    $stream=\stream_socket_client('tcp://127.0.0.1:'.$fixture['port'],$errno,$errstr,$timeout,$flags,$context);
    if(isset($GLOBALS['submission_fixture_after_open']))($GLOBALS['submission_fixture_after_open'])();
    return $stream;
}
