<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Guard for subscription plans in the shared customer portal. */
function software_subscription_term(string $term): string {
    if (!in_array($term, ['month', 'year'], true)) {
        throw new InvalidArgumentException('Subscription term must be monthly or yearly.');
    }
    return $term;
}


/** Extend a paid-through UTC timestamp by calendar months, clamping month-end safely. */
function software_subscription_extend_expiry(string $expiresAt,int $months,DateTimeImmutable $now):string {
    if(!in_array($months,[1,12],true))throw new InvalidArgumentException('Subscription interval must be one or twelve months.');
    $zone=new DateTimeZone('UTC');
    $current=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$expiresAt,$zone);
    $errors=DateTimeImmutable::getLastErrors();
    if($current===false||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))
        ||$current->format('Y-m-d H:i:s')!==$expiresAt){
        throw new InvalidArgumentException('Invalid subscription expiry timestamp.');
    }
    $base=$current>$now?$current:$now->setTimezone($zone);
    $year=(int)$base->format('Y');$month=(int)$base->format('n');$day=(int)$base->format('j');
    $absolute=$year*12+($month-1)+$months;
    $targetYear=intdiv($absolute,12);$targetMonth=($absolute%12)+1;
    $first=$base->setDate($targetYear,$targetMonth,1);
    $targetDay=min($day,(int)$first->format('t'));
    return $first->setDate($targetYear,$targetMonth,$targetDay)->format('Y-m-d H:i:s');
}
