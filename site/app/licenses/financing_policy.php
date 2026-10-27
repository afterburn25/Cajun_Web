<?php
/** Pure financed-license state policy. No database, clock, network, or session access. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function license_financing_date(string $date):DateTimeImmutable{
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$m)
        ||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])){
        throw new InvalidArgumentException('Invalid financing date.');
    }
    return new DateTimeImmutable($date.' 00:00:00',new DateTimeZone('UTC'));
}

/**
 * Evaluate only issued installment invoices. A future/missing invoice is never
 * treated as delinquent. Once paidOff is true, billing state is permanently ignored.
 *
 * $periods: list of ['index'=>int,'due'=>'YYYY-MM-DD','paid'=>bool].
 */
function license_financing_evaluate(array $periods,int $installments,int $graceDays,string $today,bool $paidOff=false):array{
    if($installments<1||$installments>360||$graceDays<0||$graceDays>90){
        throw new InvalidArgumentException('Invalid financing term.');
    }
    $now=license_financing_date($today);
    if($paidOff){
        return ['state'=>'paid_off','paid_count'=>$installments,'required_count'=>$installments,
            'overdue_period'=>null,'days_late'=>0];
    }
    if(!array_is_list($periods))throw new InvalidArgumentException('Invalid financing periods.');
    $seen=[];$paid=0;$grace=null;$suspended=null;
    foreach($periods as $period){
        if(!is_array($period)||array_keys($period)!==['index','due','paid']
            ||!is_int($period['index'])||$period['index']<0||$period['index']>=$installments
            ||!is_string($period['due'])||!is_bool($period['paid'])||isset($seen[$period['index']])){
            throw new InvalidArgumentException('Invalid financing period.');
        }
        $seen[$period['index']]=true;
        if($period['paid']){$paid++;continue;}
        $due=license_financing_date($period['due']);
        if($now<=$due)continue;
        $days=(int)$due->diff($now)->format('%a');
        $candidate=['index'=>$period['index'],'days'=>$days];
        if($days>$graceDays){
            if($suspended===null||$period['index']<$suspended['index'])$suspended=$candidate;
        }elseif($grace===null||$period['index']<$grace['index']){
            $grace=$candidate;
        }
    }
    if($paid===$installments&&count($seen)===$installments){
        return ['state'=>'paid_off','paid_count'=>$paid,'required_count'=>$installments,
            'overdue_period'=>null,'days_late'=>0];
    }
    if($suspended!==null){
        return ['state'=>'suspended','paid_count'=>$paid,'required_count'=>$installments,
            'overdue_period'=>$suspended['index'],'days_late'=>$suspended['days']];
    }
    if($grace!==null){
        return ['state'=>'grace','paid_count'=>$paid,'required_count'=>$installments,
            'overdue_period'=>$grace['index'],'days_late'=>$grace['days']];
    }
    return ['state'=>'active','paid_count'=>$paid,'required_count'=>$installments,
        'overdue_period'=>null,'days_late'=>0];
}

function license_financing_terms_math(int $cashPrice,int $initialPayment,int $amountFinanced,int $financeCharge,int $installmentAmount,int $installments):array{
    if($cashPrice<1||$initialPayment<0||$initialPayment>$cashPrice||$amountFinanced<1||$financeCharge<0
        ||$installmentAmount<1||$installments<1||$installments>360){
        throw new InvalidArgumentException('Financing amounts are outside supported limits.');
    }
    if($cashPrice-$initialPayment!==$amountFinanced){
        throw new InvalidArgumentException('Cash price minus initial payment must equal amount financed.');
    }
    $installmentTotal=$installmentAmount*$installments;
    if($installmentTotal!==$amountFinanced+$financeCharge){
        throw new InvalidArgumentException('Installment amount multiplied by installment count must equal amount financed plus finance charge.');
    }
    return [
        'cash_price_cents'=>$cashPrice,'initial_payment_cents'=>$initialPayment,'amount_financed_cents'=>$amountFinanced,
        'finance_charge_cents'=>$financeCharge,'installment_amount_cents'=>$installmentAmount,'installments'=>$installments,
        'installment_total_cents'=>$installmentTotal,'total_customer_cost_cents'=>$initialPayment+$installmentTotal
    ];
}

function license_financing_cadence_months(string $cadence):int{
    return match($cadence){'monthly'=>1,'quarterly'=>3,'annually'=>12,default=>throw new InvalidArgumentException('Financing cadence must be monthly, quarterly, or annually.')};
}
function license_financing_period_from_pay_num(int $payNum,int $installments):int{
    if($payNum<1||$installments<1||$installments>360||$payNum>$installments)throw new InvalidArgumentException('Financing subscription payment number is outside the signed installment count.');
    return $payNum-1;
}
function license_financing_period_from_replacement_pay_num(int $payNum,int $remainingOccurrences,int $periodOffset,int $signedInstallments):int{
    if($signedInstallments<1||$signedInstallments>360||$periodOffset<0||$periodOffset>=$signedInstallments
        ||$remainingOccurrences!==$signedInstallments-$periodOffset){
        throw new InvalidArgumentException('Replacement financing subscription does not match the remaining signed installment count.');
    }
    $relative=license_financing_period_from_pay_num($payNum,$remainingOccurrences);
    $period=$periodOffset+$relative;
    if($period<0||$period>=$signedInstallments)throw new InvalidArgumentException('Replacement financing payment maps outside the signed installments.');
    return $period;
}
function license_financing_recovery_link_allowed(string $subscriptionStatus):bool{
    return $subscriptionStatus==='billing_suspended';
}

function license_financing_rights(bool $resale,bool $distribution,bool $exclusiveResale,bool $exclusiveDistribution):array{
    if($exclusiveResale&&!$resale)throw new InvalidArgumentException('Exclusive resale requires resale rights.');
    if($exclusiveDistribution&&!$distribution)throw new InvalidArgumentException('Exclusive distribution requires distribution rights.');
    return [
        'resale'=>$resale,'distribution'=>$distribution,
        'exclusive_resale'=>$exclusiveResale,'exclusive_distribution'=>$exclusiveDistribution
    ];
}
