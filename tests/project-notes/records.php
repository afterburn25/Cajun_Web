<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);

$source=in_array('--deployment',$argv,true)?'site/includes/foundation':'foundation';
require __DIR__.'/../../'.$source.'/projects/ProjectPrivateNote.php';

use CajunWebPro\Foundation\Projects\ProjectPrivateNote;

$checks=[];
function noteCheck(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function noteRefused(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|LogicException|JsonException|TypeError){noteCheck($name,true);return;}noteCheck($name,false);}
function noteJson(array $data):string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);}
function noteAltered(ProjectPrivateNote $note,array $fields):ProjectPrivateNote {return ProjectPrivateNote::fromJson(noteJson(array_replace($note->data(),$fields)));}

try {
    $literal="Private </textarea><script>alert(1)</script> & é\r\n\tsecond line";
    $note=ProjectPrivateNote::create(12,21,7,100,$literal);
    $data=$note->data();
    noteCheck('Private record retains exact normalized text for later escaped rendering',$data['body']===$literal);
    noteCheck('Private record retains exact project staff identity and time',array_slice($data,1,4,true)===['id'=>12,'project_id'=>21,'staff_id'=>7,'created_at'=>100]);
    noteCheck('Canonical record roundtrips byte for byte',ProjectPrivateNote::fromJson($note->json())->json()===$note->json());
    noteCheck('Fingerprint binds every private byte',$note->fingerprint()===hash('sha256',$note->json()));
    noteCheck('Established form trimming is canonicalized at creation',ProjectPrivateNote::create(13,21,7,101,"  Staff context  \r\n")->data()['body']==='Staff context');
    $prepared=ProjectPrivateNote::canonicalBody("\u{00A0}  Adapter context  \r\n");
    noteCheck('Public body preparation exposes the exact canonical storage text',$prepared==='Adapter context');
    noteCheck('Creation uses the same public canonical body preparation',ProjectPrivateNote::create(15,21,7,103,"\u{00A0}  Adapter context  \r\n")->data()['body']===$prepared);
    noteRefused('Public body preparation refuses invalid content before storage',fn()=>ProjectPrivateNote::canonicalBody("invalid\0body"));

    $staff=$note->forStaffProject(21);
    noteCheck('Authorized staff projection contains the private record without format metadata',array_keys($staff)===['id','project_id','staff_id','created_at','body']&&$staff['body']===$literal);
    noteRefused('Another project cannot obtain the staff projection',fn()=>$note->forStaffProject(22));
    noteRefused('Private note has no client contact or support projection',fn()=>$note->forClient());
    $audit=$note->auditReference();
    noteCheck('Audit reference excludes private body and fingerprint',array_keys($audit)===['id','project_id','staff_id','created_at']&&!str_contains(noteJson($audit),'Private'));

    $newest=ProjectPrivateNote::create(14,21,8,102,'Newest private note');
    ProjectPrivateNote::verifyStaffPage([$newest,$note],21);
    noteCheck('Bounded newest-first staff page verifies',true);
    ProjectPrivateNote::verifyStaffPage([],21);
    noteCheck('Empty authorized project page is valid',true);
    noteRefused('Staff page refuses mixed projects',fn()=>ProjectPrivateNote::verifyStaffPage([$newest,ProjectPrivateNote::create(11,22,8,99,'Other project note')],21));
    noteRefused('Staff page refuses duplicate identities',fn()=>ProjectPrivateNote::verifyStaffPage([$newest,$newest],21));
    noteRefused('Staff page refuses ascending identities',fn()=>ProjectPrivateNote::verifyStaffPage([$note,$newest],21));
    noteRefused('Staff page refuses associative input',fn()=>ProjectPrivateNote::verifyStaffPage(['note'=>$note],21));
    noteRefused('Staff page refuses non-note values',fn()=>ProjectPrivateNote::verifyStaffPage([[]],21));
    $page=[];for($id=ProjectPrivateNote::MAX_PAGE;$id>0;$id--)$page[]=ProjectPrivateNote::create($id,21,7,100+$id,'Bounded note '.$id);
    ProjectPrivateNote::verifyStaffPage($page,21);noteCheck('Maximum bounded staff page verifies',count($page)===ProjectPrivateNote::MAX_PAGE);
    noteRefused('Unbounded staff page is refused',fn()=>ProjectPrivateNote::verifyStaffPage([...$page,ProjectPrivateNote::create(101,21,7,201,'Extra note')],21));

    foreach([
        'empty'=>'','blank'=>'  ','one byte'=>'x','oversized'=>str_repeat('x',ProjectPrivateNote::MAX_BODY_BYTES+1),
        'over-limit before trim'=>str_repeat('x',ProjectPrivateNote::MAX_BODY_BYTES).' ',
        'invalid UTF-8'=>"bad\xFF",'NUL'=>"bad\0note",'leading NUL'=>"\0valid note",'trailing NUL'=>"valid note\0",
        'control'=>"bad\x01note",'delete'=>"bad\x7Fnote",'NBSP blank'=>"\u{00A0}\u{00A0}",
        'C1 NEL'=>"line\u{0085}break",'zero width'=>"zero\u{200B}width",'bidi override'=>"bidi\u{202E}override",
        'invisible separator'=>"A\u{2063}B",'combining grapheme joiner only'=>"\u{034F}\u{034F}",
        'Hangul filler only'=>"\u{3164}\u{3164}",'combining marks only'=>"\u{0301}\u{0301}",
    ] as $name=>$body)noteRefused('Body refuses '.$name,fn()=>ProjectPrivateNote::create(30,21,7,100,$body));
    foreach([
        'leading ZWJ'=>"\u{200D}AB",'trailing ZWJ'=>"AB\u{200D}",'Latin ZWJ'=>"A\u{200D}B",
        'Latin ZWNJ'=>"A\u{200C}B",'Latin combining ZWJ'=>"A\u{0301}\u{200D}B",
        'ASCII variation selector'=>"A\u{FE0F}B",'repeated joiners'=>'AB'.str_repeat("\u{200D}",100),
        'braille blank'=>"\u{2800}\u{2800}",'ideographic half fill space'=>"\u{303F}\u{303F}",
        'Unicode noncharacter'=>"\u{FDD0}\u{FDD0}",
    ] as $name=>$body)noteRefused('Body refuses malformed Unicode '.$name,fn()=>ProjectPrivateNote::create(30,21,7,100,$body));
    noteCheck('Exact body byte limit is accepted',strlen(ProjectPrivateNote::create(30,21,7,100,str_repeat('x',ProjectPrivateNote::MAX_BODY_BYTES))->data()['body'])===ProjectPrivateNote::MAX_BODY_BYTES);
    noteCheck('Multibyte body limit is measured in bytes',strlen(ProjectPrivateNote::create(30,21,7,100,str_repeat('é',2500))->data()['body'])===ProjectPrivateNote::MAX_BODY_BYTES);
    noteRefused('Multibyte body cannot exceed byte limit',fn()=>ProjectPrivateNote::create(30,21,7,100,str_repeat('é',2501)));
    noteCheck('Unicode edge separators trim around visible content',ProjectPrivateNote::create(30,21,7,100,"\u{00A0}visible note\u{00A0}")->data()['body']==='visible note');
    $validUnicode="Indic \u{0915}\u{094D}\u{200D}\u{0937}; Persian می\u{200C}خواهم; family \u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}; styled \u{1F469}\u{1F3FD}\u{200D}\u{1F4BB}; plane \u{2708}\u{FE0E} \u{2708}\u{FE0F}; keycap 1\u{FE0F}\u{20E3}";
    noteCheck('Contextual script joiners and standard emoji sequences remain valid',ProjectPrivateNote::create(30,21,7,100,$validUnicode)->data()['body']===$validUnicode);
    $escapeHeavy=ProjectPrivateNote::create(30,21,7,100,str_repeat('"',ProjectPrivateNote::MAX_BODY_BYTES));
    noteCheck('Maximum escape-heavy body remains inside canonical record bound',strlen($escapeHeavy->json())<=ProjectPrivateNote::MAX_RECORD_BYTES&&strlen($escapeHeavy->data()['body'])===ProjectPrivateNote::MAX_BODY_BYTES);
    foreach(['project_id','staff_id'] as $field){
        foreach([0,-1,2147483648] as $bad)noteRefused('Record refuses invalid '.$field.' '.$bad,fn()=>noteAltered($note,[$field=>$bad]));
        foreach(['1',1.0,true,null] as $case=>$bad)noteRefused('Record refuses coerced '.$field.' case '.$case,fn()=>noteAltered($note,[$field=>$bad]));
    }
    foreach([0,-1] as $bad)noteRefused('Record refuses invalid id '.$bad,fn()=>noteAltered($note,['id'=>$bad]));
    foreach(['1',1.0,true,null] as $case=>$bad)noteRefused('Record refuses coerced id case '.$case,fn()=>noteAltered($note,['id'=>$bad]));
    noteCheck('Existing BIGINT note identities remain representable',ProjectPrivateNote::create(PHP_INT_MAX,21,7,100,'Largest platform note id')->data()['id']===PHP_INT_MAX);
    foreach([-1,253402300800,'100',100.0,true,null] as $case=>$bad)noteRefused('Record refuses invalid time case '.$case,fn()=>noteAltered($note,['created_at'=>$bad]));
    noteCheck('UTC timestamp boundaries roundtrip',ProjectPrivateNote::create(31,21,7,0,'Epoch note')->data()['created_at']===0&&ProjectPrivateNote::create(32,21,7,253402300799,'Far note')->data()['created_at']===253402300799);

    foreach([
        'wrong format'=>['format'=>2], 'coerced format'=>['format'=>'1'], 'stored leading whitespace'=>['body'=>' private'],
        'stored trailing whitespace'=>['body'=>'private '], 'stored Unicode edge whitespace'=>['body'=>"\u{00A0}private\u{00A0}"],
        'stored blank'=>['body'=>'  '], 'stored non-text'=>['body'=>7],
        'stored Latin joiner'=>['body'=>"A\u{200D}B"], 'stored noncharacter'=>['body'=>"ok\u{FDD0}"],
    ] as $name=>$fields)noteRefused('Stored record refuses '.$name,fn()=>noteAltered($note,$fields));
    $raw=$note->json();
    foreach(['','[]','{}','null','true','42'," \n".$raw,$raw."\n",str_replace('"format":1','"format":1,"format":1',$raw),str_replace('"format":1','"format":1.0',$raw),json_encode($note->data(),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),noteJson(array_reverse($note->data(),true)),str_repeat('x',ProjectPrivateNote::MAX_RECORD_BYTES+1)] as $i=>$bad){
        noteRefused('Noncanonical or oversized JSON refused '.$i,fn()=>ProjectPrivateNote::fromJson($bad));
    }
    $extra=$note->data();$extra['client_visible']=false;
    noteRefused('Stored record refuses extra visibility field',fn()=>ProjectPrivateNote::fromJson(noteJson($extra)));
    $missing=$note->data();unset($missing['staff_id']);
    noteRefused('Stored record refuses missing attribution',fn()=>ProjectPrivateNote::fromJson(noteJson($missing)));
    $copy=$note->data();$copy['body']='Changed externally';$staff['body']='Changed externally';
    noteCheck('Returned arrays cannot mutate private record',$note->data()['body']===$literal&&$note->forStaffProject(21)['body']===$literal);
    $body='Original private body';$referenced=ProjectPrivateNote::create(40,21,7,100,$body);$body='Changed later';
    noteCheck('Caller variables cannot mutate private record',$referenced->data()['body']==='Original private body');

    $names=array_column($checks,'name');if(count($names)!==count(array_unique($names)))throw new RuntimeException('Duplicate test names.');
    echo noteJson(['scope'=>'Pure private-note records only; no routing, authority, schema, database, UI, customer message, notification, provider or live acceptance.','php'=>PHP_VERSION,'checks'=>$checks,'passed'=>true])."\n";
} catch(Throwable $error) {
    echo noteJson(['checks'=>$checks,'passed'=>false,'failure'=>get_class($error).': '.$error->getMessage()])."\n";
    exit(1);
}
