<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/PreparedMime.php';
use CajunWebPro\Foundation\MailCompose\DraftContent;
use CajunWebPro\Foundation\MailSubmission\{SubmissionSnapshot,PreparedMime,PreparationFailure};
if(function_exists('ctype_alnum'))throw new RuntimeException('This fixture requires ctype_alnum to be disabled.');
$snap=new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,'sender@example.invalid',new DraftContent(['recipient@example.invalid'],[],[],'Fixture','Synthetic body'),1791964800);
$passed=false;
try{PreparedMime::fromSnapshot($snap);}catch(PreparationFailure $e){$passed=$e->reason==='unsupported_runtime'&&$e->getPrevious()===null&&!str_contains($e->getMessage(),'example.invalid');}
$checks=[['name'=>'Missing upstream-required function gives finite private failure before MIME loading','passed'=>$passed&&!class_exists('PHPMailer\\PHPMailer\\PHPMailer',false)]];
if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$checks[0]['passed'],'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
if(!$checks[0]['passed'])throw new RuntimeException('Unsupported runtime was not refused before library loading.');
echo "PASS:1 explicit unavailable-runtime check\n";
