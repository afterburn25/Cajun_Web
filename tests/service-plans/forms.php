<?php
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../../site');require CWP_CODE_ROOT.'/app/plans/forms.php';require CWP_CODE_ROOT.'/includes/security/input.php';require CWP_CODE_ROOT.'/includes/layout/plans.php';
$checks=[];
function sf_ok(string $name,bool $pass):void {global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function sf_deny(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException){sf_ok($name,true);return;}sf_ok($name,false);}
$form=['name'=>'Website care café','cadence'=>'monthly','price'=>'129.00','inclusions'=>"Reviewed updates\r\nMonthly report",'exclusions'=>'New features','limits'=>"updates = 4\nreports = 1",'support'=>'Response within two business days'];
try {
    $terms=service_plans_form_terms($form);sf_ok('Human form preserves exact price UTF-8 and multiline terms',$terms['price']===12900&&$terms['name']===$form['name']&&$terms['inclusions']===['Reviewed updates','Monthly report']&&$terms['limits']===['reports'=>1,'updates'=>4]);
    foreach(['one-time','monthly','quarterly','annually'] as $cadence)sf_ok('Supported cadence '.$cadence.' parses unchanged',service_plans_form_terms(array_replace($form,['cadence'=>$cadence]))['cadence']===$cadence);
    foreach(['0'=>0,'0.01'=>1,'2.5'=>250,'999999.99'=>99999999] as $price=>$cents)sf_ok('Price '.(string)$price.' uses existing exact cents parser',service_plans_form_terms(array_replace($form,['price'=>(string)$price]))['price']===$cents);
    foreach(['','00','+1','-1','1e2','1,000.00','$10','0.001','1000000','1.00x'] as $price)sf_deny('Invalid price '.json_encode($price).' cannot change amount',fn()=>service_plans_form_terms(array_replace($form,['price'=>$price])));
    foreach(array_keys($form) as $field)sf_deny('Scalar field '.$field.' refuses array input',fn()=>service_plans_form_terms(array_replace($form,[$field=>['value']])));
    foreach(['currency'=>'EUR','cadence'=>'monthly ' ,'name'=>str_repeat('x',121),'inclusions'=>implode("\n",array_fill(0,33,'term')),'exclusions'=>"A\n\nB",'support'=>'Unlimited updates'] as $field=>$value)sf_deny('Terms validation refuses malformed '.$field,fn()=>service_plans_form_terms(array_replace($form,[$field=>$value])));
    foreach(["updates = 4\nupdates = 5",'Updates = 4','updates = -1','updates = 2.5','updates = 02','updates = 2147483648','updates = 1e5',"updates = 4\n"] as $value) {
        if(str_ends_with($value,"\n")){sf_ok('Trailing textarea newline does not create another term',service_plans_form_terms(array_replace($form,['limits'=>$value]))['limits']===['updates'=>4]);continue;}
        sf_deny('Malformed or duplicate usage limit '.json_encode($value).' is refused',fn()=>service_plans_form_terms(array_replace($form,['limits'=>$value])));
    }
    $intent=['record_id'=>str_repeat('a',32),'revision'=>'0','event_id'=>str_repeat('b',32)];sf_ok('Draft revision zero and stable request identities are retained',service_plans_form_intent($intent)===array_replace($intent,['revision'=>0]));
    foreach(['00','01','-1','+1','1.0','1e1','256',"1\n",str_repeat('9',40)] as $v)sf_deny('Malformed or unavailable revision '.json_encode($v).' is refused',fn()=>service_plans_form_intent(array_replace($intent,['revision'=>$v])));
    foreach([str_repeat('A',32),str_repeat('a',31),str_repeat('a',32)."\n",['id']] as $v)sf_deny('Record identity remains exact '.json_encode($v),fn()=>service_plans_form_intent(array_replace($intent,['record_id'=>$v])));
    foreach([null,0,1,true,'0','yes',['1']] as $v)sf_deny('Agreement requires the exact submitted confirmation '.json_encode($v),fn()=>service_plans_form_confirm(['confirm'=>$v]));
    service_plans_form_confirm(['confirm'=>'1']);sf_ok('Explicit unchecked-form confirmation may proceed to authority checks',true);
    sf_ok('Exact lower-case terms hash is preserved',service_plans_form_hash(['terms_hash'=>str_repeat('e',64)],'terms_hash')===str_repeat('e',64));
    sf_deny('Terms hash suffix is never trimmed into a valid agreement',fn()=>service_plans_form_hash(['terms_hash'=>str_repeat('e',64)."\n"],'terms_hash'));
    $posted=array_merge($form,$intent,['name'=>'</textarea><script>marker</script>','current_password'=>'SECRET','confirm'=>'1','action'=>'arbitrary','revision'=>'4']);$saved=service_plans_form_values($posted);
    sf_ok('Failed form retains old intent revision and literal unsaved text',$saved['record_id']===$intent['record_id']&&$saved['event_id']===$intent['event_id']&&$saved['revision']==='4'&&$saved['name']===$posted['name']);
    sf_ok('Redisplay excludes password confirmation and action fields',!array_intersect(['current_password','confirm','action'],array_keys($saved))&&!str_contains(json_encode($saved),'SECRET'));
    sf_deny('Invalid UTF-8 in human input is refused',fn()=>service_plans_form_terms(array_replace($form,['name'=>"bad\xff"])));
    sf_deny('NUL in human input is refused',fn()=>service_plans_form_terms(array_replace($form,['name'=>"bad\0"])));
    sf_ok('Malformed raw redisplay input cannot become an arbitrary array or huge field',service_plans_form_values(['name'=>['x'],'support'=>str_repeat('x',1001)])['name']===''&&service_plans_form_values(['support'=>str_repeat('x',1001)])['support']==='');
    ob_start();service_plans_terms_fields($saved);$html=ob_get_clean();sf_ok('Unsaved form text is escaped and never closes its field',str_contains($html,'&lt;/textarea&gt;&lt;script&gt;marker&lt;/script&gt;')&&!str_contains($html,'<script>marker')&&!str_contains($html,'SECRET'));
    ob_start();service_plans_confirmation('I agree to <these> terms',true);$html=ob_get_clean();sf_ok('Owner confirmation is unchecked and current password is never populated',!str_contains($html,' checked')&&str_contains($html,'type="checkbox"')&&str_contains($html,'type="password"')&&!str_contains($html,'name="current_password" value=')&&str_contains($html,'&lt;these&gt;'));
    ob_start();service_plans_intent_fields('service_plans_change',$saved,'<input name="csrf" value="synthetic">');$html=ob_get_clean();sf_ok('Rendered intent retains old failed request revision and identity',str_contains($html,'name="revision" value="4"')&&str_contains($html,'name="event_id" value="'.str_repeat('b',32).'"')&&str_contains($html,'name="csrf"'));
    $plan=ServicePlanDocument::create(str_repeat('c',32),1,100,array_replace($terms,['name'=>'<script>plan</script>','support'=>'Literal <b>support</b>']))->append(str_repeat('d',32),'activate',1,101,[]);
    ob_start();service_plans_terms_view($plan);$html=ob_get_clean();sf_ok('Terms display escapes model text and preserves reviewed USD price',str_contains($html,'&lt;script&gt;plan&lt;/script&gt;')&&str_contains($html,'&lt;b&gt;support&lt;/b&gt;')&&!str_contains($html,'<script>')&&str_contains($html,'$129.00 USD'));
    $offer=ServiceSubscriptionDocument::create(str_repeat('e',32),7,1,102,$plan);sf_ok('Pending unagreed offer has an honest client label',service_plans_agreement_label($offer)==='Awaiting your agreement');
    $offer=$offer->append(str_repeat('f',32),'consent','client:7',103,['terms_hash'=>$plan->model()->termsHash()]);sf_ok('Accepted offer does not falsely display active service',service_plans_agreement_label($offer)==='Agreed · awaiting studio activation');
    $offer=$offer->append(str_repeat('1',32),'activate','staff:1',104,[])->append(str_repeat('2',32),'cancel','client:7',105,['reason'=>'Literal <script>end</script>']);
    ob_start();service_plans_agreement_history($offer);$html=ob_get_clean();sf_ok('Agreement history attributes actions escapes reasons and distinguishes payment consent',str_contains($html,'Client')&&str_contains($html,'Studio')&&str_contains($html,'&lt;script&gt;end&lt;/script&gt;')&&!str_contains($html,'<script>')&&str_contains($html,'does not authorize automatic payments'));
    sf_ok('Ended agreement label remains terminal',service_plans_agreement_label($offer)==='Ended agreement');
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e."\n");}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Pure service-plan form parsing and escaped template functions; no current authority, CSRF enforcement, HTTP, database or payment acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";exit($passed?0:1);
