<?php
/** Native ZIP inspection, no database, network, extraction or executable payload invocation. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/app/delivery-archive.php';require __DIR__.'/delivery_fixture.php';
$checks=[];$tmp=sys_get_temp_dir().'/cwp-delivery-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
function da_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function da_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException){da_ok($name,true);return;}da_ok($name,false);}
try{
    $p=d_zip($tmp.'/fixture.zip');$scan=delivery_inspect($p,'static');da_ok('Valid static manifest matches whole ZIP and each file',count($scan['files'])===3&&$scan['sha256']===hash_file('sha256',$p)&&$scan['files'][0]['sha256']===hash('sha256','Open index.html in a browser. This synthetic site needs no database.'));
    foreach(['../escape','/absolute','C:/drive','a\\back','a//b','a/../b','a./b','CON.txt','NUL','LPT9.php','same.','white ','dir//','.env','.env.production','.git/config','config.php','wp-config.php','id_rsa','db.sql','db.sqlite','backup.zip','logs.log','secret.pem'] as $name){d_zip($p,[$name=>'Unsafe contents']);da_deny('Reject path '.$name,fn()=>delivery_inspect($p,'files'));}
    foreach([['a'=>'x','A'=>'y'],['dir'=>'x','dir/a'=>'y'],['dir/'=>'','DIR'=>'x']] as $i=>$entries){d_zip($p,$entries);da_deny('Reject colliding or overlapping paths '.$i,fn()=>delivery_inspect($p,'files'));}
    d_zip($p,['link'=>'../../secret']);$z=new ZipArchive();$z->open($p);$z->setExternalAttributesName('link',ZipArchive::OPSYS_UNIX,0120777<<16);$z->close();da_deny('Reject Unix symbolic link',fn()=>delivery_inspect($p,'files'));
    d_zip($p,['fifo'=>'']);$z->open($p);$z->setExternalAttributesName('fifo',ZipArchive::OPSYS_UNIX,0010644<<16);$z->close();da_deny('Reject special file',fn()=>delivery_inspect($p,'files'));
    d_zip($p);$z->open($p);$z->setEncryptionName('index.html',ZipArchive::EM_AES_256,'Synthetic test only');$z->close();da_deny('Reject encrypted archive',fn()=>delivery_inspect($p,'files'));
    foreach(['<?php $password="SyntheticLeakedValue";','api_key: "SyntheticLeakedValue"','-----BEGIN RSA PRIVATE KEY-----','AKIA1234567890ABCDEF','ghp_'.str_repeat('a',36),str_repeat(' ',65530).'token="BoundaryLeakValue"'] as $i=>$contents){d_zip($p,['data.txt'=>$contents]);da_deny('Reject recognizable embedded credential '.$i,fn()=>delivery_inspect($p,'files'));}
    d_zip($p,['app.php'=>'<?php file_put_contents("should-never-exist", "executed");']);da_deny('Static scope rejects server programs',fn()=>delivery_inspect($p,'static'));da_ok('Files-only scans code without executing',count(delivery_inspect($p,'files')['files'])===4&&!is_file('should-never-exist'));
    d_zip($p,['example.txt'=>'api_key="YOUR_API_KEY"']);da_ok('Clearly marked credential placeholder accepted',count(delivery_inspect($p,'files')['files'])===4);
    d_zip($p,['index.html'=>'Only a page'],false);da_deny('Requires handover and licensing documents',fn()=>delivery_inspect($p,'static'));
    d_zip($p,['index.html'=>'a','HANDOVER.md'=>'','LICENSES.md'=>''],false);da_deny('Empty handover documents rejected',fn()=>delivery_inspect($p,'static'));
    d_zip($p);file_put_contents($p,str_replace('Delivered website','Corrupted website',file_get_contents($p)));da_deny('Stored content CRC mismatch rejected',fn()=>delivery_inspect($p,'static'));
    d_zip($p);file_put_contents($p,'<?php exit; ?>'.file_get_contents($p));da_deny('Executable prefix rejected',fn()=>delivery_inspect($p,'static'));
    file_put_contents($p,'not a ZIP'.str_repeat('x',50));da_deny('Invalid signature rejected',fn()=>delivery_inspect($p,'static'));
    d_zip($p);da_deny('Invalid scope rejected',fn()=>delivery_inspect($p,'unknown'));
    $extra=[];for($i=0;$i<998;$i++)$extra['f'.$i]='x';d_zip($p,$extra);da_deny('More than 1000 entries rejected',fn()=>delivery_inspect($p,'files'));
    d_zip($p,['large.txt'=>str_repeat('x',33*1024*1024)]);da_deny('File larger than 32 MiB rejected',fn()=>delivery_inspect($p,'files'));
    d_zip($p,['ratio.txt'=>str_repeat('x',11*1024*1024)]);$z->open($p);$z->setCompressionName('ratio.txt',ZipArchive::CM_DEFLATE);$z->close();da_deny('Extreme compression expansion rejected',fn()=>delivery_inspect($p,'files'));
    $fh=fopen($p,'wb');ftruncate($fh,50*1024*1024+1);fclose($fh);clearstatcache();da_deny('Compressed limit enforced before parsing',fn()=>delivery_inspect($p,'files'));
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
foreach(glob($tmp.'/*') as $f)unlink($f);rmdir($tmp);
$qa=__DIR__.'/../qa';if(!is_dir($qa))mkdir($qa);file_put_contents($qa.'/delivery-archive-results.json',json_encode(['scope'=>'Native PHP ZIP adversarial synthetic archives; no extraction or execution','checks'=>$checks,'passed'=>!isset($failure),'failure'=>$failure??null],JSON_PRETTY_PRINT));
foreach($checks as $c)echo ($c['passed']?'PASS ':'FAIL ').$c['name'].PHP_EOL;
if(isset($failure)){fwrite(STDERR,$failure.PHP_EOL);exit(1);}echo 'Archive checks: '.count($checks).' passed'.PHP_EOL;
