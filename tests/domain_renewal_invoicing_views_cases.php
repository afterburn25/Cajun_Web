<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

/** Actual view with modeled, already-authorized projections; no SQL or provider work. */
function domain_renewal_invoicing_view_cases(callable $check):void {
    $hash=str_repeat('a',64);$otherHash=str_repeat('b',64);$request=str_repeat('1',32);
    $context=[
        'prepared'=>true,'request'=>['request'=>['id'=>$request,'domain'=>'renew.example']],
        'offer'=>['binding_sha256'=>$hash,'agreement_sha256'=>$otherHash,'domain'=>'renew.example','years'=>2,'renew_privacy'=>false,'price_minor'=>2499],
        'create_invoice'=>[],'projects'=>[['id'=>2,'title'=>'Client <script>project</script> & website','sha256'=>str_repeat('c',64)]],
        'project_page'=>1,'projects_has_more'=>false,'page'=>1,'allocations'=>[],'invoices'=>[],'message'=>null,
    ];
    $render=static function(array $value,array $input=[]):string {ob_start();try{domain_renewal_dispatch_creation_view($value,$input);return (string)ob_get_contents();}finally{ob_end_clean();}};
    $html=$render($context);
    $check('Renewal invoicing view uses exact accepted fee term and privacy',str_contains($html,'USD 24.99')&&str_contains($html,'renew.example, 2 year(s), without privacy renewal'));
    $check('Renewal invoicing project label escapes stored markup',str_contains($html,'Client &lt;script&gt;project&lt;/script&gt; &amp; website')&&!str_contains($html,'<script>'));
    $check('Renewal invoicing project choice binds ID and reviewed hash',str_contains($html,'value="2:'.str_repeat('c',64).'"'));
    $check('Renewal invoicing starts with no silently selected project',str_contains($html,'<option value="">Choose a project</option>')&&!str_contains($html,' selected'));
    $check('Renewal invoicing captures original request and agreed fingerprints',str_contains($html,'name="request_id" value="'.$request.'"')&&str_contains($html,'name="binding_sha256" value="'.$hash.'"')&&str_contains($html,'name="agreement_sha256" value="'.$otherHash.'"'));
    $check('Renewal invoicing requires explicit creation confirmation',str_contains($html,'name="create_invoice_confirmed" value="1" required'));
    $check('Renewal invoicing password stays empty and is required',str_contains($html,'type="password" name="owner_password" autocomplete="current-password" required')&&!preg_match('/name="owner_password"[^>]*value=/',$html));
    $check('Renewal invoicing exposes no editable price or currency',!preg_match('/<(?:input|select)[^>]+name="(?:price|amount|currency|years|renew_privacy)"/',$html));
    $check('Renewal invoicing first page has no unnecessary pagination',!str_contains($html,'Renewal invoice project pages'));
    $posted=['action'=>'provider_renewal_dispatch_create_invoice','allocation_id'=>str_repeat('3',32),'request_id'=>$request,'binding_sha256'=>$hash,'agreement_sha256'=>$otherHash,'project_choice'=>'2:'.str_repeat('c',64),'owner_password'=>'SYNTHETIC_PASSWORD_DO_NOT_RENDER'];
    $retry=$render($context,$posted);
    $check('Renewal invoicing retry retains its captured authority fingerprints',str_contains($retry,'name="binding_sha256" value="'.$hash.'"')&&str_contains($retry,'name="agreement_sha256" value="'.$otherHash.'"'));
    $check('Renewal invoicing retry retains exact request and selected project',str_contains($retry,'name="allocation_id" value="'.str_repeat('3',32).'"')&&str_contains($retry,'value="2:'.str_repeat('c',64).'" selected'));
    $check('Renewal invoicing retry never echoes submitted password',!str_contains($retry,'SYNTHETIC_PASSWORD_DO_NOT_RENDER'));
    foreach(['request_id','binding_sha256','agreement_sha256'] as $field){$input=$posted;$input[$field]=str_repeat('f',strlen($input[$field]));$retry=$render($context,$input);$check('Renewal invoicing stale '.$field.' cannot pair fresh terms with old authority',str_contains($retry,'Reload this page and review')&&!str_contains($retry,'<form'));}
    foreach(['missing'=>null,'array'=>[],'other project'=>'3:'.str_repeat('c',64),'changed project'=>'2:'.str_repeat('f',64),'invalid encoding'=>"2:\xff"] as $name=>$choice){
        $input=$posted;if($choice===null)unset($input['project_choice']);else $input['project_choice']=$choice;$retry=$render($context,$input);
        $check('Renewal invoicing retry refuses refreshed selection for '.$name,str_contains($retry,'Reload this page and review')&&!str_contains($retry,'<form'));
    }
    $closed=$context;$closed['create_invoice']=null;
    $check('Renewal invoicing hides action when no eligible unallocated terms exist',$render($closed)==='');
    $check('Renewal invoicing closed eligibility cannot render captured action',str_contains($render($closed,$posted),'Reload this page and review')&&!str_contains($render($closed,$posted),'<form'));
    $empty=$context;$empty['projects']=[];
    $check('Renewal invoicing empty project page offers no creation form',str_contains($render($empty),'No client projects are shown')&&!str_contains($render($empty),'<form'));
    $paged=$context;$paged['project_page']=2;$paged['page']=3;$paged['projects_has_more']=true;$html=$render($paged);
    $check('Renewal invoicing project paging keeps separate invoice context',str_contains($html,'Newer projects')&&str_contains($html,'Older projects')&&str_contains($html,'project_page=1')&&str_contains($html,'project_page=3')&&str_contains($html,'invoice_page=3'));
    $private=$context;$private['offer']['renew_privacy']=true;
    $check('Renewal invoicing renders the accepted positive privacy choice',str_contains($render($private),'including privacy renewal')&&!str_contains($render($private),'without privacy renewal'));
}
