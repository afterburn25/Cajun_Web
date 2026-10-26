<?php
/** Pure Authorize.net request contracts; never accepts card numbers or CVV. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function software_anet_amount(int $cents): string {
    if ($cents < 1 || $cents > 99999999) {
        throw new InvalidArgumentException('Amount must be positive and within invoice limits.');
    }
    return number_format($cents / 100, 2, '.', '');
}
/** Compare gateway currency in exact minor units, rejecting invalid precision and types. */
function software_charge_exact_cents(mixed $value): ?int {
    if (!is_string($value) && !is_int($value)) return null;
    $amount=(string)$value;
    if (!preg_match('/^(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?$/D',$amount,$parts)) return null;
    return ((int)$parts[1])*100+(int)str_pad($parts[2]??'',2,'0');
}

function software_anet_reversal_details(array $details,string $eventTransactionId,string $eventType):array{
    $expectedType = match($eventType){
        'net.authorize.payment.refund.created'=>'refundTransaction',
        'net.authorize.payment.void.created'=>'voidTransaction',
        default=>throw new InvalidArgumentException('Unsupported payment reversal event.')
    };
    $tx=$details['transaction']??null;
    if(($details['messages']['resultCode']??'')!=='Ok'||!is_array($tx)
        ||(string)($tx['transId']??'')!==$eventTransactionId
        ||(string)($tx['transactionType']??'')!==$expectedType
        ||!preg_match('/^[0-9]{5,30}$/D',$eventTransactionId)
        ||!preg_match('/^[0-9]{5,30}$/D',(string)($tx['refTransId']??''))){
        throw new RuntimeException('Gateway reversal could not be tied to a verified original transaction.');
    }
    return [
        'kind'=>$expectedType==='refundTransaction'?'refund':'void',
        'transaction_id'=>$eventTransactionId,
        'original_transaction_id'=>(string)$tx['refTransId'],
        'amount_cents'=>$expectedType==='refundTransaction'
            ? software_charge_exact_cents($tx['authAmount']??null)
            : null,
    ];
}


/** Pure subscription webhook state policy. Review is sticky until an owner resolves it. */
/** Verify the gateway fields shared by production settlement and sandbox shape checks. */
function software_anet_subscription_charge_details(array $details,string $transactionId):array{
    $tx=$details['transaction']??null;
    if(($details['messages']['resultCode']??'')!=='Ok'||!is_array($tx)
        ||(string)($tx['transId']??'')!==$transactionId
        ||!preg_match('/^[0-9]{5,30}$/D',$transactionId)
        ||!in_array($tx['transactionStatus']??'', ['capturedPendingSettlement','settledSuccessfully'],true)
        ||!isset($tx['subscription']['id'])
        ||!preg_match('/^[0-9]{1,25}$/D',(string)$tx['subscription']['id'])){
        throw new RuntimeException('Gateway transaction is not a verified captured subscription charge.');
    }
    $cents=software_charge_exact_cents($tx['authAmount']??null);
    $invoice=(string)($tx['order']['invoiceNumber']??'');
    if($cents===null||$cents<1||$invoice===''||strlen($invoice)>20){
        throw new RuntimeException('Gateway subscription amount or invoice reference is invalid.');
    }
    return [
        'subscription_id'=>(string)$tx['subscription']['id'],
        'amount_cents'=>$cents,
        'invoice_number'=>$invoice,
        'transaction_status'=>(string)$tx['transactionStatus'],
    ];
}

function software_subscription_event_target(string $eventType,string $currentStatus):?string{
    if($currentStatus==='review') return 'review';
    return match($eventType){
        'net.authorize.customer.subscription.failed',
        'net.authorize.customer.subscription.suspended',
        'net.authorize.customer.subscription.terminated'=>'review',
        'net.authorize.customer.subscription.cancelled',
        'net.authorize.customer.subscription.expired'=>'cancelled',
        default=>null
    };
}

function software_anet_nonce(string $descriptor, string $value): array {
    if ($descriptor !== 'COMMON.ACCEPT.INAPP.PAYMENT' || strlen($value) < 8 || strlen($value) > 8192) {
        throw new InvalidArgumentException('Use a valid Authorize.net Accept.js payment token.');
    }
    return ['opaqueData'=>['dataDescriptor'=>$descriptor, 'dataValue'=>$value]];
}
function software_anet_charge(int $invoiceId, int $cents, string $descriptor, string $value): array {
    if ($invoiceId < 1) throw new InvalidArgumentException('Invoice is required.');
    return ['refId'=>substr('I'.$invoiceId, 0, 20), 'transactionRequest'=>[
        'transactionType'=>'authCaptureTransaction',
        'amount'=>software_anet_amount($cents),
        'payment'=>software_anet_nonce($descriptor, $value),
        'order'=>['invoiceNumber'=>substr('CWP-'.$invoiceId, 0, 20)]
    ]];
}
function software_anet_approved(array $reply): ?string {
    $transaction = $reply['transactionResponse'] ?? null;
    if (($reply['messages']['resultCode'] ?? '') !== 'Ok'
        || !is_array($transaction)
        || ($transaction['responseCode'] ?? '') !== '1') return null;
    $id = $transaction['transId'] ?? null;
    return is_string($id) && preg_match('/^[0-9]{5,30}$/D', $id) ? $id : null;
}
function software_anet_arb_request(string $name, int $cents, string $start, int $months,
    int $occurrences, string $profile, string $paymentProfile): array {
    if (trim($name) === '' || strlen($name) > 50 || $months < 1 || $months > 12
        || $occurrences < 1 || $occurrences > 9999
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $start)
        || !checkdate((int)substr($start, 5, 2), (int)substr($start, 8, 2), (int)substr($start, 0, 4))
        || $start < gmdate('Y-m-d')
        || !preg_match('/^[0-9]{1,20}$/D', $profile)
        || !preg_match('/^[0-9]{1,20}$/D', $paymentProfile)) {
        throw new InvalidArgumentException('Invalid recurring payment schedule or profile.');
    }
    return ['subscription'=>[
        'name'=>$name,
        'paymentSchedule'=>[
            'interval'=>['length'=>$months,'unit'=>'months'],
            'startDate'=>$start,'totalOccurrences'=>$occurrences
        ],
        'amount'=>software_anet_amount($cents),
        'profile'=>['customerProfileId'=>$profile,'customerPaymentProfileId'=>$paymentProfile]
    ]];
}

/** Authorize.net ARB accepts the one-use Accept.js opaque payment token. */
function software_anet_arb_opaque(int $invoiceId,int $cents,int $intervalMonths,string $startDate,string $descriptor,string $nonce):array{
    if($invoiceId<1||!in_array($intervalMonths,[1,12],true)
        ||!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$startDate)
        ||!checkdate((int)substr($startDate,5,2),(int)substr($startDate,8,2),(int)substr($startDate,0,4))
        ||$startDate<=gmdate('Y-m-d'))throw new InvalidArgumentException('Invalid subscription schedule.');
    return [
        'refId'=>substr('SW'.$invoiceId,0,20),
        'subscription'=>[
            'name'=>'CajunWebPro software '.$invoiceId,
            'paymentSchedule'=>[
                'interval'=>['length'=>$intervalMonths,'unit'=>'months'],
                'startDate'=>$startDate,'totalOccurrences'=>9999
            ],
            'amount'=>software_anet_amount($cents),
            'payment'=>software_anet_nonce($descriptor,$nonce),
            'order'=>['invoiceNumber'=>substr('CWP-'.$invoiceId,0,20)]
        ]
    ];
}
