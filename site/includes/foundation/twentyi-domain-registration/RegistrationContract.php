<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDomainRegistration;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once __DIR__.'/RegistrationDraft.php';

/** Source facts, deliberately no request builder, credential resolver or sender. */
final class RegistrationContract {
    public const OPERATION='domains.register';
    public const METHOD='POST';
    public const ROUTE='/reseller/*/addDomain';
    public const REFERENCE_SHA256='34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95';
    public static function prerequisites(RegistrationDraft $draft):array {
        $needs=['registration_term_wire_contract','registration_response_contract','current_account_acceptance','current_registration_period','current_availability_and_eligibility','registry_contact_requirements','retail_terms_and_direct_client_consent','prepayment_invoice_purpose_and_receipts','current_owner_charge_approval'];
        if(str_ends_with($draft->selection()['domain'],'.ca')&&!$draft->selection()['ca_acceptance_recorded'])$needs[]='direct_registrant_ca_agreement';
        return $needs;
    }
    /** Transport/body values cannot establish any undocumented registration outcome. */
    public static function unverifiedResponse(#[\SensitiveParameter] int $status,#[\SensitiveParameter] string $body):array {
        unset($status,$body);
        return ['state'=>'unverified','reason'=>'registration_response_contract_missing','may_retry'=>false,'charge_proven_absent'=>false,'registered'=>null];
    }
    public static function canDispatch():bool {return false;}
}
