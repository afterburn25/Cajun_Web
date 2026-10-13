<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if(PHP_SAPI!=='cli')exit(2);
/** Test namespace only: assert the production destination/context before routing to loopback. */
function stream_socket_client(string $remote,?int &$errno=null,?string &$errstr=null,?float $timeout=null,int $flags=STREAM_CLIENT_CONNECT,$context=null) {
    $input=$GLOBALS['receiving_fixture']; $ssl=stream_context_get_options($context)['ssl'];
    $GLOBALS['pinned_verified']=$remote==='tcp://8.8.8.8:'.($input['encryption']==='ssl'?993:143) && $timeout===5.0
        && $flags===STREAM_CLIENT_CONNECT && $ssl['verify_peer']===true && $ssl['verify_peer_name']===true
        && $ssl['allow_self_signed']===false && $ssl['peer_name']===$input['host'] && $ssl['SNI_enabled']===true
        && !isset($ssl['cafile']) && !isset($ssl['proxy']) && $ssl['disable_compression']===true
        && $ssl['crypto_method']===(STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
    if(!$GLOBALS['pinned_verified'])throw new \RuntimeException('Fixture found invalid connection policy');
    stream_context_set_option($context,'ssl','cafile',$input['ca']);
    return \stream_socket_client('tcp://127.0.0.1:'.$input['port'],$errno,$errstr,$timeout,$flags,$context);
}
