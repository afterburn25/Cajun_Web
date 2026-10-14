<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
require __DIR__.'/../../foundation/mail-submission/SubmissionChannel.php';
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
$checks=[];$complete=false;$calls=[];
function stream_socket_client(string $remote,?int &$errno=null,?string &$errstr=null,?float $timeout=null,int $flags=STREAM_CLIENT_CONNECT,$context=null) {
    $GLOBALS['calls'][]=['remote'=>$remote,'timeout'=>$timeout,'flags'=>$flags,'options'=>stream_context_get_options($context)];
    throw new \RuntimeException('Synthetic socket exception with private detail');
}
function check(string $name,bool $pass):void {global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new \RuntimeException($name);}
try {
    foreach(['127.0.0.1','10.1.2.3','169.254.169.254','192.168.0.1','100.64.0.1','::1','fc00::1','::ffff:8.8.8.8','2001:db8::1','8.8.8.8:465'] as $address) {
        try{SubmissionChannel::open('smtp.example.net',465,'ssl',$address);$bad=false;}catch(\InvalidArgumentException){$bad=true;}
        check('Unsafe numeric destination refused before socket '.$address,$bad&&!$calls);
    }
    foreach(['localhost','127.0.0.1','smtp.internal','smtp.example.net.','https://smtp.example.net',"smtp.example.net\n"] as $host) {
        try{SubmissionChannel::open($host,465,'ssl','8.8.8.8');$bad=false;}catch(\InvalidArgumentException){$bad=true;}
        check('Invalid peer hostname refused before socket '.count($checks),$bad&&!$calls);
    }
    foreach([[25,'none'],[465,'starttls'],[587,'ssl'],[2525,'ssl'],[465,'tls']] as [$port,$mode]) {
        try{SubmissionChannel::open('smtp.example.net',$port,$mode,'8.8.8.8');$bad=false;}catch(\InvalidArgumentException){$bad=true;}
        check('Unsupported port/mode refused before socket '.$port.'/'.$mode,$bad&&!$calls);
    }
    foreach([['8.8.8.8',465,'ssl'],['2001:4860:4860::8888',587,'starttls']] as [$address,$port,$mode]) {
        try{SubmissionChannel::open('SMTP.EXAMPLE.NET',$port,$mode,$address);$failure=null;}catch(ProbeFailure $e){$failure=$e;}
        if(!extension_loaded('openssl')) {
            check('Missing OpenSSL refuses before socket '.$mode,$failure?->getMessage()==='tls'&&!$calls);
            continue;
        }
        $call=$calls[count($calls)-1];$ssl=$call['options']['ssl'];
        check('Selected numeric address pinned '.$mode,$call['remote']==='tcp://'.(str_contains($address,':')?'['.$address.']':$address).':'.$port&&$call['timeout']===5.0&&$call['flags']===STREAM_CLIENT_CONNECT);
        check('Original normalized hostname and trust policy retained '.$mode,$ssl['peer_name']==='smtp.example.net'&&$ssl['verify_peer']&&$ssl['verify_peer_name']&&!$ssl['allow_self_signed']&&$ssl['SNI_enabled']&&!isset($ssl['cafile'])&&!isset($ssl['proxy']));
        check('Socket errors sanitized without exception chain '.$mode,$failure?->getMessage()==='connection'&&$failure->getPrevious()===null);
    }
    $complete=true;
}finally {
    if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$complete,'php'=>PHP_VERSION,'openssl'=>extension_loaded('openssl'),'scope'=>'policy interception, no socket opened','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." submission channel policy checks\n";
