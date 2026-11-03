<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);
require dirname(__DIR__).'/site/app/activity/provider_sources.php';
set_error_handler(static function(int $level,string $message):never { throw new ErrorException($message,0,$level); });
$checks=[];$databaseCalls=0;
function ap_check(string $name,bool $passed):void {
    global $checks;
    $checks[]=['name'=>$name,'passed'=>$passed];
    if (!$passed) throw new RuntimeException($name);
}
function db():never { ++$GLOBALS['databaseCalls'];throw new LogicException('No database belongs to this pure test.'); }
function ap_row(string $key,string $time,?string $read=null):array {
    return ['event_key'=>$key,'occurred_at'=>$time,'read_at'=>$read];
}
function ap_key(int $id):string { return 'subdomain-check:'.str_pad(dechex($id),32,'0',STR_PAD_LEFT); }
$error=null;
try {
    foreach (['request','terminal','check'] as $kind) ap_check('Stable '.$kind.' key accepted',activity_provider_key('subdomain-'.$kind.':'.str_repeat('a',32)));
    foreach (['','message:1','subdomain-check:'.str_repeat('A',32),'subdomain-check:'.str_repeat('a',31),'subdomain-check:'.str_repeat('a',33),'subdomain-other:'.str_repeat('a',32),' subdomain-check:'.str_repeat('a',32),'subdomain-check:'.str_repeat('a',32)."\n"] as $index=>$key) {
        ap_check('Malformed or other-family provider key refused '.$index,!activity_provider_key($key));
    }
    $time='2026-10-19 00:00:00';
    $first=ap_row(ap_key(1),$time);$second=ap_row(ap_key(2),$time);$project=ap_row('message:9',$time);
    $mixed=activity_merge_rows([$project],[$first,$second],false,null);
    ap_check('Equal timestamps order provider and project keys strictly descending',array_column($mixed['rows'],'event_key')===[$second['event_key'],$first['event_key'],$project['event_key']]);
    ap_check('Short mixed page has no next cursor',$mixed['next']==='');
    $boundary=activity_merge_rows([$project],[$second,$first],false,$second);
    ap_check('Equal-time cursor excludes itself and retains lower keys',array_column($boundary['rows'],'event_key')===[$first['event_key'],$project['event_key']]);
    $old=ap_row(ap_key(3),'2026-10-18 23:59:59');$new=ap_row(ap_key(4),'2026-10-19 00:00:01');
    ap_check('Time precedes key and cursor excludes newer provider records',activity_merge_rows([],[$new,$second,$old],false,$second)['rows']===[$old]);
    $read=$first;$read['read_at']=$time;
    ap_check('Unread view hides a read provider update',activity_merge_rows([],[$read,$second],true,null)['rows']===[$second]);
    ap_check('All view retains provider read status',activity_merge_rows([],[$read],false,null)['rows']===[$read]);
    ap_check('Empty combined feed is stable',activity_merge_rows([],[],false,null)===['rows'=>[],'next'=>'']);

    // The caller supplies the SQL-selected legacy prefix (31 maximum). Exercise
    // the real merger across repeated cursors; this does not emulate SQL execution.
    $legacy=[];$provider=[];$oracle=[];
    for ($i=1;$i<=47;$i++) {
        $stamp='2026-10-19 00:00:'.str_pad((string)($i%3),2,'0',STR_PAD_LEFT);
        $a=ap_row('message:'.$i,$stamp,$i%7===0?$time:null);
        $b=ap_row(ap_key($i),$stamp,$i%5===0?$time:null);
        $legacy[]=$a;$provider[]=$b;
        foreach ([$a,$b] as $row) $oracle[$row['occurred_at'].'|'.$row['event_key']]=$row;
    }
    krsort($oracle,SORT_STRING);
    foreach ([false,true] as $unread) {
        $expected=array_values(array_filter($oracle,static fn($r)=>!$unread||$r['read_at']===null));
        $seen=[];$cursor=null;$pages=0;
        do {
            $eligible=[];
            foreach ($expected as $row) {
                if (!str_starts_with($row['event_key'],'message:')) continue;
                if ($cursor!==null&&strcmp($row['occurred_at'].'|'.$row['event_key'],$cursor['occurred_at'].'|'.$cursor['event_key'])>=0) continue;
                $eligible[]=$row;
            }
            $page=activity_merge_rows(array_slice($eligible,0,31),$provider,$unread,$cursor);
            ++$pages;
            if ($pages>4) throw new RuntimeException('Cursor did not terminate.');
            array_push($seen,...$page['rows']);
            if ($page['next']!=='') {
                ap_check(($unread?'Unread':'All').' page '.$pages.' exposes the exact thirtieth cursor',count($page['rows'])===30&&$page['next']===$page['rows'][29]['event_key']);
                $cursor=$page['rows'][29];
            }
        } while ($page['next']!=='');
        ap_check(($unread?'Unread':'All').' pagination retains every mixed source once in order',$seen===$expected&&count(array_unique(array_column($seen,'event_key')))===count($seen));
    }
    foreach ([['invalid',[]],['admin',[]],['client',[]],['admin',['uid'=>0]],['client',['client_id'=>0]]] as $index=>[$audience,$session]) {
        $_SESSION=$session;$called=false;$refused=false;
        try { activity_authenticated_snapshot($audience,function()use(&$called):void {$called=true;}); }
        catch (InvalidArgumentException|RuntimeException) {$refused=true;}
        ap_check('Invalid or anonymous polling refuses before database and callback '.$index,$refused&&!$called&&$GLOBALS['databaseCalls']===0);
    }
} catch (Throwable $e) { $error=get_class($e).': '.$e->getMessage(); }
echo json_encode(['passed'=>$error===null,'scope'=>'Actual pure key, merge, cursor and anonymous preflight functions; no SQL, provider, HTTP or browser execution.','checks'=>$checks,'count'=>count($checks),'error'=>$error],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
if ($error!==null) exit(1);
