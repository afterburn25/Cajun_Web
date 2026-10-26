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
