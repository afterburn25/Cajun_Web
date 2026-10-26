<?php
/** Financed website license enforcement bound to retained recurring-billing obligations. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/licenses/financing_policy.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
require_once CWP_CODE_ROOT.'/database/migrations/license_financing_migrate.php';

function license_financing_table_present():bool{
    return (bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
        [config()['db']['prefix'].'license_financing']);
}
function license_financing_ready():bool{
    return setting('license_financing_schema_version')==='1'&&license_financing_table_present();
}
/** Null means the feature has never been enabled. Any partial established state is an error. */
function license_financing_feature_state():?bool{
    $version=(string)(setting('license_financing_schema_version')??'');
    $present=license_financing_table_present();
    if($version===''&&!$present)return null;
    if($version!=='1'||!$present)throw new RuntimeException('Financed-license storage is inconsistent. Restore the original database.');
    return true;
}
function license_financing_plan(int $licenseId):?array{
    if($licenseId<1)throw new InvalidArgumentException('License not found.');
    if(license_financing_feature_state()===null)return null;
    $row=one('SELECT * FROM '.table('license_financing').' WHERE license_id=?',[$licenseId]);
    return $row?:null;
}
function license_financing_terms_hash(RecurringBillingSchedule $doc):string{
    $data=$doc->data();
    if(!is_array($data['terms']??null))throw new RuntimeException('Financing schedule terms are missing.');
    return hash('sha256',recurring_billing_json($data['terms']));
}
function license_financing_schedule(array $plan):array{
    $row=recurring_billing_row((string)$plan['schedule_id']);
    $doc=recurring_billing_validate($row);
    if((int)$row['client_id']!==(int)$plan['client_id']){
        throw new RuntimeException('Financing schedule ownership changed.');
    }
    if(!hash_equals((string)$plan['terms_sha256'],license_financing_terms_hash($doc))){
        throw new RuntimeException('Financing terms differ from the retained agreement.');
    }
    return [$row,$doc];
}
function license_financing_rights_from_row(array $plan):array{
    return license_financing_rights(
        (int)$plan['allow_resale']===1,(int)$plan['allow_distribution']===1,
        (int)$plan['exclusive_resale']===1,(int)$plan['exclusive_distribution']===1
    );
}
function license_financing_rights_label(array $plan):string{
    $rights=license_financing_rights_from_row($plan);$parts=[];
    if(!$rights['resale']&&!$rights['distribution'])return 'Site-use only — resale and distribution not granted';
    if($rights['resale'])$parts[]=$rights['exclusive_resale']?'Exclusive resale':'Resale';
    if($rights['distribution'])$parts[]=$rights['exclusive_distribution']?'Exclusive distribution':'Distribution';
    return implode(' + ',$parts).' rights granted';
}

/**
 * Bind one existing website license to a finite, already-agreed recurring schedule.
 * The recurring schedule remains the source of price/cadence truth; this table only
 * controls payment-dependent license access and commercial-rights scope.
 */
function license_financing_bind(int $licenseId,string $scheduleId,int $installments,int $graceDays,array $rights,
    #[\SensitiveParameter]string $password):array{
    if($installments<1||$installments>360||$graceDays<0||$graceDays>90){
        throw new InvalidArgumentException('Financing requires 1–360 installments and a 0–90 day grace period.');
    }
    $normalized=license_financing_rights(
        (bool)($rights['resale']??false),(bool)($rights['distribution']??false),
        (bool)($rights['exclusive_resale']??false),(bool)($rights['exclusive_distribution']??false)
    );
    if(license_financing_feature_state()!==true)throw new RuntimeException('Enable financed-site licensing first.');
    if(!preg_match('/^[a-f0-9]{32}$/D',$scheduleId))throw new InvalidArgumentException('Choose a valid recurring billing schedule.');
    if(db()->inTransaction())throw new LogicException('Financing binding owns its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($licenseId,$scheduleId,$installments,$graceDays,$normalized,$password):array{
        if(support_current())throw new RuntimeException('Leave support mode before changing financing.');
        $actor=staff_owner($password);recurring_billing_require();
        $license=one('SELECT l.*,c.active AS customer_active FROM '.table('website_licenses').' l JOIN '.table('studio_clients').' c ON c.id=l.customer_id WHERE l.id=? FOR UPDATE',[$licenseId]);
        if(!$license||(int)$license['customer_active']!==1||(int)$license['is_master']===1)throw new InvalidArgumentException('Choose an active customer website license.');
        $softwareTable=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
            [config()['db']['prefix'].'software_entitlements']);
        if($softwareTable&&one('SELECT license_id FROM '.table('software_entitlements').' WHERE license_id=?',[$licenseId])){
            throw new InvalidArgumentException('Software subscriptions use the software-commerce billing lifecycle, not website financing.');
        }
        if(one('SELECT license_id FROM '.table('license_financing').' WHERE license_id=? FOR UPDATE',[$licenseId])){
            throw new RuntimeException('This license already has a financing agreement.');
        }
        $schedule=recurring_billing_row($scheduleId);$doc=recurring_billing_validate($schedule);
        if((int)$schedule['client_id']!==(int)$license['customer_id']||$doc->phase()!=='active'){
            throw new RuntimeException('Choose the active recurring financing agreement owned by this license customer.');
        }
        $data=$doc->data();$terms=$data['terms'];
        if(($terms['currency']??'')!=='USD'||!is_int($terms['amount_cents']??null)||(int)$terms['amount_cents']<1){
            throw new RuntimeException('Financing requires a positive agreed USD installment amount.');
        }
        $now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('license_financing').' (license_id,schedule_id,terms_sha256,installments,grace_days,state,paid_off_at,allow_resale,allow_distribution,exclusive_resale,exclusive_distribution,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$licenseId,$scheduleId,license_financing_terms_hash($doc),$installments,$graceDays,'active',null,
             $normalized['resale']?1:0,$normalized['distribution']?1:0,$normalized['exclusive_resale']?1:0,$normalized['exclusive_distribution']?1:0,
             (int)$actor['id'],$now,$now]);
        license_audit((int)$actor['id'],'financing.bind',
            'License '.$licenseId.' bound to financing schedule '.$scheduleId.' for '.$installments.' installments; grace '.$graceDays.
            ' days; '.$terms['amount_cents'].' '.$terms['currency'].' '.$terms['cadence'].'; rights: '.license_financing_rights_label(
                ['allow_resale'=>$normalized['resale']?1:0,'allow_distribution'=>$normalized['distribution']?1:0,
                 'exclusive_resale'=>$normalized['exclusive_resale']?1:0,'exclusive_distribution'=>$normalized['exclusive_distribution']?1:0]
            ));
        return one('SELECT * FROM '.table('license_financing').' WHERE license_id=?',[$licenseId]);
    }));
}

/** Commercial rights are monotonic: an already granted paid right is never silently taken away. */
function license_financing_grant_rights(int $licenseId,array $requested,string $evidence,
    #[\SensitiveParameter]string $password):array{
    $evidence=trim($evidence);
    if(strlen($evidence)<10||strlen($evidence)>200)throw new InvalidArgumentException('Record the agreement or payment evidence for this rights grant.');
    $next=license_financing_rights(
        (bool)($requested['resale']??false),(bool)($requested['distribution']??false),
        (bool)($requested['exclusive_resale']??false),(bool)($requested['exclusive_distribution']??false)
    );
    if(license_financing_feature_state()!==true)throw new RuntimeException('Enable financed-site licensing first.');
    return staff_locked(fn()=>staff_transaction(function()use($licenseId,$next,$evidence,$password):array{
        if(support_current())throw new RuntimeException('Leave support mode before changing license rights.');
        $actor=staff_owner($password);
        $plan=one('SELECT * FROM '.table('license_financing').' WHERE license_id=? FOR UPDATE',[$licenseId]);
        if(!$plan)throw new InvalidArgumentException('Financing agreement not found.');
        $current=license_financing_rights_from_row($plan);
        foreach(['resale','distribution','exclusive_resale','exclusive_distribution'] as $name){
            if($current[$name]&&!$next[$name])throw new RuntimeException('Previously granted commercial rights cannot be removed by this workflow.');
        }
        query('UPDATE '.table('license_financing').' SET allow_resale=?,allow_distribution=?,exclusive_resale=?,exclusive_distribution=?,updated_at=? WHERE license_id=?',
            [$next['resale']?1:0,$next['distribution']?1:0,$next['exclusive_resale']?1:0,$next['exclusive_distribution']?1:0,
             gmdate('Y-m-d H:i:s'),$licenseId]);
        $updated=one('SELECT * FROM '.table('license_financing').' WHERE license_id=?',[$licenseId]);
        license_audit((int)$actor['id'],'financing.rights',
            'License '.$licenseId.' commercial rights granted from retained evidence: '.$evidence.'; '.license_financing_rights_label($updated));
        return $updated;
    }));
}

function license_financing_periods(array $plan,RecurringBillingSchedule $doc):array{
    if(!db()->inTransaction())throw new LogicException('Financing evaluation requires its transaction.');
    $maps=all('SELECT * FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index<? ORDER BY period_index FOR UPDATE',
        [$plan['schedule_id'],(int)$plan['installments']]);
    $periods=[];
    foreach($maps as $map){
        $invoice=recurring_billing_invoice_validate($map,$doc);
        $balance=ledger_balance($invoice);
        $snapshot=json_decode((string)$map['snapshot_json'],true,8,JSON_THROW_ON_ERROR);
        $period=$snapshot['period']??null;
        if(!is_array($period)||!is_string($period['due']??null)||(int)($period['index']??-1)!==(int)$map['period_index']){
            throw new RuntimeException('Financing invoice period snapshot is inconsistent.');
        }
        $periods[]=['index'=>(int)$map['period_index'],'due'=>$period['due'],
            'paid'=>$invoice['status']==='paid'&&(int)$balance['due_cents']===0];
    }
    return $periods;
}

/** Recompute access from retained invoices. paid_off_at is a permanent one-way boundary. */
function license_financing_sync(int $licenseId,?string $today=null):?array{
    if(license_financing_feature_state()===null)return null;
    $today??=gmdate('Y-m-d');
    $result=null;
    license_transaction(function()use($licenseId,$today,&$result):void{
        license_lock($licenseId);
        $plan=one('SELECT f.*,l.customer_id AS client_id FROM '.table('license_financing').' f JOIN '.table('website_licenses').' l ON l.id=f.license_id WHERE f.license_id=? FOR UPDATE',[$licenseId]);
        if(!$plan){$result=null;return;}
        if($plan['paid_off_at']!==null){
            if((string)$plan['state']!=='paid_off')throw new RuntimeException('Paid-off financing state is inconsistent.');
            $result=$plan;return;
        }
        if(!in_array((string)$plan['state'],['active','grace','suspended'],true)){
            throw new RuntimeException('Unknown financed-license state.');
        }
        [, $doc]=license_financing_schedule($plan);
        $evaluation=license_financing_evaluate(
            license_financing_periods($plan,$doc),(int)$plan['installments'],(int)$plan['grace_days'],$today,false
        );
        $old=(string)$plan['state'];$next=$evaluation['state'];$now=gmdate('Y-m-d H:i:s');
        if($next==='paid_off'){
            query('UPDATE '.table('license_financing').' SET state=?,paid_off_at=?,updated_at=? WHERE license_id=? AND paid_off_at IS NULL',
                ['paid_off',$now,$now,$licenseId]);
            license_audit(0,'financing.paid_off',
                'License '.$licenseId.' completed all '.(int)$plan['installments'].' financing installments; payment lock permanently removed');
        }elseif($next!==$old){
            query('UPDATE '.table('license_financing').' SET state=?,updated_at=? WHERE license_id=?',
                [$next,$now,$licenseId]);
            license_audit(0,'financing.state',
                'License '.$licenseId.' financing '.$old.' -> '.$next.
                ($evaluation['overdue_period']!==null?'; period '.$evaluation['overdue_period'].' is '.$evaluation['days_late'].' days late':''));
        }
        $result=one('SELECT * FROM '.table('license_financing').' WHERE license_id=?',[$licenseId]);
        $result['evaluation']=$evaluation;
    });
    return $result;
}

/** Null = no financing plan; true = authorized by financing; false = definitively beyond grace. */
function license_financing_authorized(int $licenseId):?bool{
    $plan=license_financing_sync($licenseId);
    if($plan===null)return null;
    if((string)$plan['state']==='paid_off')return true;
    if((string)$plan['state']==='suspended')return false;
    if(in_array((string)$plan['state'],['active','grace'],true))return true;
    throw new RuntimeException('Financed-license state is not trustworthy.');
}

/** Called after a committed invoice payment/correction; never changes the recorded payment itself. */
function license_financing_sync_by_invoice(int $invoiceId):void{
    if($invoiceId<1||license_financing_feature_state()===null)return;
    $row=one('SELECT f.license_id FROM '.table('recurring_billing_invoices').' r JOIN '.table('license_financing').' f ON f.schedule_id=r.schedule_id WHERE r.invoice_id=?',[$invoiceId]);
    if($row)license_financing_sync((int)$row['license_id']);
}
