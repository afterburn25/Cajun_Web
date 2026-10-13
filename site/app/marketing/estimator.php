<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

foreach (['PriceRule','EstimateLine','EstimateRequest','EstimateRuleSet','EstimateResult','Estimator'] as $class) {
    require_once CWP_CODE_ROOT.'/includes/foundation/estimator/'.$class.'.php';
}

/** Exact component arithmetic behind the live estimator's validated selections. */
function growth_estimator_range(array $quantities,array $prices): ?array {
    $rules=[];$overflow=false;
    foreach($quantities as $key=>$quantity){
        $pair=grange($prices[$key]??[null,null]);
        // Preserve the live evaluator's early return before inspecting later rules.
        if($pair[0]===null)return null;
        if(!is_int($quantity)||$quantity<1)throw new LogicException('Validated positive quantities are required.');
        // Still validate later configured rules, preserving existing error order
        // on narrower integer platforms without ever multiplying into a float.
        if($pair[1]>intdiv(PHP_INT_MAX,$quantity)){$overflow=true;continue;}
        $rules[]=new \CajunWebPro\Foundation\Estimator\PriceRule((string)$key,(string)$key,$pair[0]*$quantity,$pair[1]*$quantity);
    }
    if($overflow)return null;
    $selections=array_keys($quantities);
    $request=new \CajunWebPro\Foundation\Estimator\EstimateRequest($selections[0],options:array_slice($selections,1));
    $result=(new \CajunWebPro\Foundation\Estimator\Estimator())->evaluate($request,new \CajunWebPro\Foundation\Estimator\EstimateRuleSet($rules));
    if($result->kind!==\CajunWebPro\Foundation\Estimator\EstimateResult::KIND_ESTIMATE || $result->highCents>99999999)return null;
    return [$result->lowCents,$result->highCents];
}
