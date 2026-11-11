<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require __DIR__.'/../../foundation/mail-compose/MessageThread.php';
use CajunWebPro\Foundation\MailCompose\MessageThread;

$checks = [];
function check(string $name, callable $test): void {
    global $checks;
    try { $ok = $test() === true; } catch (Throwable $e) { $ok = false; $error = get_class($e).': '.$e->getMessage(); }
    $checks[] = ['name'=>$name, 'passed'=>$ok] + (isset($error)?['error'=>$error]:[]);
}
function denied(callable $test): bool {
    try { $test(); return false; } catch (InvalidArgumentException) { return true; }
}

check('new or forwarded message starts without reply headers', fn()=>MessageThread::none()->toArray() === ['in_reply_to'=>null,'references'=>[]]);
check('reply identifies its exact parent', fn()=>MessageThread::reply('<Parent@Example.COM>')->toArray() === ['in_reply_to'=>'<Parent@Example.COM>','references'=>['<Parent@Example.COM>']]);
check('existing reference chain precedes the immediate parent', fn()=>MessageThread::reply('<parent@x>', ['<root@x>','<middle@x>'])->references === ['<root@x>','<middle@x>','<parent@x>']);
check('single parent In-Reply-To supplies a missing reference chain', fn()=>MessageThread::reply('<parent@x>', [], ['<root@x>'])->references === ['<root@x>','<parent@x>']);
check('parent References takes precedence over In-Reply-To', fn()=>MessageThread::reply('<parent@x>', ['<root@x>'], ['<ignored@x>'])->references === ['<root@x>','<parent@x>']);
check('multiple parent In-Reply-To identifiers do not invent a chain', fn()=>MessageThread::reply('<parent@x>', [], ['<a@x>','<b@x>'])->references === ['<parent@x>']);
check('missing parent id preserves existing references without inventing an id', fn()=>MessageThread::reply(null,['<root@x>'])->toArray() === ['in_reply_to'=>null,'references'=>['<root@x>']]);
check('completely absent parent identifiers remain absent', fn()=>MessageThread::reply(null)->toArray() === MessageThread::none()->toArray());
check('modern atom punctuation remains exact', fn()=>MessageThread::reply('<a!#$%&\'*+-/=?^_`{|}~.b@EXAMPLE.invalid>')->inReplyTo === '<a!#$%&\'*+-/=?^_`{|}~.b@EXAMPLE.invalid>');
check('domain literal is a token rather than a destination lookup', fn()=>MessageThread::reply('<a@[IPv6:2001:db8::1]>')->inReplyTo === '<a@[IPv6:2001:db8::1]>');
foreach (["<a@x>\r\nBcc: victim@example.invalid", "<a@x>\n", " <a@x>", "<a@x> ", '<a..b@x>', '<.a@x>', '<a.@x>', '<a@.x>', '<a@x..y>', '<"quoted"@x>', '<a(comment)@x>', '<a@x> <b@x>', '<a x@y>', "<a\t@x>", "<a\0@x>", '<café@x>', '', '<a@x>\r\n'] as $i=>$value) {
    check('unsupported or injected identifier refused '.$i, fn()=>denied(fn()=>MessageThread::reply($value)));
}
check('exact identifier byte bound accepted', function(){ $v='<'.str_repeat('a',MessageThread::MAX_IDENTIFIER_BYTES-4).'@x>'; return MessageThread::reply($v)->inReplyTo===$v; });
check('identifier byte overflow refused', fn()=>denied(fn()=>MessageThread::reply('<'.str_repeat('a',MessageThread::MAX_IDENTIFIER_BYTES-3).'@x>')));
check('sparse reference arrays refused', fn()=>denied(fn()=>MessageThread::reply('<a@x>',[1=>'<root@x>'])));
check('non-string reference refused without coercion', fn()=>denied(fn()=>MessageThread::reply('<a@x>',[true])));
check('reference-count limit includes appended parent', fn()=>denied(fn()=>MessageThread::reply('<a@x>',array_fill(0,MessageThread::MAX_REFERENCES,'<root@x>'))));
check('maximum bounded chain retains every element', fn()=>count(MessageThread::reply('<a@x>',array_fill(0,MessageThread::MAX_REFERENCES-1,'<root@x>'))->references)===MessageThread::MAX_REFERENCES);
check('aggregate header limit is independent of element count', fn()=>denied(fn()=>MessageThread::reply(null,array_fill(0,9,'<'.str_repeat('a',508).'@x>'))));
check('reference values detach from external aliases', function(){ $v='<root@x>'; $a=[&$v]; $t=MessageThread::reply('<parent@x>',$a); $v="<evil@x>\r\nBcc: injected"; return $t->references===['<root@x>','<parent@x>']; });
check('exported arrays cannot change a thread', function(){ $t=MessageThread::reply('<a@x>'); $a=$t->toArray(); $a['references'][0]='<other@x>'; return $t->references===['<a@x>']; });
check('public reference property is readonly', function(){ $t=MessageThread::reply('<a@x>'); try{$t->references[0]='<other@x>';return false;}catch(Error){return $t->references===['<a@x>'];} });
check('stored round trip preserves ordered exact identifiers', function(){ $t=MessageThread::reply('<P@X>',['<R@X>']);return MessageThread::fromStored(json_decode(json_encode($t->toArray(),JSON_THROW_ON_ERROR),true,16,JSON_THROW_ON_ERROR))->toArray()===$t->toArray(); });
foreach ([[],['in_reply_to'=>null],['in_reply_to'=>false,'references'=>[]],['in_reply_to'=>null,'references'=>''],['in_reply_to'=>null,'references'=>[],'extra'=>'x']] as $i=>$value) {
    check('malformed stored context refused '.$i, fn()=>denied(fn()=>MessageThread::fromStored($value)));
}
check('stored header injection is revalidated', fn()=>denied(fn()=>MessageThread::fromStored(['in_reply_to'=>'<a@x>','references'=>["<b@x>\r\nInjected: yes"]])));
$passed = !array_filter($checks, fn($c)=>!$c['passed']);
echo json_encode(['scope'=>'Isolated conversation identifier contract; no parser, application, database, MIME or mail delivery acceptance','passed'=>$passed,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
