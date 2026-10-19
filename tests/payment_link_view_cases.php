<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')||PHP_SAPI!=='cli'){exit(2);}

/** Actual disposable SQL and renderer checks, included in the existing native payment report. */
function pay_link_view_cases(array $request):void {
    if(config()['db']['prefix']!=='payci_')throw new LogicException('Unexpected payment-link fixture scope.');
    $invoice=one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$request['invoice_id']]);
    $job=one('SELECT * FROM '.table('studio_jobs').' WHERE id=?',[$invoice['job_id']]);
    $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$job['client_id']]);
    $state=function():array{$out=[];foreach(['studio_invoices','studio_jobs','studio_clients','payment_requests','audit'] as $name)$out[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');return $out;};
    $render=function(array $view):string{ob_start();payments_link_card($view);return ob_get_clean();};
    $before=$state();$valid=payments_link_projection($request);$html=$render($valid);
    pay_ok('Current bearer projection renders exactly the validated invoice fields',$valid['state']==='prepared'&&$valid['details']['reference']===$invoice['reference']&&$valid['details']['title']===$invoice['title']&&$valid['details']['instructions']===$invoice['instructions']&&str_contains($html,esc($invoice['reference']))&&str_contains($html,'USD due')&&$state()===$before);
    foreach(['revoked','superseded','expired','unrecognized'] as $kind){
        $copy=$request;if($kind==='expired')$copy['expires_at']=1;else $copy['status']=$kind;
        $view=payments_link_projection($copy);$body=$render($view);
        pay_ok('Terminal '.$kind.' projection exposes neither invoice fields nor an amount due',array_keys($view)===['state']&&$view['state']===($kind==='unrecognized'?'unavailable':$kind)&&!str_contains($body,esc($invoice['reference']))&&!str_contains($body,esc($invoice['title']))&&!str_contains($body,'USD due')&&$state()===$before);
    }
    foreach(['reference'=>'PRIVATE-REPLACED-REFERENCE','title'=>'PRIVATE-REPLACED-TITLE','instructions'=>'PRIVATE-REPLACED-INSTRUCTIONS','amount_cents'=>(int)$invoice['amount_cents']+100,'currency'=>'EUR','status'=>'paid'] as $column=>$value){
        query('UPDATE '.table('studio_invoices').' SET '.$column.'=? WHERE id=?',[$value,$invoice['id']]);
        try{
            $changed=$state();$view=payments_link_projection($request);$body=$render($view);
            pay_ok('Changed invoice '.$column.' never exposes current metadata or stale due',array_keys($view)===['state']&&$view['state']!== 'prepared'&&!str_contains($body,'PRIVATE-REPLACED')&&!str_contains($body,esc($invoice['reference']))&&!str_contains($body,'USD due')&&$state()===$changed);
            if($column==='status')pay_ok('Paid link says no further payment without displaying the previous charge',$view['state']==='paid'&&str_contains($body,'No further payment is due.')&&!str_contains($body,'$'));
        }finally{query('UPDATE '.table('studio_invoices').' SET '.$column.'=? WHERE id=?',[$invoice[$column],$invoice['id']]);}
    }
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=?',[$client['id']]);
    try{pay_ok('Inactive original client loses bearer invoice projection',payments_link_projection($request)===['state'=>'unavailable']);}
    finally{query('UPDATE '.table('studio_clients').' SET active=? WHERE id=?',[$client['active'],$client['id']]);}
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Other link fixture client','other-pay-link@example.invalid',str_repeat('c',32),gmdate('Y-m-d H:i:s')]);$other=(int)db()->lastInsertId();
    try{
        query('UPDATE '.table('studio_jobs').' SET client_id=? WHERE id=?',[$other,$job['id']]);
        pay_ok('Moving the project to another client invalidates its old bearer view',payments_link_projection($request)===['state'=>'unavailable']);
        query('UPDATE '.table('studio_invoices')." SET status='paid' WHERE id=?",[$invoice['id']]);
        pay_ok('A moved invoice does not reveal the new client paid state',payments_link_projection($request)===['state'=>'unavailable']);
    }finally{
        query('UPDATE '.table('studio_jobs').' SET client_id=? WHERE id=?',[$job['client_id'],$job['id']]);
        query('UPDATE '.table('studio_invoices').' SET status=? WHERE id=?',[$invoice['status'],$invoice['id']]);
        query('DELETE FROM '.table('studio_clients').' WHERE id=?',[$other]);
    }
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$client['id'],'Other link fixture project','','',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);$otherJob=(int)db()->lastInsertId();
    try{
        query('UPDATE '.table('studio_invoices').' SET job_id=? WHERE id=?',[$otherJob,$invoice['id']]);
        pay_ok('Changing the invoice project within the same client invalidates the old link',payments_link_projection($request)===['state'=>'unavailable']);
    }finally{
        query('UPDATE '.table('studio_invoices').' SET job_id=? WHERE id=?',[$invoice['job_id'],$invoice['id']]);
        query('DELETE FROM '.table('studio_jobs').' WHERE id=?',[$otherJob]);
    }
    pay_ok('All synthetic changes restore exact original invoice client request and audit rows',$state()===$before&&payments_link_projection($request)===$valid);
}
