<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

require __DIR__.'/../../foundation/mail-submission/PreparedMime.php';

use CajunWebPro\Foundation\MailCompose\{DraftAttachment,DraftContent,MessageThread};
use CajunWebPro\Foundation\MailSubmission\{PreparedMime,SubmissionSnapshot};

$checks=[];
$messages=[];
$complete=false;

function check(string $name,bool $passed):void
{
    global $checks;
    $checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed)throw new RuntimeException($name);
}

function refused(callable $run):bool
{
    try{$run();return false;}catch(InvalidArgumentException|TypeError){return true;}
}

/** The format value is a closed bitset: thread=1, attachments=2, formatted=4. */
function attachment_snapshot(int $format,?array $attachments=null):SubmissionSnapshot
{
    if($format<1||$format>8)throw new InvalidArgumentException('Unsupported fixture format.');
    $bits=$format-1;
    $thread=($bits&1)!==0?MessageThread::reply('<parent@example.invalid>',['<root@example.invalid>']):null;
    $files=($bits&2)!==0?($attachments??[
        new DraftAttachment('notes.txt','text/plain',"PRIVATE-NOTE\0bytes\r\n"),
        new DraftAttachment('photo.png','image/png',"\x89PNG\r\n\x1A\n".str_repeat("\xDE\xAD",500)),
    ]):[];
    $html=($bits&4)!==0?'<p>Formatted <strong>body</strong></p>':null;
    return new SubmissionSnapshot(
        str_pad(dechex($format),32,'0',STR_PAD_LEFT),
        7,
        str_repeat('d',31).dechex($format),
        3,
        str_repeat('a',32),
        4,
        "Studio+o'neil@example.invalid",
        new DraftContent(
            ['Client+work@example.invalid'],
            ['copy@example.invalid'],
            ['private-envelope@example.invalid'],
            'Attachment shape '.$format,
            "Saved body\nSecond line",
            $html,
        ),
        1791964800,
        $thread,
        $thread===null?null:str_repeat('f',64),
        $files,
    );
}

try {
    $privateBytes="%PDF-1.4\nPRIVATE-CONTENT\0".str_repeat("\x00\xffA",100);
    $file=new DraftAttachment('Quarterly report.pdf','application/pdf',$privateBytes);
    check('Attachment name, type and checksum bind exact private bytes',
        $file->name==='Quarterly report.pdf'&&$file->mime==='application/pdf'
        &&$file->bytes()===$privateBytes&&$file->sha256===hash('sha256',$privateBytes));
    check('Descriptor is closed ordered metadata with no content',
        $file->descriptor()===['name'=>'Quarterly report.pdf','mime'=>'application/pdf','sha256'=>$file->sha256,'bytes'=>strlen($privateBytes)]);

    $objectJson=json_encode($file,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    check('Attachment object JSON exposes metadata but no raw bytes',
        str_contains($objectJson,'Quarterly report.pdf')&&!str_contains($objectJson,'PRIVATE-CONTENT')&&!str_contains($objectJson,'PDF-1.4'));
    $serializeError=null;
    try{serialize($file);}catch(LogicException $error){$serializeError=$error;}
    check('Attachment native serialization fails closed without private bytes',
        $serializeError instanceof LogicException&&!str_contains($serializeError->getMessage(),'PRIVATE-CONTENT'));
    $class=DraftAttachment::class;
    $crafted='O:'.strlen($class).':"'.$class.'":0:{}';
    $unserializeError=null;
    try{unserialize($crafted,['allowed_classes'=>[$class]]);}catch(LogicException $error){$unserializeError=$error;}
    check('Crafted attachment unserialize cannot bypass constructor invariants',$unserializeError instanceof LogicException);

    foreach([
        '../evil.txt','..\\evil.txt',' evil.txt','evil.txt ','a/b.txt','a:b.txt','a"b.txt','a?b.txt','a*b.txt',
        'a<b.txt','a>b.txt','a|b.txt','.','..','',"bad\r\nname.txt","caf\xC3\xA9.txt",str_repeat('n',181),
    ] as $index=>$bad){
        check('Noncanonical attachment name is refused '.$index,refused(fn()=>new DraftAttachment($bad,'text/plain','x')));
    }
    check('Exact 180-byte printable name is accepted',strlen((new DraftAttachment(str_repeat('n',180),'text/plain','x'))->name)===180);
    check('Canonical internal spaces stay exact',(new DraftAttachment('Project notes.txt','text/plain','x'))->name==='Project notes.txt');

    foreach(['text/plain','application/pdf','image/png','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] as $index=>$mime){
        check('Canonical MIME token is accepted '.$index,(new DraftAttachment('a.bin',$mime,'x'))->mime===$mime);
    }
    foreach(['','not a mime','text','text/','/plain','./.','text/.plain','text/plain; charset=x',"text/plain\n",'TEXT/PLAIN',str_repeat('t',101).'/x'] as $index=>$bad){
        check('Noncanonical MIME token is refused '.$index,refused(fn()=>new DraftAttachment('a.bin',$bad,'x')));
    }

    check('Empty attachment bytes are refused',refused(fn()=>new DraftAttachment('empty.bin','application/octet-stream','')));
    $maximum=new DraftAttachment('maximum.bin','application/octet-stream',str_repeat('a',DraftAttachment::MAX_BYTES));
    check('Exact per-file maximum is accepted',strlen($maximum->bytes())===DraftAttachment::MAX_BYTES);
    check('One byte above the per-file maximum is refused',
        refused(fn()=>new DraftAttachment('large.bin','application/octet-stream',str_repeat('a',DraftAttachment::MAX_BYTES+1))));
    $totalA=new DraftAttachment('total-a.bin','application/octet-stream',str_repeat('a',DraftAttachment::MAX_BYTES));
    $totalB=new DraftAttachment('total-b.bin','application/octet-stream',str_repeat('b',DraftAttachment::MAX_BYTES));
    check('Exact combined maximum is accepted',DraftAttachment::list([$totalA,$totalB])===[$totalA,$totalB]);
    check('One byte above the combined maximum is refused',
        refused(fn()=>DraftAttachment::list([$totalA,$totalB,new DraftAttachment('overflow.bin','application/octet-stream','x')])));
    check('Four files are accepted',count(DraftAttachment::list([
        new DraftAttachment('1.txt','text/plain','1'),new DraftAttachment('2.txt','text/plain','2'),
        new DraftAttachment('3.txt','text/plain','3'),new DraftAttachment('4.txt','text/plain','4'),
    ]))===DraftAttachment::MAX_FILES);
    check('Five files are refused',refused(fn()=>DraftAttachment::list([
        new DraftAttachment('1.txt','text/plain','1'),new DraftAttachment('2.txt','text/plain','2'),
        new DraftAttachment('3.txt','text/plain','3'),new DraftAttachment('4.txt','text/plain','4'),
        new DraftAttachment('5.txt','text/plain','5'),
    ])));
    check('Associative attachment collections are refused',
        refused(fn()=>DraftAttachment::list(['first'=>new DraftAttachment('a.txt','text/plain','a')])));
    check('Non-attachment values are refused',refused(fn()=>DraftAttachment::list([['name'=>'a.txt']])));
    check('ASCII-case-folded duplicate names are refused',refused(fn()=>DraftAttachment::list([
        new DraftAttachment('Report.txt','text/plain','a'),new DraftAttachment('report.txt','text/plain','b'),
    ])));

    $ordered=DraftAttachment::descriptors([
        new DraftAttachment('one.txt','text/plain',"\x00PRIVATE-ONE\xff"),
        new DraftAttachment('two.bin','application/octet-stream',"\x00PRIVATE-TWO\x01"),
    ]);
    check('Descriptor list preserves file order and canonical key order',
        array_is_list($ordered)&&array_column($ordered,'name')===['one.txt','two.bin']
        &&array_keys($ordered[0])===['name','mime','sha256','bytes']);
    check('Descriptor JSON contains no raw private bytes',
        !str_contains(json_encode($ordered,JSON_THROW_ON_ERROR),'PRIVATE-ONE')
        &&!str_contains(json_encode($ordered,JSON_THROW_ON_ERROR),'PRIVATE-TWO'));

    $baseKeys=['format','id','author_id','draft_id','draft_revision','account_id','account_revision','from','message_id','prepared_epoch','to','cc','bcc','subject','body'];
    $expectedMimeSha256=[
        1=>'8469c3fa42df859d95d6dbc759eafb81ebb0756f9efc532a582d13aecf1008ad',
        2=>'e284b9ab2f70236e4cc047cd0cd26bbafb9c2490f19ddab4ac230bf0e06426c9',
        3=>'2c151dc56585d9a3da284f1f93c0cc5e963c55107afb0deb902117e48bc3a11d',
        4=>'e2073f609853a7552ffbef1ea33cb271f148fd62c780478b85f14e7cd108e3f4',
        5=>'23070c999e0103838f71f0a8a9e416ea901642a1713c5ab748369e5a7e9bffe5',
        6=>'9bb08c4cb0087ffcafba9301e0052f4808a01258289346b02dca245f975667c6',
        7=>'16f1bb7acb5e30fa1dd7100528675bb8ea2af290a80e84d8f83b40cc27656a0b',
        8=>'502894db2788711da83f1979e0c984d38f5db8e3cf46c77ad5ee8b6a5d11b157',
    ];
    $formatBytes=[];
    for($format=1;$format<=8;$format++){
        $bits=$format-1;
        $snapshot=attachment_snapshot($format);
        $payload=$snapshot->privatePayload();
        $expected=$baseKeys;
        if(($bits&4)!==0)$expected[]='body_html';
        if(($bits&1)!==0){$expected[]='thread';$expected[]='origin_fingerprint';}
        if(($bits&2)!==0)$expected[]='attachments';
        check('Format '.$format.' has the exact value and canonical payload order',
            $payload['format']===$format&&array_keys($payload)===$expected);
        $privateJson=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        check('Format '.$format.' private payload carries descriptors but no attachment bytes',
            !str_contains($privateJson,'PRIVATE-NOTE')&&!str_contains($privateJson,'PNG'));

        $first=PreparedMime::fromSnapshot($snapshot);
        $second=PreparedMime::fromSnapshot($snapshot);
        check('Format '.$format.' MIME is exact deterministic and bounded',
            $first->bytes===$second->bytes&&$first->sha256===$second->sha256
            &&$first->sha256===hash('sha256',$first->bytes)
            &&strlen($first->bytes)<=PreparedMime::MAX_BYTES&&str_ends_with($first->bytes,"\r\n"));
        check('Format '.$format.' MIME retains its exact golden checksum',$first->sha256===$expectedMimeSha256[$format]);
        check('Format '.$format.' MIME stays canonical ASCII CRLF with SMTP-safe lines',
            !preg_match('/(?<!\r)\n|\r(?!\n)|[^\x09\x0A\x0D\x20-\x7E]/',$first->bytes)
            &&max(array_map('strlen',explode("\r\n",$first->bytes)))<=998);
        $headers=explode("\r\n\r\n",$first->bytes,2)[0];
        check('Format '.$format.' omits hidden-recipient and extra authority headers',
            !preg_match('/^(?:Bcc|Resent-Bcc|Reply-To|Return-Path|Disposition-Notification-To):/mi',$headers));
        if(($bits&2)!==0){
            check('Format '.$format.' emits ordered multipart attachments',
                str_contains($headers,'multipart/mixed')&&substr_count($first->bytes,'Content-Disposition: attachment')===2);
        }
        if(($bits&4)!==0){
            check('Format '.$format.' emits the formatted alternative',
                str_contains($first->bytes,'multipart/alternative')
                &&str_contains($first->bytes,'Content-Type: text/plain')
                &&str_contains($first->bytes,'Content-Type: text/html'));
        }
        if(($bits&1)!==0){
            check('Format '.$format.' retains literal structured thread headers',
                str_contains($headers,'In-Reply-To: <parent@example.invalid>')
                &&str_contains($headers,'References: <root@example.invalid> <parent@example.invalid>')
                &&!preg_match('/(?:In-Reply-To|References):[^\r\n]*=\?/',$headers));
        }
        $formatBytes[$format]=$first->bytes;
        $messages[]=[
            'name'=>'format-'.$format,
            'snapshot'=>$payload,
            'mime_sha256'=>$first->sha256,
            'mime_base64'=>base64_encode($first->bytes),
        ];
    }
    check('Plain attachment MIME nests body and files directly in multipart mixed',
        str_contains($formatBytes[3],'multipart/mixed')&&!str_contains($formatBytes[3],'multipart/alternative'));
    check('Formatted attachment MIME nests multipart alternative inside multipart mixed',
        str_contains($formatBytes[7],'multipart/mixed')&&str_contains($formatBytes[7],'multipart/alternative'));

    $formatThree=attachment_snapshot(3);
    $changedBytes=attachment_snapshot(3,[
        new DraftAttachment('notes.txt','text/plain',"PRIVATE-NOTE\0bytes\r\n!"),
        new DraftAttachment('photo.png','image/png',"\x89PNG\r\n\x1A\n".str_repeat("\xDE\xAD",500)),
    ]);
    check('Attachment digest changes the private snapshot fingerprint',
        $formatThree->fingerprint!==$changedBytes->fingerprint);
    check('Attachment order changes the private snapshot fingerprint',
        attachment_snapshot(3,array_reverse($formatThree->attachments))->fingerprint!==$formatThree->fingerprint);
    $snapshotJsonError=null;
    try{json_encode($formatThree,JSON_THROW_ON_ERROR);}catch(LogicException $error){$snapshotJsonError=$error;}
    check('Direct snapshot JSON fails closed without private attachment bytes',$snapshotJsonError instanceof LogicException);
    $snapshotSerializeError=null;
    try{serialize($formatThree);}catch(LogicException $error){$snapshotSerializeError=$error;}
    check('Snapshot native serialization fails closed without private attachment bytes',$snapshotSerializeError instanceof LogicException);

    $big=[];
    for($index=0;$index<DraftAttachment::MAX_FILES;$index++){
        $big[]=new DraftAttachment('big-'.$index.'.bin','application/octet-stream',str_repeat(chr(65+$index),131072));
    }
    $maximumSnapshot=attachment_snapshot(8,$big);
    $maximumMime=PreparedMime::fromSnapshot($maximumSnapshot);
    check('Four 128 KiB files exercise the exact total attachment bound',
        array_sum(array_column(DraftAttachment::descriptors($big),'bytes'))===DraftAttachment::MAX_TOTAL_BYTES);
    check('Maximum attachment set remains inside the exact one-MiB message bound',
        strlen($maximumMime->bytes)<=PreparedMime::MAX_BYTES&&PreparedMime::MAX_BYTES===1048576);
    check('Maximum attachment MIME is deterministic',
        PreparedMime::fromSnapshot($maximumSnapshot)->bytes===$maximumMime->bytes
        &&hash('sha256',$maximumMime->bytes)===$maximumMime->sha256);
    $messages[]=[
        'name'=>'maximum-format-8',
        'snapshot'=>$maximumSnapshot->privatePayload(),
        'mime_sha256'=>$maximumMime->sha256,
        'mime_base64'=>base64_encode($maximumMime->bytes),
    ];

    $complete=true;
} finally {
    $result=[
        'passed'=>$complete&&!in_array(false,array_column($checks,'passed'),true),
        'scope'=>'Offline canonical attachment values, snapshot formats 1-8 and deterministic maintained MIME only; synthetic fixture bytes, no filesystem, database, application storage, credentials, socket, SMTP, provider or live operation',
        'checks'=>$checks,
        'messages'=>$messages,
    ];
    if(isset($argv[1]))file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}

echo 'PASS: '.count($checks).' portable attachment checks; '.count($messages)." independent parser fixtures\n";
