<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

require __DIR__.'/../../foundation/mail-compose/DraftAttachment.php';
use CajunWebPro\Foundation\MailCompose\DraftAttachment;

$checks=[];
function check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function refused(callable $run):bool{try{$run();return false;}catch(InvalidArgumentException|TypeError){return true;}}

$name='Quarterly report.pdf';$mime='application/pdf';$bytes="%PDF-1.4\n".str_repeat("\x00\xffA",100);
$file=new DraftAttachment($name,$mime,$bytes);
$name='changed.txt';$mime='text/plain';$bytes='changed';
check('Constructor detaches exact input values',$file->name==='Quarterly report.pdf'&&$file->mime==='application/pdf'&&$file->bytes()!==$bytes);
check('Explicit accessor returns exact private bytes',$file->bytes()==="%PDF-1.4\n".str_repeat("\x00\xffA",100));
check('Checksum binds exact content',$file->sha256===hash('sha256',$file->bytes()));
check('Descriptor is closed ordered metadata',array_keys($file->descriptor())===['name','mime','sha256','bytes']&&$file->descriptor()['bytes']===strlen($file->bytes()));
$encoded=(string)json_encode($file,JSON_THROW_ON_ERROR);
check('Object JSON cannot serialize private content',!str_contains($encoded,'PDF-1.4')&&!str_contains($encoded,'changed'));

foreach(['../evil.txt','..\\evil.txt',' evil.txt','evil.txt ','a/b.txt','a:b.txt','a"b.txt','a?b.txt','a*b.txt','a<b.txt','a>b.txt','a|b.txt','.', '..','',"bad\r\nname.txt","caf\xC3\xA9.txt",str_repeat('n',181)] as $i=>$bad){
    check('Noncanonical name is refused '.$i,refused(fn()=>new DraftAttachment($bad,'text/plain','x')));
}
check('Exact 180-byte printable name is accepted',strlen((new DraftAttachment(str_repeat('n',180),'text/plain','x'))->name)===180);
check('Internal spaces remain exact',(new DraftAttachment('Project notes.txt','text/plain','x'))->name==='Project notes.txt');

foreach(['text/plain','application/pdf','image/png','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] as $i=>$type){
    check('Canonical MIME token is accepted '.$i,(new DraftAttachment('a.bin',$type,'x'))->mime===$type);
}
foreach(['','not a mime','text','text/','/plain','./.','text/.plain','text/plain; charset=x',"text/plain\n",'TEXT/PLAIN',str_repeat('t',101).'/x'] as $i=>$bad){
    check('Noncanonical MIME token is refused '.$i,refused(fn()=>new DraftAttachment('a.bin',$bad,'x')));
}

check('Empty bytes are refused',refused(fn()=>new DraftAttachment('a.bin','application/octet-stream','')));
check('One byte is accepted',(new DraftAttachment('a.bin','application/octet-stream','x'))->bytes()==='x');
$maximum=new DraftAttachment('max.bin','application/octet-stream',str_repeat('a',DraftAttachment::MAX_BYTES));
check('Exact per-file maximum is accepted',strlen($maximum->bytes())===DraftAttachment::MAX_BYTES);
check('One byte over per-file maximum is refused',refused(fn()=>new DraftAttachment('large.bin','application/octet-stream',str_repeat('a',DraftAttachment::MAX_BYTES+1))));

$a=new DraftAttachment('a.txt','text/plain',str_repeat('a',DraftAttachment::MAX_BYTES));
$b=new DraftAttachment('b.txt','text/plain',str_repeat('b',DraftAttachment::MAX_BYTES));
check('Exact total maximum is accepted',DraftAttachment::list([$a,$b])===[$a,$b]);
check('One byte over total maximum is refused',refused(fn()=>DraftAttachment::list([$a,$b,new DraftAttachment('c.txt','text/plain','c')])));
check('Four files are accepted',count(DraftAttachment::list([
    new DraftAttachment('1.txt','text/plain','1'),new DraftAttachment('2.txt','text/plain','2'),
    new DraftAttachment('3.txt','text/plain','3'),new DraftAttachment('4.txt','text/plain','4'),
]))===4);
check('Five files are refused',refused(fn()=>DraftAttachment::list([
    new DraftAttachment('1.txt','text/plain','1'),new DraftAttachment('2.txt','text/plain','2'),
    new DraftAttachment('3.txt','text/plain','3'),new DraftAttachment('4.txt','text/plain','4'),new DraftAttachment('5.txt','text/plain','5'),
])));
check('Associative attachment arrays are refused',refused(fn()=>DraftAttachment::list(['first'=>new DraftAttachment('a.txt','text/plain','a')])));
check('Non-attachment values are refused',refused(fn()=>DraftAttachment::list([['name'=>'a.txt']])));
check('ASCII-case-folded duplicate names are refused',refused(fn()=>DraftAttachment::list([
    new DraftAttachment('Report.txt','text/plain','a'),new DraftAttachment('report.txt','text/plain','b'),
])));

$ordered=DraftAttachment::descriptors([
    new DraftAttachment('one.txt','text/plain',"\x00PRIVATE\xff"),new DraftAttachment('two.bin','application/octet-stream',"\x00\x01"),
]);
check('Descriptor list preserves list shape and order',array_is_list($ordered)&&array_column($ordered,'name')===['one.txt','two.bin']);
check('Descriptor list contains no raw content',!str_contains(json_encode($ordered,JSON_THROW_ON_ERROR),'PRIVATE'));

echo json_encode(['scope'=>'Canonical outbound attachment values and ordered private-content descriptors only; no MIME generation, draft snapshot, storage, authority, filesystem, SMTP, socket, provider or application acceptance','passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
