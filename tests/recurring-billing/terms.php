<?php
declare(strict_types=1);
require_once __DIR__.'/../../foundation/recurring-billing/RecurringBillingTerms.php';
use CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms as Terms;

$checks = [];
function verify(string $name, bool $passed): void {
    global $checks;
    $checks[] = ['name'=>$name, 'passed'=>$passed];
    if (!$passed) throw new RuntimeException($name);
}
function refused(string $name, callable $call): void {
    try { $call(); $refused = false; } catch (InvalidArgumentException) { $refused = true; }
    verify($name, $refused);
}
function fixture(array $changes = []): array {
    return array_replace([
        'format'=>1, 'id'=>'0123456789abcdef0123456789abcdef', 'client_id'=>7, 'project_id'=>19,
        'source_subscription'=>['id'=>str_repeat('a',32), 'revision'=>3, 'sha256'=>str_repeat('b',64)],
        'title'=>'Website maintenance', 'terms'=>'Approved maintenance only; invoice each month.',
        'currency'=>'USD', 'amount_cents'=>12500, 'cadence'=>'monthly', 'anchor'=>'2024-01-31',
        'alignment'=>'day-of-month', 'timezone'=>'America/New_York', 'due_after_days'=>15, 'collection'=>'invoice-only',
    ], $changes);
}
function terms(array $changes = []): Terms { return Terms::fromArray(fixture($changes)); }
function utc(string $date): int { return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp(); }

$originalZone = date_default_timezone_get();
try {
    $document = terms();
    $literal = '{"format":1,"id":"0123456789abcdef0123456789abcdef","client_id":7,"project_id":19,"source_subscription":{"id":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","revision":3,"sha256":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"},"title":"Website maintenance","terms":"Approved maintenance only; invoice each month.","currency":"USD","amount_cents":12500,"cadence":"monthly","anchor":"2024-01-31","alignment":"day-of-month","timezone":"America/New_York","due_after_days":15,"collection":"invoice-only"}';
    verify('Canonical bytes match an independent literal document', $document->json()===$literal);
    verify('Exact data and SHA256 fingerprint round trip', Terms::fromJson($literal)->data()===fixture() && $document->fingerprint()===hash('sha256',$literal));
    $reordered = array_reverse(fixture(),true);
    $reordered['source_subscription'] = array_reverse($reordered['source_subscription'],true);
    verify('Array input is copied into fixed top-level and source-field order', Terms::fromArray($reordered)->json()===$literal);
    $literalText = "  <img src=x onerror=alert(1)> / Café \n\tKeep these literal terms.  ";
    $textDocument = terms(['title'=>' Café / support ', 'terms'=>$literalText]);
    verify('Literal Unicode markup whitespace LF and TAB remain unchanged', $textDocument->data()['title']===' Café / support ' && $textDocument->data()['terms']===$literalText && Terms::fromJson($textDocument->json())->json()===$textDocument->json());
    verify('Unicode and slashes are unescaped while JSON string controls are escaped', str_contains($textDocument->json(),'Café / support') && str_contains($textDocument->json(),'\\n\\tKeep'));
    $input = fixture(); $input['source_subscription']['revision'] = 256;
    $saved = Terms::fromArray($input); $original = $saved->json();
    $input['amount_cents'] = 1; $input['source_subscription']['revision'] = 2;
    $copy = $saved->data(); $copy['source_subscription']['sha256'] = str_repeat('c',64); $copy['terms'] = 'Changed';
    $periodCopy = $saved->period(0); $periodCopy['amount_cents'] = 1;
    verify('Input data output data and period edits cannot mutate stored terms', $saved->json()===$original && $saved->data()['source_subscription']['revision']===256 && $saved->period(0)['amount_cents']===12500);
    $external = 'Original'; $referenced = fixture(); $referenced['terms'] = &$external;
    $detached = Terms::fromArray($referenced); $external = 'Replacement';
    verify('Referenced caller values are detached at construction', $detached->data()['terms']==='Original');
    foreach (['id'=>str_repeat('c',32), 'client_id'=>8, 'project_id'=>20, 'title'=>'Updated title', 'terms'=>'Updated exact terms', 'amount_cents'=>12501, 'cadence'=>'quarterly', 'anchor'=>'2024-02-29', 'alignment'=>'month-end', 'timezone'=>'UTC', 'due_after_days'=>16] as $field=>$value) {
        verify('Fingerprint binds '.$field, terms([$field=>$value])->fingerprint()!==$document->fingerprint());
    }
    foreach (['id'=>str_repeat('d',32), 'revision'=>4, 'sha256'=>str_repeat('e',64)] as $field=>$value) {
        $source = fixture()['source_subscription']; $source[$field] = $value;
        verify('Fingerprint binds source subscription '.$field, terms(['source_subscription'=>$source])->fingerprint()!==$document->fingerprint());
    }
    foreach ([null, true, 1, 'document', new stdClass, []] as $n=>$bad) {
        refused('Reject non-document array input '.$n,fn()=>Terms::fromArray($bad));
    }
    foreach (array_keys(fixture()) as $field) {
        $missing = fixture(); unset($missing[$field]);
        refused('Reject missing field '.$field,fn()=>Terms::fromArray($missing));
    }
    foreach (['extra'=>true,'accepted'=>true,'billable'=>true,'automatic_payment'=>true] as $field=>$value) {
        refused('Reject undeclared authority field '.$field,fn()=>terms([$field=>$value]));
    }
    foreach ([0,2,'1',1.0,true,null] as $n=>$bad) refused('Reject unsupported strict format '.$n,fn()=>terms(['format'=>$bad]));
    foreach (['client_id','project_id'] as $field) {
        foreach ([0,-1,2147483648,'7',7.0,true,null,[],new stdClass] as $n=>$bad) refused('Reject '.$field.' type or range '.$n,fn()=>terms([$field=>$bad]));
        verify('Accept '.$field.' exact endpoint integers',terms([$field=>1])->data()[$field]===1 && terms([$field=>2147483647])->data()[$field]===2147483647);
    }
    foreach (['id','sha256'] as $field) {
        $length = $field==='id' ? 32 : 64;
        foreach (['',str_repeat('a',$length-1),str_repeat('a',$length+1),str_repeat('A',$length),str_repeat('g',$length),str_repeat('a',$length)."\n",null,true,[],42] as $n=>$bad) {
            $source = fixture()['source_subscription']; $source[$field]=$bad;
            refused('Reject source '.$field.' '.$n,fn()=>terms(['source_subscription'=>$source]));
            if ($field==='id') refused('Reject schedule identity '.$n,fn()=>terms(['id'=>$bad]));
        }
    }
    foreach (['id','revision','sha256'] as $field) {
        $source=fixture()['source_subscription']; unset($source[$field]);
        refused('Reject missing source '.$field,fn()=>terms(['source_subscription'=>$source]));
    }
    foreach ([null,[],new stdClass,'source',array_merge(fixture()['source_subscription'],['accepted'=>true])] as $n=>$bad) refused('Reject invalid source shape '.$n,fn()=>terms(['source_subscription'=>$bad]));
    foreach ([0,-1,257,'3',3.0,true,null,[]] as $n=>$bad) {
        $source=fixture()['source_subscription'];$source['revision']=$bad;
        refused('Reject source revision '.$n,fn()=>terms(['source_subscription'=>$source]));
    }
    foreach ([1,256] as $revision) {
        $source=fixture()['source_subscription'];$source['revision']=$revision;
        verify('Accept native source revision bound '.$revision,terms(['source_subscription'=>$source])->data()['source_subscription']===$source);
    }
    foreach (['title'=>120,'terms'=>16384] as $field=>$limit) {
        foreach (['',' ',"\t\n", "\u{00a0}\u{2003}","bad\0text","bad\rtext","bad\x7ftext","\xc3\x28",str_repeat('x',$limit+1),true,1,null,[],new stdClass] as $n=>$bad) refused('Reject invalid '.$field.' '.$n,fn()=>terms([$field=>$bad]));
        verify('Accept exact UTF8 byte limit '.$field,strlen(terms([$field=>str_repeat('é',intdiv($limit,2))])->data()[$field])===$limit);
    }
    foreach (["Title\ntext","Title\ttext"] as $n=>$bad) refused('Reject title controls '.$n,fn()=>terms(['title'=>$bad]));
    foreach (range(0,31) as $code) {
        if (in_array($code,[9,10],true)) continue;
        refused('Reject terms control byte '.$code,fn()=>terms(['terms'=>'x'.chr($code).'y']));
    }
    foreach (['amount_cents'=>99999999,'due_after_days'=>60] as $field=>$max) {
        foreach ([-1,$max+1,'0',1.0,true,null,[],INF,NAN] as $n=>$bad) refused('Reject '.$field.' type or range '.$n,fn()=>terms([$field=>$bad]));
        verify('Accept exact '.$field.' bounds',terms([$field=>0])->data()[$field]===0 && terms([$field=>$max])->data()[$field]===$max);
    }
    foreach (['EUR','usd','USD ',null,true,[]] as $n=>$bad) refused('Reject unsupported currency '.$n,fn()=>terms(['currency'=>$bad]));
    foreach (['automatic','card','invoice-only ',null,true,[]] as $n=>$bad) refused('Reject collection mandate '.$n,fn()=>terms(['collection'=>$bad]));
    foreach (['one-time','weekly','Monthly',null,true,[]] as $n=>$bad) refused('Reject unsupported cadence '.$n,fn()=>terms(['cadence'=>$bad]));
    foreach (['2024-02-30','2025-02-29','2024-1-31','10000-01-01','2024-01-31 +1 day',null,true,[]] as $n=>$bad) refused('Reject malformed anchor '.$n,fn()=>terms(['anchor'=>$bad]));
    foreach (['day','month-end ',null,true,[]] as $n=>$bad) refused('Reject unsupported alignment '.$n,fn()=>terms(['alignment'=>$bad]));
    refused('Month-end anchor must actually end its month',fn()=>terms(['anchor'=>'2024-01-30','alignment'=>'month-end']));
    refused('Initial period requires a representable exclusive end',fn()=>terms(['anchor'=>'9999-12-31']));
    foreach (['','GMT','US/Eastern','EST','Z','+00:00','America/new_york','UTC ','Unknown/Place',null,true,[],new stdClass] as $n=>$bad) refused('Reject unknown offset abbreviation or alias timezone '.$n,fn()=>terms(['timezone'=>$bad]));
    foreach (['UTC','America/New_York','Europe/London','Asia/Kathmandu','Pacific/Chatham','Pacific/Kiritimati'] as $zone) verify('Accept explicit IANA timezone '.$zone,terms(['timezone'=>$zone])->data()['timezone']===$zone);
    foreach ([null,1,1.0,true,[],new stdClass,'','null','[]','{}','{','"text"',"\xef\xbb\xbf".$literal,$literal.' ',$literal."\n",str_repeat('x',32769)] as $n=>$bad) refused('Reject noncanonical JSON input '.$n,fn()=>Terms::fromJson($bad));
    $alternates=[
        str_replace('"format":1,','"format":1,"format":1,',$literal),
        str_replace('"revision":3,','"revision":3,"revision":3,',$literal),
        str_replace('"format":1,','"format":1.0,',$literal),
        str_replace('"amount_cents":12500','"amount_cents":1.25e4',$literal),
        str_replace('"client_id":7','"client_id":07',$literal),
        str_replace('"client_id":7','"client_id":7,"project_id":20',$literal),
        str_replace('America/New_York','America\\/New_York',$literal),
        str_replace('Website','\\u0057ebsite',$literal),
        json_encode(array_reverse(fixture(),true),JSON_UNESCAPED_SLASHES),
        json_encode($reordered,JSON_UNESCAPED_SLASHES),
        str_replace('"format":1','"format": 1',$literal),
        substr($literal,0,-1).',"unknown":0}',
    ];
    foreach ($alternates as $n=>$bad) refused('Reject duplicate altered ordered or escaped canonical JSON '.$n,fn()=>Terms::fromJson($bad));
    $emptyTextBytes = strlen(json_encode(fixture(['terms'=>'']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $room = Terms::MAX_BYTES-$emptyTextBytes;
    $maximumText = 'x'.str_repeat("\t",intdiv($room-1,2)).(($room-1)%2 ? 'x' : '');
    $maximum = terms(['terms'=>$maximumText]);
    verify('Accept exact maximum canonical byte size without truncation',strlen($maximum->json())===32768 && Terms::fromJson($maximum->json())->json()===$maximum->json());
    refused('Reject encoded bytes beyond bound even when literal text fits',fn()=>terms(['terms'=>$maximumText.'x']));
    refused('Reject deeply nested JSON before document processing',fn()=>Terms::fromJson(str_repeat('[',20).'0'.str_repeat(']',20)));

    $expected=['key'=>'0123456789abcdef0123456789abcdef:1','index'=>1,'start'=>'2024-02-29','end'=>'2024-03-31','due'=>'2024-03-15','timezone'=>'America/New_York','currency'=>'USD','amount_cents'=>12500];
    verify('Period facts match literal key context amount and due date', $document->period(1)===$expected);
    verify('Zero priced period stays zero and has no billability or authorization flags', terms(['amount_cents'=>0])->period(0)['amount_cents']===0 && array_keys($document->period(1))===array_keys($expected));
    verify('Maximum integer price is not rounded',terms(['amount_cents'=>99999999])->period(0)['amount_cents']===99999999);
    verify('Same schedule period retains identity after source or terms changes',terms(['terms'=>'New text'])->period(1)['key']===$expected['key']);
    verify('Different schedule and period identities stay distinct',terms(['id'=>str_repeat('c',32)])->period(1)['key']!==$expected['key'] && $document->period(0)['key']!==$expected['key']);
    $dates=[
        ['2024-01-31','monthly','day-of-month',0,0,'2024-01-31','2024-02-29','2024-01-31'],
        ['2024-01-31','monthly','day-of-month',1,60,'2024-02-29','2024-03-31','2024-04-29'],
        ['2025-01-31','monthly','day-of-month',1,1,'2025-02-28','2025-03-31','2025-03-01'],
        ['2025-01-30','monthly','day-of-month',2,30,'2025-03-30','2025-04-30','2025-04-29'],
        ['2025-04-30','monthly','month-end',1,1,'2025-05-31','2025-06-30','2025-06-01'],
        ['2023-11-30','quarterly','month-end',1,31,'2024-02-29','2024-05-31','2024-03-31'],
        ['2025-11-30','quarterly','day-of-month',1,1,'2026-02-28','2026-05-30','2026-03-01'],
        ['2024-02-29','annually','day-of-month',4,1,'2028-02-29','2029-02-28','2028-03-01'],
        ['1896-02-29','annually','day-of-month',4,1,'1900-02-28','1901-02-28','1900-03-01'],
        ['1996-02-29','annually','day-of-month',4,1,'2000-02-29','2001-02-28','2000-03-01'],
        ['2096-02-29','annually','day-of-month',4,1,'2100-02-28','2101-02-28','2100-03-01'],
        ['2396-02-29','annually','day-of-month',4,1,'2400-02-29','2401-02-28','2400-03-01'],
        ['2024-12-31','monthly','month-end',0,60,'2024-12-31','2025-01-31','2025-03-01'],
        ['1000-01-01','monthly','day-of-month',0,60,'1000-01-01','1000-02-01','1000-03-02'],
        ['9999-10-31','monthly','month-end',0,60,'9999-10-31','9999-11-30','9999-12-30'],
    ];
    foreach ($dates as $n=>[$anchor,$cadence,$alignment,$index,$days,$start,$end,$due]) {
        $period=terms(['anchor'=>$anchor,'cadence'=>$cadence,'alignment'=>$alignment,'due_after_days'=>$days])->period($index);
        verify('Independent literal calendar and due example '.$n,$period['start']===$start && $period['end']===$end && $period['due']===$due);
    }
    foreach ([-1,120000,'0',0.0,true,null,[],new stdClass] as $n=>$bad) refused('Reject invalid period index '.$n,fn()=>$document->period($bad));
    refused('Reject out-of-range period end',fn()=>$document->period(119999));
    $late=terms(['anchor'=>'9999-11-30','alignment'=>'month-end','due_after_days'=>60]);
    refused('Reject due date overflow rather than returning partial facts',fn()=>$late->period(0));
    $distant=terms(['anchor'=>'1000-01-31','timezone'=>'UTC','due_after_days'=>0]);
    verify('Distant lookup preserves direct global index', $distant->periodAt(utc('9998-12-30 12:00:00'))['key']==='0123456789abcdef0123456789abcdef:107986');
    foreach ([-1,253402300800,'0',0.0,true,null,[],new stdClass,INF,NAN] as $n=>$bad) {
        refused('Reject invalid UTC epoch '.$n,fn()=>$document->billingDate($bad));
        refused('Reject invalid period lookup epoch '.$n,fn()=>$document->periodAt($bad));
    }
    $times=[
        ['America/New_York','2024-02-29 04:59:59','2024-02-28'],
        ['America/New_York','2024-02-29 05:00:00','2024-02-29'],
        ['America/New_York','2024-03-10 06:59:59','2024-03-10'],
        ['America/New_York','2024-03-10 07:00:00','2024-03-10'],
        ['America/New_York','2024-03-11 03:59:59','2024-03-10'],
        ['America/New_York','2024-03-11 04:00:00','2024-03-11'],
        ['America/New_York','2024-11-03 05:30:00','2024-11-03'],
        ['America/New_York','2024-11-03 06:30:00','2024-11-03'],
        ['America/New_York','2024-11-04 04:59:59','2024-11-03'],
        ['America/New_York','2024-11-04 05:00:00','2024-11-04'],
        ['Asia/Kathmandu','2024-02-29 18:14:59','2024-02-29'],
        ['Asia/Kathmandu','2024-02-29 18:15:00','2024-03-01'],
        ['Pacific/Kiritimati','2024-02-29 09:59:59','2024-02-29'],
        ['Pacific/Kiritimati','2024-02-29 10:00:00','2024-03-01'],
        ['Pacific/Apia','2011-12-30 09:59:59','2011-12-29'],
        ['Pacific/Apia','2011-12-30 10:00:00','2011-12-31'],
        ['UTC','1970-01-01 00:00:00','1970-01-01'],
        ['America/Los_Angeles','1970-01-01 00:00:00','1969-12-31'],
        ['UTC','9999-12-31 23:59:59','9999-12-31'],
    ];
    foreach ($times as $n=>[$zone,$instant,$expectedDate]) verify('Literal local date midnight DST or date-line example '.$n,terms(['timezone'=>$zone])->billingDate(utc($instant))===$expectedDate);
    refused('Positive offset must not return a five-digit local year',fn()=>terms(['timezone'=>'Pacific/Kiritimati'])->billingDate(253402300799));
    verify('Latest positive-offset instant still in supported local year is accepted',terms(['timezone'=>'Pacific/Kiritimati'])->billingDate(253402250399)==='9999-12-31');
    verify('Lookup before exact local anchor midnight is absent',$document->periodAt(utc('2024-01-31 04:59:59'))===null);
    verify('Exact local anchor midnight enters first period',$document->periodAt(utc('2024-01-31 05:00:00'))===$document->period(0));
    verify('Second before local renewal remains in old period',$document->periodAt(utc('2024-02-29 04:59:59'))===$document->period(0));
    verify('Exact local renewal midnight enters new period',$document->periodAt(utc('2024-02-29 05:00:00'))===$expected);
    verify('Post-DST midnight uses the new offset',$document->periodAt(utc('2024-03-31 03:59:59'))===$expected && $document->periodAt(utc('2024-03-31 04:00:00'))===$document->period(2));
    $fall=terms(['anchor'=>'2024-10-04']);
    verify('Post-fall-DST renewal uses standard-time midnight',$fall->periodAt(utc('2024-11-04 04:59:59'))===$fall->period(0) && $fall->periodAt(utc('2024-11-04 05:00:00'))===$fall->period(1));
    $leapday=terms(['anchor'=>'2024-02-29','cadence'=>'annually','timezone'=>'UTC']);
    verify('Annual period lookup retains original leap-day alignment',$leapday->periodAt(utc('2028-02-28 23:59:59'))===$leapday->period(3) && $leapday->periodAt(utc('2028-02-29 00:00:00'))===$leapday->period(4));
    foreach (['2024-03-09'=>'2024-03-11','2024-11-02'=>'2024-11-04'] as $anchor=>$due) verify('Due offset counts calendar days across DST '.$anchor,terms(['anchor'=>$anchor,'due_after_days'=>2])->period(0)['due']===$due);
    foreach (['UTC','Pacific/Apia','Asia/Kathmandu'] as $processZone) {
        date_default_timezone_set($processZone);
        verify('Stored timezone and due date ignore process setting '.$processZone,$document->billingDate(utc('2024-02-29 04:59:59'))==='2024-02-28' && $document->periodAt(utc('2024-02-29 05:00:00'))===$expected && terms()->json()===$literal);
    }
    refused('Latest UTC date cannot produce a period with an unrepresentable end',fn()=>terms(['timezone'=>'UTC'])->periodAt(253402300799));
    verify('Failed calls preserve original canonical document and valid results',$document->json()===$literal && $document->period(1)===$expected);
    verify('No duplicate named assertions', count(array_unique(array_column($checks,'name')))===count($checks));
    date_default_timezone_set($originalZone);
    echo json_encode(['passed'=>count($checks),'failed'=>0,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $error) {
    date_default_timezone_set($originalZone);
    echo json_encode(['passed'=>count(array_filter($checks,fn($c)=>$c['passed'])),'failed'=>1,'checks'=>$checks,'error'=>get_class($error).': '.$error->getMessage()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    exit(1);
}
