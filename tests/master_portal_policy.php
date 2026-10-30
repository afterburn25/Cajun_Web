<?php
/** Pure routing/query policies. No database or network. */
declare(strict_types=1);
define('CWP_BOOT',true);
function site_url(string $path=''): string {return 'https://fixture.invalid/sub/'.$path;}
function admin_url(string $view='dashboard',array $q=[]): string {return site_url('admin/index.php').'?'.http_build_query(['view'=>$view]+$q);}
function staff_home(array $u): string {return 'dashboard';}
function table(string $name): string {return '`fixture_'.$name.'`';}
require __DIR__.'/../site/app/clients/master_portal.php';
$checks=[];
function check(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function denied(string $name,callable $f): void {try{$f();}catch(InvalidArgumentException){check($name,true);return;}check($name,false);}
try {
    check('Signed-in administrator bare portal goes to hub',master_portal_entry([],'GET',true)==='redirect');
    check('Signed-out bare portal remains customer login',master_portal_entry([],'GET',false)==='none');
    check('Explicit master path is available before authentication',master_portal_entry(['master'=>'1'],'GET',false)==='hub');
    check('Master route refuses POST',master_portal_entry(['master'=>'1'],'POST',true)==='method');
    check('Master route refuses array marker',master_portal_entry(['master'=>['1']],'GET',true)==='invalid');
    foreach(['invite','support','invoice','hosting','hosting_purchases','downloads','agreements','license','finance'] as $key){
        check('Deep link '.$key.' is not hijacked',master_portal_entry([$key=>'1'],'GET',true)==='none');
        check('Mixed master/'.$key.' route rejected',master_portal_entry(['master'=>'1',$key=>'1'],'GET',true)==='invalid');
    }
    $_GET=['portal'=>'master','return'=>'https://evil.invalid'];
    check('Administrator login return is fixed internal master URL',master_portal_login_destination([])==='https://fixture.invalid/sub/portal/index.php?master=1');
    $_GET=['portal'=>'https://evil.invalid'];check('Arbitrary return target not accepted',master_portal_login_destination([])==='https://fixture.invalid/sub/admin/index.php?view=dashboard');
    $_GET=['portal'=>['master']];check('Array return target ignored',master_portal_login_query()===[]);
    check('Query cannot override master marker',master_portal_url(['master'=>'0'])==='https://fixture.invalid/sub/portal/index.php?master=1');
    $f=master_portal_filters(['q'=>'  100%_Studio=  ','portal_page'=>'2']);
    check('Search trimmed and page normalized',$f===['q'=>'100%_Studio=','page'=>2]);
    [$sql,$params]=master_portal_client_query(false,$f);
    check('Staff rows require explicit policy and active account',str_contains($sql,'p.allowed=1')&&str_contains($sql,'c.active=1'));
    check('Search values not interpolated',!str_contains($sql,'Studio')&&substr_count($sql,'?')===2);
    check('Wildcard characters are literal',$params===['%100=%=_Studio==%','%100=%=_Studio==%']);
    check('Pagination bounded to 51 rows and computed offset',str_ends_with($sql,'LIMIT 51 OFFSET 50'));
    [$sql,$params]=master_portal_client_query(true,master_portal_filters([]));
    check('Owner sees active clients without staff opt-in condition',str_contains($sql,'c.active=1')&&!str_contains($sql,'support_clients')&&$params===[]);
    foreach([['q'=>[]],['q'=>str_repeat('a',121)],['q'=>"bad\0value"],['q'=>"\xff"],['portal_page'=>'0'],['portal_page'=>'-1'],['portal_page'=>[]],['portal_page'=>'1 OR 1=1'],['portal_page'=>'100000']] as $i=>$input)denied('Malformed filter '.$i.' rejected',fn()=>master_portal_filters($input));
}catch(Throwable $e){$failure=$e->getMessage();}
@mkdir(__DIR__.'/../qa',0775,true);
file_put_contents(__DIR__.'/../qa/master-portal-policy-results.json',json_encode(['passed'=>!isset($failure),'scope'=>'Pure route/search policy only; no SQL or installed HTTP','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT));
echo json_encode(['passed'=>!isset($failure),'checks'=>count($checks),'failure'=>$failure??null])."\n";exit(isset($failure)?1:0);
