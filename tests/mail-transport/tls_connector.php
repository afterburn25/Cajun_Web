<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport;
if (PHP_SAPI!=='cli') exit(2);
/** Test-only namespace interception: preserve production options, route socket to our loopback fixture. */
function stream_socket_client(string $remote,?int &$errno=null,?string &$errstr=null,?float $timeout=null,int $flags=STREAM_CLIENT_CONNECT,$context=null) {
    $input=$GLOBALS['tls_fixture'];$options=stream_context_get_options($context)['ssl'];
    $port=['imap'=>['ssl'=>993,'starttls'=>143],'smtp'=>['ssl'=>465,'starttls'=>587]][$input['protocol']][$input['encryption']];
    $GLOBALS['pinned_verified']=$remote==='tcp://8.8.8.8:'.$port && $timeout===5.0 && $flags===STREAM_CLIENT_CONNECT
        && $options['peer_name']===$input['host'] && $options['verify_peer']===true && $options['verify_peer_name']===true
        && $options['allow_self_signed']===false && $options['SNI_enabled']===true && !isset($options['cafile'])
        && $options['crypto_method']===(STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
    if (!$GLOBALS['pinned_verified']) throw new \RuntimeException('Fixture observed invalid connection policy.');
    stream_context_set_option($context,'ssl','cafile',$input['ca']);
    return \stream_socket_client('tcp://127.0.0.1:'.$input['port'],$errno,$errstr,$timeout,$flags,$context);
}
