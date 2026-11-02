<?php
declare(strict_types=1);
require_once __DIR__.'/../../'.(in_array('--deployment',$argv,true)?'site/includes/':'').'foundation/design-payments/DesignPaymentSchedule.php';
use CajunWebPro\Foundation\DesignPayments\DesignPaymentSchedule as Schedule;

$checks=[];
function verify(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function denied(string $name,callable $call):void {try{$call();$refused=false;}catch(InvalidArgumentException){$refused=true;}verify($name,$refused);}
function identity(int $number):string{return str_pad(dechex($number),32,'0',STR_PAD_LEFT);}
function stage(int $id,string $kind,string $calculation,int $value):array{return ['id'=>identity($id),'kind'=>$kind,'title'=>ucfirst($kind),'calculation'=>$calculation,'value'=>$value,'due_date'=>'','milestone_id'=>$kind==='milestone'?identity($id+1000):''];}
function sample(int $total=10001):array{return ['format'=>1,'id'=>identity(1),'client_id'=>11,'project_id'=>22,'proposal'=>['id'=>33,'version'=>4,'content_sha256'=>str_repeat('a',64)],'currency'=>'USD','title'=>'Website for Acadian shop','terms'=>"Exact financial terms.\r\nNo recurring purchase.",'items'=>[['id'=>identity(10),'description'=>'Design and build','quantity'=>1,'unit_cents'=>$total,'discount_cents'=>0,'tax_cents'=>0]],'stages'=>[stage(20,'full','remainder',0)]];}
function change(array $source,array $path,mixed $value):array{$cursor=&$source;foreach($path as $key)$cursor=&$cursor[$key];$cursor=$value;return $source;}

try {
    $data=sample();$full=Schedule::fromArray($data);
    verify('Design-only full schedule assigns exact10001 cents',$full->quote()['total_cents']===10001&&$full->stages()[0]['amount_cents']===10001&&$full->stages()[0]['remaining_scheduled_cents']===0);
    verify('Exact ordered input and literal terms survive rehydration',$full->data()===$data&&Schedule::fromArray($full->data())->json()===$full->json());
    verify('Fingerprint is the exact retained financial snapshot',hash('sha256',$full->json())===$full->fingerprint());
    $split=sample();$split['stages']=[stage(21,'deposit','fixed',2500),stage(22,'milestone','percent',3333),stage(23,'final','remainder',0)];$planned=Schedule::fromArray($split);
    verify('Fixed deposit and quote-based percentage leave exact final amount',array_column($planned->stages(),'amount_cents')===[2500,3333,4168]);
    verify('Remaining scheduled values are allocations not payment observations',array_column($planned->stages(),'remaining_scheduled_cents')===[7501,4168,0]&&!array_key_exists('paid_cents',$planned->quote()));
    verify('Exact stage lookup returns the retained allocation',$planned->stage(identity(22))===$planned->stages()[1]);
    denied('Unknown stage cannot project another obligation',fn()=>$planned->stage(identity(999)));
    $half=sample(101);$half['stages']=[stage(21,'deposit','percent',5000),stage(22,'final','remainder',0)];
    verify('Half-cent deposit rounds up and final conserves cents',array_column(Schedule::fromArray($half)->stages(),'amount_cents')===[51,50]);
    $thirds=sample(100);$thirds['stages']=[stage(21,'deposit','percent',3333),stage(22,'milestone','percent',3333),stage(23,'final','remainder',0)];
    verify('Two thirds installments never lose the rounding remainder',array_column(Schedule::fromArray($thirds)->stages(),'amount_cents')===[33,33,34]);
    $priced=sample();$priced['items']=[['id'=>identity(10),'description'=>'Design','quantity'=>3,'unit_cents'=>12500,'discount_cents'=>2500,'tax_cents'=>2800],['id'=>identity(11),'description'=>'Content','quantity'=>2,'unit_cents'=>1000,'discount_cents'=>0,'tax_cents'=>0]];
    $quote=Schedule::fromArray($priced)->quote();
    verify('Item quantity approved discount and tax are itemized exactly',[$quote['subtotal_cents'],$quote['discount_cents'],$quote['tax_cents'],$quote['total_cents']] === [39500,2500,2800,39800]);
    verify('Quoted lines retain their own computed subtotals and totals',array_column($quote['items'],'total_cents')===[37800,2000]&&$quote['items'][0]['subtotal_cents']===37500);
    verify('Largest supported invoice total remains exact',Schedule::fromArray(sample(Schedule::MAX_CENTS))->stages()[0]['amount_cents']===99999999);
    foreach([1,2,3,99,100,101,10001,99999999] as $total){
        foreach([1,2500,3333,5000,9999,10000] as $bps){
            $s=sample($total);$s['stages']=[stage(21,'deposit','percent',$bps),stage(22,'final','remainder',0)];
            // Independent direct-product oracle, on the CI's64-bit runtimes.
            $expected=intdiv($total*$bps+5000,10000);$name='Rounding and conservation total'.$total.' basis-points'.$bps;
            if($expected<1||$expected>=$total){denied($name,fn()=>Schedule::fromArray($s));}
            else{$amounts=array_column(Schedule::fromArray($s)->stages(),'amount_cents');verify($name,$amounts===[$expected,$total-$expected]&&array_sum($amounts)===$total);}
        }
    }
    $copy=$planned->data();$copy['items'][0]['unit_cents']=1;$projection=$planned->stages();$projection[0]['amount_cents']=1;$quoteCopy=$planned->quote();$quoteCopy['items'][0]['total_cents']=1;
    verify('Mutating returned nested arrays cannot rewrite the saved snapshot',$planned->data()===$split&&$planned->stages()[0]['amount_cents']===2500&&$planned->quote()['items'][0]['total_cents']===10001);
    foreach([
        [['client_id'],12],[['project_id'],23],[['proposal','id'],34],[['proposal','version'],5],[['proposal','content_sha256'],str_repeat('b',64)],
        [['title'],'Changed financial offer'],[['terms'],'Different terms'],[['items',0,'unit_cents'],10002],[['items',0,'description'],'Changed scope'],
        [['stages',0,'title'],'Different installment'],[['stages',0,'due_date'],'2028-02-29'],[['stages',0,'value'],2600],
        [['stages',1,'milestone_id'],identity(444)],
    ] as $n=>[$path,$value]){
        verify('Changing accepted financial binding or terms changes fingerprint'.$n,Schedule::fromArray(change($split,$path,$value))->fingerprint()!==$planned->fingerprint());
    }
    $unicode=sample();$unicode['title']='Café <script> & Bayou';$unicode['terms']="Literal <b>terms</b>\r\nÉquipe\tclient";
    verify('Unicode markup and line endings stay literal financial facts',Schedule::fromArray($unicode)->data()===$unicode);
    $leap=change($data,['stages',0,'due_date'],'2028-02-29');verify('Real leap-day due date is retained',Schedule::fromArray($leap)->stages()[0]['due_date']==='2028-02-29');
    foreach(['2027-02-29','2028-02-30','2028-13-01','2028-00-10','0000-01-01','2028-1-01','2028-01-01T00:00:00Z',1,null,[],false] as $n=>$date)denied('Invalid due date refused'.$n,fn()=>Schedule::fromArray(change($data,['stages',0,'due_date'],$date)));
    foreach([
        [['format'],'1'],[['format'],2],[['currency'],'usd'],[['currency'],'EUR'],[['currency'],null],
        [['id'],str_repeat('A',32)],[['id'],str_repeat('a',31)],[['id'],str_repeat('a',32)."\n"],
        [['client_id'],0],[['client_id'],'11'],[['client_id'],true],[['client_id'],2147483648],[['project_id'],-1],
        [['proposal','id'],0],[['proposal','version'],1.0],[['proposal','content_sha256'],str_repeat('A',64)],
        [['title'],' '],[['title'],str_repeat('x',161)],[['title'],"bad\nline"],[['title'],"\xff"],[['title'],[]],
        [['terms'],''],[['terms'],str_repeat('x',30001)],[['terms'],"bad\0terms"],[['terms'],new stdClass()],
        [['items'],[]],[['items'],'bad'],[['items'],[2=>$data['items'][0]]],[['items',0],null],
        [['items',0,'description'],''],[['items',0,'description'],str_repeat('x',2001)],[['items',0,'description'],"\xff"],
        [['items',0,'quantity'],0],[['items',0,'quantity'],101],[['items',0,'quantity'],'2'],[['items',0,'quantity'],2.0],
        [['items',0,'unit_cents'],0],[['items',0,'unit_cents'],100000000],[['items',0,'unit_cents'],'100'],[['items',0,'unit_cents'],true],
        [['items',0,'discount_cents'],-1],[['items',0,'discount_cents'],10002],[['items',0,'discount_cents'],10001],
        [['items',0,'tax_cents'],-1],[['items',0,'tax_cents'],100000000],[['items',0,'tax_cents'],0.0],
        [['stages'],[]],[['stages'],[3=>$data['stages'][0]]],[['stages',0],new stdClass()],
        [['stages',0,'kind'],'final'],[['stages',0,'kind'],[]],[['stages',0,'calculation'],'fixed'],[['stages',0,'value'],'0'],
        [['stages',0,'title'],"\t"],[['stages',0,'title'],str_repeat('x',161)],[['stages',0,'milestone_id'],identity(99)],
    ] as $n=>[$path,$value])denied('Invalid record field refused'.$n,fn()=>Schedule::fromArray(change($data,$path,$value)));
    foreach([
        [['stages',0,'kind'],'full'],[['stages',0,'calculation'],'remainder'],[['stages',0,'value'],0],
        [['stages',0,'value'],10001],[['stages',0,'value'],'2500'],[['stages',0,'value'],INF],
        [['stages',1,'kind'],'deposit'],[['stages',1,'milestone_id'],''],[['stages',1,'milestone_id'],str_repeat('z',32)],
        [['stages',1,'calculation'],'unknown'],[['stages',1,'value'],10001],[['stages',1,'value'],10000],
        [['stages',2,'kind'],'milestone'],[['stages',2,'calculation'],'percent'],[['stages',2,'value'],1],
    ] as $n=>[$path,$value])denied('Invalid split allocation refused'.$n,fn()=>Schedule::fromArray(change($split,$path,$value)));
    foreach([['extra'=>'field']+$data,array_reverse($data,true),array_diff_key($data,['title'=>true])] as $n=>$bad)denied('Top-level field shape refused'.$n,fn()=>Schedule::fromArray($bad));
    foreach([['items',0],['stages',0],['proposal']] as $n=>$path){$bad=$data;$node=$bad;foreach($path as $key)$node=$node[$key];$node['hidden']='unknown';denied('Nested extra field refused'.$n,fn()=>Schedule::fromArray(change($bad,$path,$node)));}
    $duplicate=$data;$duplicate['items'][]=$duplicate['items'][0];denied('Duplicate item identity refused',fn()=>Schedule::fromArray($duplicate));
    $duplicate=$split;$duplicate['stages'][2]['id']=$duplicate['stages'][0]['id'];denied('Duplicate stage identity refused',fn()=>Schedule::fromArray($duplicate));
    $overflow=sample(99999999);$overflow['items'][0]['quantity']=2;denied('Quantity product over supported limit refused before multiplication',fn()=>Schedule::fromArray($overflow));
    $overflow=sample(99999999);$overflow['items'][0]['tax_cents']=1;denied('Tax overflow refused before addition',fn()=>Schedule::fromArray($overflow));
    $overflow=sample(99999999);$overflow['items'][]=change(sample(1)['items'][0],['id'],identity(11));denied('Aggregate quote overflow refused before addition',fn()=>Schedule::fromArray($overflow));
    $many=sample(1);$many['items']=[];for($n=0;$n<50;$n++)$many['items'][]=change(sample(1)['items'][0],['id'],identity(100+$n));
    verify('Fifty quoted items remain supported',Schedule::fromArray($many)->quote()['total_cents']===50);
    $many['items'][]=change(sample(1)['items'][0],['id'],identity(999));denied('Fifty-first quoted item refused',fn()=>Schedule::fromArray($many));
    $many=sample();$many['stages']=[];for($n=0;$n<31;$n++)$many['stages'][]=stage(200+$n,'milestone','fixed',1);$many['stages'][]=stage(300,'final','remainder',0);
    verify('Thirty-two installments preserve exact remainder',Schedule::fromArray($many)->stages()[31]['amount_cents']===9970);
    array_splice($many['stages'],0,0,[stage(199,'deposit','fixed',1)]);denied('Thirty-third installment refused',fn()=>Schedule::fromArray($many));
    $large=sample();$large['items']=[];for($n=0;$n<50;$n++){$item=sample(1)['items'][0];$item['id']=identity(100+$n);$item['description']=str_repeat('x',2000);$large['items'][]=$item;}$large['terms']=str_repeat('x',30000);
    denied('Combined snapshot byte budget is enforced',fn()=>Schedule::fromArray($large));
    $large['terms']='Exact short terms';verify('Large bounded record round-trips without truncation',Schedule::fromArray($large)->data()===$large);
}catch(Throwable $error){$checks[]=['name'=>'Unexpected fixture failure: '.get_class($error).': '.$error->getMessage(),'passed'=>false];}
$passed=!in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'scope'=>'Pure immutable quote and payment allocation records only; no native authority, storage, invoice issuance, browser or gateway acceptance.','php'=>PHP_VERSION,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
