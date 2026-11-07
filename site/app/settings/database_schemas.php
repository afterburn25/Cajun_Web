<?php
/** Complete read-only registry for installed feature schema/version markers. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function database_schema_versions(): array {
    return [
        'activity_schema_version'=>'1',
        'appointments_schema_version'=>'1',
        'backups_schema_version'=>'2',
        'catalog_schema_version'=>'1',
        'contacts_schema_version'=>'1',
        'delivery_schema_version'=>'1',
        'domain_contracts_schema_version'=>'1',
        'domain_expiry_schema_version'=>'1',
        'domain_renewal_agreements_schema_version'=>'1',
        'domain_renewal_dispatch_schema_version'=>'1',
        'domain_renewals_schema_version'=>'1',
        'hosting_catalog_version'=>'1',
        'hosting_checkout_version'=>'1',
        'hosting_fulfillment_schema_version'=>'1',
        'hosting_operations_schema_version'=>'1',
        'hosting_order_links_schema_version'=>'1',
        'hosting_reconciliation_schema_version'=>'1',
        'hosting_records_schema_version'=>'1',
        'invoice_ledger_schema_version'=>'1',
        'license_finance_gateway_schema_version'=>'3',
        'license_finance_offers_schema_version'=>'1',
        'license_finance_recovery_schema_version'=>'1',
        'license_financing_schema_version'=>'2',
        'license_schema_version'=>'3',
        'mail_accounts_schema_version'=>'1',
        'mail_connections_schema_version'=>'1',
        'mail_drafts_schema_version'=>'1',
        'mail_inbox_schema_version'=>'1',
        'mail_messages_schema_version'=>'1',
        'mail_notices_schema_version'=>'1',
        'mail_submissions_schema_version'=>'1',
        'mail_templates_schema_version'=>'1',
        'notification_feed_version'=>'1',
        'payment_attempts_schema_version'=>'1',
        'payment_notifications_schema_version'=>'1',
        'payments_schema_version'=>'2',
        'proposals_schema_version'=>'1',
        'provider_catalog_import_version'=>'1',
        'provider_catalog_variant_version'=>'1',
        'provider_cloud_confirmation_schema_version'=>'1',
        'provider_cloud_providers_schema_version'=>'1',
        'provider_cloud_site_request_schema_version'=>'1',
        'provider_directory_indexing_control_schema_version'=>'1',
        'provider_directory_indexing_schema_version'=>'1',
        'provider_document_roots_schema_version'=>'1',
        'provider_domain_client_schema_version'=>'1',
        'provider_domain_search_schema_version'=>'1',
        'provider_force_ssl_control_schema_version'=>'1',
        'provider_force_ssl_schema_version'=>'1',
        'provider_forwarding_schema_version'=>'1',
        'provider_inventory_schema_version'=>'5',
        'provider_mailbox_schema_version'=>'1',
        'provider_operations_schema_version'=>'4',
        'provider_package_types_schema_version'=>'1',
        'provider_php_schema_version'=>'1',
        'provider_plan_catalog_version'=>'1',
        'provider_registrar_schema_version'=>'1',
        'provider_registration_period_version'=>'1',
        'provider_schedule_version'=>'1',
        'provider_settings_schema_version'=>'1',
        'provider_stackcp_access_schema_version'=>'1',
        'provider_wp_installed_schema_version'=>'1',
        'recurring_billing_checkout_schema_version'=>'1',
        'recurring_billing_schema_version'=>'1',
        'registration_drafts_schema_version'=>'1',
        'service_observations_schema_version'=>'1',
        'service_orders_schema_version'=>'1',
        'service_plans_checkout_schema_version'=>'1',
        'service_plans_schema_version'=>'1',
        'software_commerce_version'=>'3',
        'software_onboarding_version'=>'1',
        'staff_schema_version'=>'1',
        'studio_schema_version'=>'2',
        'support_schema_version'=>'1',
        'support_tickets_schema_version'=>'1',
        'website_reviews_schema_version'=>'1',
    ];
}

function database_schema_upgrade_sources(): array {
    return [
        'backups_schema_version'=>['1'],
        'license_finance_gateway_schema_version'=>['1','2'],
        'license_financing_schema_version'=>['1'],
        'license_schema_version'=>['1','2'],
        'payments_schema_version'=>['1'],
        'provider_inventory_schema_version'=>['1','2','3','4'],
        'provider_operations_schema_version'=>['1','2','3'],
        'software_commerce_version'=>['1','2'],
    ];
}

function database_schema_recovery_sources(): array {
    return [
        'recurring_billing_checkout_schema_version'=>['preparing-1'],
        'service_plans_checkout_schema_version'=>['preparing-1'],
    ];
}

function database_schema_required_markers(): array {
    return ['staff_schema_version','studio_schema_version'];
}

function database_schema_group(string $marker): string {
    return match(true) {
        str_starts_with($marker,'provider_')=>'Provider',
        str_starts_with($marker,'mail_')=>'Mail',
        str_starts_with($marker,'license_')||str_starts_with($marker,'software_')=>'Licensing & software',
        str_starts_with($marker,'payment_')||str_starts_with($marker,'payments_')||str_starts_with($marker,'invoice_')||str_starts_with($marker,'recurring_')=>'Billing',
        str_starts_with($marker,'hosting_')||str_starts_with($marker,'service_')=>'Hosting & services',
        str_starts_with($marker,'domain_')||$marker==='registration_drafts_schema_version'=>'Domains',
        str_starts_with($marker,'activity_')||str_starts_with($marker,'appointments_')||str_starts_with($marker,'website_')||str_starts_with($marker,'proposals_')=>'Customer experience',
        default=>'Administration',
    };
}

function database_schema_label(string $marker): string {
    $name=preg_replace('/_(?:schema_)?version$/','',$marker);
    $name=str_replace(['provider_','wp_'],['20i ','WordPress '],(string)$name);
    return ucwords(str_replace('_',' ',$name));
}

function database_schema_registry(): array {
    $required=array_flip(database_schema_required_markers());
    $upgrades=database_schema_upgrade_sources();$recoveries=database_schema_recovery_sources();$rows=[];
    foreach(database_schema_versions() as $marker=>$current)$rows[$marker]=[
        'marker'=>$marker,
        'label'=>database_schema_label($marker),
        'group'=>database_schema_group($marker),
        'current'=>$current,
        'required'=>isset($required[$marker]),
        'upgrade_from'=>$upgrades[$marker]??[],
        'recover_from'=>$recoveries[$marker]??[],
    ];
    return $rows;
}

function database_schema_classify(array $entry,string $installed): array {
    $state='unsupported';$action='Restore a supported database state before applying another update.';
    if(hash_equals($entry['current'],$installed)){$state='current';$action='No update required.';}
    elseif($installed===''){$state=$entry['required']?'required':'optional';$action=$entry['required']?'Required setup is missing.':'Optional feature storage is not prepared.';}
    elseif(in_array($installed,$entry['upgrade_from'],true)){$state='update';$action='A reviewed additive update is available.';}
    elseif(in_array($installed,$entry['recover_from'],true)){$state='interrupted';$action='A supported interrupted update can be resumed.';}
    return $entry+['installed'=>$installed,'state'=>$state,'action'=>$action,
        'needs_update'=>in_array($state,['required','update','interrupted'],true)];
}

function database_schema_snapshot(): array {
    $rows=[];foreach(database_schema_registry() as $marker=>$entry)$rows[$marker]=database_schema_classify($entry,setting($marker));return $rows;
}

function database_schema_summary(array $rows): array {
    $counts=array_fill_keys(['current','required','optional','update','interrupted','unsupported'],0);
    foreach($rows as $row){if(!is_array($row)||!isset($counts[$row['state']??'']))throw new InvalidArgumentException('Invalid database schema status row.');$counts[$row['state']]++;}
    $counts['needs_update']=$counts['required']+$counts['update']+$counts['interrupted'];
    $counts['blocking']=$counts['unsupported'];
    $counts['all_current']=$counts['needs_update']===0&&$counts['blocking']===0;
    return $counts;
}

function database_schema_groups(array $rows): array {
    $groups=[];foreach($rows as $row)$groups[$row['group']][]=$row;ksort($groups);return $groups;
}
