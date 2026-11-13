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
        'client_identity_schema_version'=>'1',
        'delivery_schema_version'=>'1',
        'domain_contracts_schema_version'=>'1',
        'domain_expiry_schema_version'=>'1',
        'domain_renewal_agreements_schema_version'=>'1',
        'domain_renewal_dispatch_schema_version'=>'1',
        'domain_renewals_schema_version'=>'1',
        'gateway_receipts_schema_version'=>'1',
        'hosting_catalog_version'=>'1',
        'hosting_checkout_version'=>'1',
        'hosting_storefront_version'=>'1',
        'hosting_fulfillment_schema_version'=>'1',
        'hosting_operations_schema_version'=>'1',
        'hosting_order_links_schema_version'=>'1',
        'hosting_payments_schema_version'=>'1',
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
        'provider_stackcp_assignment_schema_version'=>'1',
        'provider_stackcp_access_schema_version'=>'1',
        'provider_stackcp_auth_schema_version'=>'1',
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

function database_schema_separate_setups(): array {
    return [
        'delivery_schema_version'=>'Use the website-delivery setup because it also prepares protected private delivery storage.',
        'registration_drafts_schema_version'=>'Use domain-registration setup because it also prepares protected registration key material.',
        'provider_stackcp_auth_schema_version'=>'Use StackCP authentication setup because it also prepares the protected provider vault.',
        'provider_stackcp_assignment_schema_version'=>'Use StackCP assignment setup after authentication and inventory are ready.',
        'provider_stackcp_access_schema_version'=>'Use StackCP access setup after authentication and assignment history are ready.',
        'support_schema_version'=>'Use Support policy setup because enabling it also confirms the owner-reviewed support policy.',
    ];
}

function database_schema_group(string $marker): string {
    return match(true) {
        str_starts_with($marker,'provider_')=>'Provider',
        str_starts_with($marker,'mail_')=>'Mail',
        str_starts_with($marker,'license_')||str_starts_with($marker,'software_')=>'Licensing & software',
        str_starts_with($marker,'payment_')||str_starts_with($marker,'payments_')||str_starts_with($marker,'invoice_')||str_starts_with($marker,'recurring_')||str_starts_with($marker,'gateway_')=>'Billing',
        str_starts_with($marker,'hosting_')||str_starts_with($marker,'service_')=>'Hosting & services',
        str_starts_with($marker,'domain_')||$marker==='registration_drafts_schema_version'=>'Domains',
        str_starts_with($marker,'activity_')||str_starts_with($marker,'appointments_')||str_starts_with($marker,'website_')||str_starts_with($marker,'proposals_')||$marker==='client_identity_schema_version'=>'Customer experience',
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
    if($installed===''&&isset(database_schema_separate_setups()[$entry['marker']]))$action=database_schema_separate_setups()[$entry['marker']];
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

function database_schema_migrations(): array {
    return [
        'staff_schema_version'=>['depends'=>[],'effects'=>[],'note'=>'Registers the current administrator as the initial owner and preserves existing administrator identities.','file'=>'database/migrations/staff_migrate.php','function'=>'staff_migrate','arguments'=>'owner'],
        'studio_schema_version'=>['depends'=>[],'note'=>'Creates or verifies client-workspace and content-history storage.','file'=>'database/migrations/workspace.php','function'=>'studio_migrate','arguments'=>'none'],
        'activity_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/activity_migrate.php','function'=>'activity_migrate','arguments'=>'password'],
        'appointments_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'note'=>'Creates the one supported initial appointment-settings record when scheduling is first prepared.','file'=>'database/migrations/appointments_migrate.php','function'=>'appointments_migrate','arguments'=>'password'],
        'backups_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'note'=>'Also creates or verifies the protected private backup directory.','file'=>'database/migrations/backups_migrate.php','function'=>'backups_migrate','arguments'=>'password'],
        'catalog_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/catalog_migrate.php','function'=>'catalog_migrate','arguments'=>'password'],
        'contacts_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/contacts_migrate.php','function'=>'contacts_migrate','arguments'=>'password'],
        'client_identity_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','contacts_schema_version'],'note'=>'Creates isolated public registration, verification, and recovery state without adopting legacy client accounts.','file'=>'database/migrations/client_identity_migrate.php','function'=>'client_identity_migrate','arguments'=>'password'],
        'license_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/license_migrate.php','function'=>'license_migrate','arguments'=>'password'],
        'payments_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/payments_migrate.php','function'=>'payments_migrate','arguments'=>'password'],
        'notification_feed_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/notification_feed_migrate.php','function'=>'notification_feed_migrate','arguments'=>'password'],
        'proposals_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/proposals_migrate.php','function'=>'proposals_migrate','arguments'=>'password'],
        'support_tickets_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','support_schema_version'],'file'=>'database/migrations/support_tickets_migrate.php','function'=>'support_tickets_migrate','arguments'=>'password'],
        'website_reviews_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/reviews_migrate.php','function'=>'reviews_migrate','arguments'=>'password'],
        'invoice_ledger_schema_version'=>['depends'=>['payments_schema_version'],'note'=>'Imports supported retained paid-invoice balances; it does not send or verify a new payment.','file'=>'database/migrations/ledger_migrate.php','function'=>'ledger_migrate','arguments'=>'password'],
        'gateway_receipts_schema_version'=>['depends'=>['payments_schema_version','invoice_ledger_schema_version'],'note'=>'Creates immutable production gateway receipts; it sends no charge and adopts no prior payment.','file'=>'database/migrations/gateway_receipts_migrate.php','function'=>'gateway_receipts_prepare','arguments'=>'password'],
        'payment_attempts_schema_version'=>['depends'=>['payments_schema_version'],'file'=>'database/migrations/attempts_migrate.php','function'=>'attempts_migrate','arguments'=>'password'],
        'payment_notifications_schema_version'=>['depends'=>['payment_attempts_schema_version'],'file'=>'database/migrations/notifications_migrate.php','function'=>'notifications_migrate','arguments'=>'password'],
        'service_plans_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/service_plans_migrate.php','function'=>'service_plans_migrate','arguments'=>'password'],
        'recurring_billing_schema_version'=>['depends'=>['staff_schema_version','service_plans_schema_version','invoice_ledger_schema_version','payments_schema_version'],'file'=>'database/migrations/recurring_billing_migrate.php','function'=>'recurring_billing_migrate','arguments'=>'password'],
        'license_finance_offers_schema_version'=>['depends'=>['staff_schema_version','license_financing_schema_version','recurring_billing_schema_version','payments_schema_version','invoice_ledger_schema_version'],'file'=>'database/migrations/license_finance_offers_migrate.php','function'=>'license_finance_offers_prepare','arguments'=>'password'],
        'license_finance_recovery_schema_version'=>['depends'=>['license_finance_gateway_schema_version'],'file'=>'database/migrations/license_finance_recovery_migrate.php','function'=>'license_finance_recovery_prepare','arguments'=>'password'],
        'provider_inventory_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_settings_schema_version'],'file'=>'database/migrations/provider_inventory_migrate.php','function'=>'provider_inventory_migrate','arguments'=>'password'],
        'provider_operations_schema_version'=>['depends'=>['staff_schema_version','provider_settings_schema_version','provider_inventory_schema_version'],'file'=>'database/migrations/provider_operations_migrate.php','function'=>'provider_operations_migrate','arguments'=>'password'],
        'software_commerce_version'=>['depends'=>['staff_schema_version','studio_schema_version','license_schema_version','catalog_schema_version'],'file'=>'database/migrations/software_commerce_migrate.php','function'=>'software_commerce_prepare','arguments'=>'password'],
        'software_onboarding_version'=>['depends'=>['staff_schema_version','catalog_schema_version','software_commerce_version'],'file'=>'database/migrations/software_onboarding_migrate.php','function'=>'software_onboarding_prepare','arguments'=>'none'],
        'license_financing_schema_version'=>['depends'=>['staff_schema_version','license_schema_version','recurring_billing_schema_version'],'file'=>'database/migrations/license_financing_migrate.php','function'=>'license_financing_prepare','arguments'=>'password'],
        'license_finance_gateway_schema_version'=>['depends'=>['staff_schema_version','license_finance_offers_schema_version','payments_schema_version','invoice_ledger_schema_version'],'effects'=>['license_finance_recovery_schema_version'],'file'=>'database/migrations/license_finance_gateway_migrate.php','function'=>'license_finance_gateway_prepare','arguments'=>'password'],
        'service_plans_checkout_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','service_plans_schema_version'],'file'=>'database/migrations/service_plans_checkout_migrate.php','function'=>'service_plans_checkout_migrate','arguments'=>'password'],
        'recurring_billing_checkout_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','recurring_billing_schema_version','service_plans_checkout_schema_version','invoice_ledger_schema_version'],'file'=>'database/migrations/recurring_billing_checkout_migrate.php','function'=>'recurring_billing_checkout_migrate','arguments'=>'password'],
        'mail_accounts_schema_version'=>['depends'=>['staff_schema_version'],'file'=>'database/migrations/mail_accounts_migrate.php','function'=>'mail_accounts_migrate','arguments'=>'password'],
        'mail_connections_schema_version'=>['depends'=>['mail_accounts_schema_version'],'file'=>'database/migrations/mail_connections_migrate.php','function'=>'mail_connections_migrate','arguments'=>'password'],
        'mail_drafts_schema_version'=>['depends'=>['mail_accounts_schema_version'],'file'=>'database/migrations/mail_drafts_migrate.php','function'=>'mail_drafts_migrate','arguments'=>'password'],
        'mail_inbox_schema_version'=>['depends'=>['mail_accounts_schema_version'],'file'=>'database/migrations/mail_inbox_migrate.php','function'=>'mail_inbox_migrate','arguments'=>'password'],
        'mail_messages_schema_version'=>['depends'=>['mail_accounts_schema_version'],'file'=>'database/migrations/mail_messages_migrate.php','function'=>'mail_messages_migrate','arguments'=>'password'],
        'mail_notices_schema_version'=>['depends'=>['mail_accounts_schema_version','mail_inbox_schema_version','mail_messages_schema_version'],'file'=>'database/migrations/mail_notices_migrate.php','function'=>'mail_notices_migrate','arguments'=>'password'],
        'mail_submissions_schema_version'=>['depends'=>['mail_accounts_schema_version','mail_drafts_schema_version'],'file'=>'database/migrations/mail_submissions_migrate.php','function'=>'mail_submissions_migrate','arguments'=>'password'],
        'mail_templates_schema_version'=>['depends'=>['staff_schema_version'],'file'=>'database/migrations/mail_templates_migrate.php','function'=>'mail_templates_migrate','arguments'=>'password'],
        'hosting_records_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/hosting_records_migrate.php','function'=>'hosting_records_migrate','arguments'=>'password'],
        'hosting_operations_schema_version'=>['depends'=>['hosting_records_schema_version','provider_settings_schema_version'],'file'=>'database/migrations/hosting_operations_migrate.php','function'=>'hosting_operations_migrate','arguments'=>'password'],
        'service_orders_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','service_plans_schema_version'],'file'=>'database/migrations/service_orders_migrate.php','function'=>'service_orders_migrate','arguments'=>'password'],
        'hosting_order_links_schema_version'=>['depends'=>['service_orders_schema_version','hosting_records_schema_version'],'file'=>'database/migrations/hosting_order_links_migrate.php','function'=>'hosting_order_links_migrate','arguments'=>'password'],
        'hosting_reconciliation_schema_version'=>['depends'=>['hosting_records_schema_version','hosting_operations_schema_version'],'file'=>'database/migrations/hosting_reconciliation_migrate.php','function'=>'hosting_reconciliation_migrate','arguments'=>'password'],
        'hosting_fulfillment_schema_version'=>['depends'=>['service_orders_schema_version','hosting_records_schema_version','hosting_order_links_schema_version','hosting_operations_schema_version','provider_operations_schema_version','hosting_reconciliation_schema_version'],'file'=>'database/migrations/hosting_fulfillment_migrate.php','function'=>'hosting_fulfillment_migrate','arguments'=>'password'],
        'service_observations_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_inventory_schema_version'],'file'=>'app/providers/service_observations_storage.php','function'=>'service_observations_prepare','arguments'=>'password'],
        'provider_settings_schema_version'=>['depends'=>['staff_schema_version'],'file'=>'database/migrations/provider_settings_migrate.php','function'=>'provider_settings_migrate','arguments'=>'password'],
        'provider_catalog_import_version'=>['depends'=>['staff_schema_version','catalog_schema_version','provider_settings_schema_version'],'file'=>'database/migrations/provider_catalog_import_migrate.php','function'=>'provider_catalog_import_prepare','arguments'=>'none'],
        'provider_catalog_variant_version'=>['depends'=>['provider_catalog_import_version'],'file'=>'database/migrations/provider_catalog_variant_migrate.php','function'=>'provider_catalog_variant_prepare','arguments'=>'password'],
        'provider_cloud_confirmation_schema_version'=>['depends'=>['provider_inventory_schema_version','provider_operations_schema_version'],'file'=>'app/providers/cloud_confirmation_storage.php','function'=>'provider_cloud_confirmation_prepare','arguments'=>'password'],
        'provider_cloud_providers_schema_version'=>['depends'=>['provider_inventory_schema_version'],'file'=>'app/providers/cloud_providers_storage.php','function'=>'provider_cloud_providers_prepare','arguments'=>'password'],
        'provider_cloud_site_request_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_inventory_schema_version','provider_operations_schema_version'],'file'=>'app/providers/cloud_site_requests_storage.php','function'=>'provider_cloud_site_requests_prepare','arguments'=>'password'],
        'provider_directory_indexing_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_inventory_schema_version'],'file'=>'app/providers/directory_listing_storage.php','function'=>'provider_directory_indexing_prepare','arguments'=>'password'],
        'provider_directory_indexing_control_schema_version'=>['depends'=>['provider_directory_indexing_schema_version','provider_operations_schema_version'],'file'=>'app/providers/directory_listing_controls_storage.php','function'=>'provider_directory_indexing_controls_prepare','arguments'=>'password'],
        'provider_document_roots_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_inventory_schema_version'],'file'=>'app/providers/document_roots_storage.php','function'=>'provider_document_roots_prepare','arguments'=>'password'],
        'provider_domain_search_schema_version'=>['depends'=>['provider_settings_schema_version'],'file'=>'database/migrations/provider_domain_search_migrate.php','function'=>'provider_domain_search_migrate','arguments'=>'password'],
        'provider_domain_client_schema_version'=>['depends'=>['studio_schema_version','provider_domain_search_schema_version'],'file'=>'app/providers/domain_search_clients.php','function'=>'provider_domain_client_prepare','arguments'=>'password'],
        'provider_force_ssl_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_inventory_schema_version'],'file'=>'app/providers/package_force_ssl_storage.php','function'=>'provider_force_ssl_prepare','arguments'=>'password'],
        'provider_force_ssl_control_schema_version'=>['depends'=>['provider_force_ssl_schema_version','provider_operations_schema_version'],'file'=>'app/providers/force_ssl_controls_storage.php','function'=>'provider_force_ssl_controls_prepare','arguments'=>'password'],
        'provider_forwarding_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_inventory_schema_version','provider_operations_schema_version'],'file'=>'app/providers/forwarding_storage.php','function'=>'provider_forwarding_prepare','arguments'=>'password'],
        'provider_mailbox_schema_version'=>['depends'=>['studio_schema_version','provider_inventory_schema_version','provider_operations_schema_version'],'file'=>'app/providers/mailbox_storage.php','function'=>'provider_mailbox_prepare','arguments'=>'password'],
        'provider_package_types_schema_version'=>['depends'=>['provider_inventory_schema_version'],'file'=>'database/migrations/provider_package_types_migrate.php','function'=>'provider_package_types_prepare','arguments'=>'password'],
        'provider_php_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_inventory_schema_version'],'file'=>'app/providers/package_php_storage.php','function'=>'provider_php_prepare','arguments'=>'password'],
        'provider_plan_catalog_version'=>['depends'=>['service_plans_schema_version','provider_settings_schema_version'],'file'=>'app/providers/plan_catalog.php','function'=>'provider_plan_catalog_prepare','arguments'=>'password'],
        'provider_registrar_schema_version'=>['depends'=>['provider_inventory_schema_version'],'file'=>'app/providers/registrar_storage.php','function'=>'provider_registrar_prepare','arguments'=>'password'],
        'provider_registration_period_version'=>['depends'=>['provider_inventory_schema_version'],'file'=>'app/providers/registration_period_storage.php','function'=>'provider_registration_period_prepare','arguments'=>'password'],
        'provider_schedule_version'=>['depends'=>['provider_inventory_schema_version'],'note'=>'Creates the supported initial local refresh schedule document; it does not call the provider.','file'=>'app/providers/schedule_storage.php','function'=>'provider_schedule_prepare','arguments'=>'password'],
        'provider_wp_installed_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_inventory_schema_version'],'file'=>'app/providers/package_wordpress_installed_storage.php','function'=>'provider_wp_installed_prepare','arguments'=>'password'],
        'domain_contracts_schema_version'=>['depends'=>['provider_registrar_schema_version'],'file'=>'app/providers/domain_contracts_storage.php','function'=>'domain_contracts_prepare','arguments'=>'password'],
        'domain_expiry_schema_version'=>['depends'=>['provider_registrar_schema_version'],'file'=>'app/providers/domain_expiry_storage.php','function'=>'domain_expiry_prepare','arguments'=>'password'],
        'domain_renewals_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_registrar_schema_version'],'file'=>'app/providers/domain_renewals_storage.php','function'=>'domain_renewals_prepare','arguments'=>'password'],
        'domain_renewal_agreements_schema_version'=>['depends'=>['domain_renewals_schema_version','provider_registrar_schema_version','service_plans_schema_version'],'file'=>'app/providers/domain_renewal_agreements_storage.php','function'=>'domain_renewal_agreements_prepare','arguments'=>'password'],
        'domain_renewal_dispatch_schema_version'=>['depends'=>['domain_renewal_agreements_schema_version','provider_operations_schema_version','payments_schema_version','invoice_ledger_schema_version'],'file'=>'app/providers/domain_renewal_dispatch_storage.php','function'=>'domain_renewal_dispatch_prepare','arguments'=>'password'],
        'hosting_catalog_version'=>['depends'=>['catalog_schema_version','provider_plan_catalog_version','service_plans_schema_version'],'file'=>'database/migrations/hosting_catalog_migrate.php','function'=>'hosting_catalog_prepare','arguments'=>'password'],
        'hosting_storefront_version'=>['depends'=>['hosting_catalog_version','provider_catalog_import_version'],'note'=>'Seeds explicit Shared and unavailable VPS categories; it performs no payment or provider operation.','file'=>'database/migrations/hosting_storefront_migrate.php','function'=>'hosting_storefront_prepare','arguments'=>'password'],
        'hosting_checkout_version'=>['depends'=>['hosting_catalog_version','hosting_storefront_version','service_plans_schema_version'],'file'=>'database/migrations/hosting_checkout_migrate.php','function'=>'hosting_checkout_prepare','arguments'=>'password'],
        'hosting_payments_schema_version'=>['depends'=>['hosting_checkout_version','payments_schema_version','invoice_ledger_schema_version','gateway_receipts_schema_version'],'note'=>'Creates disabled-by-default hosting payment intent history; it performs no gateway or provider operation.','file'=>'database/migrations/hosting_payments_migrate.php','function'=>'hosting_payments_prepare','arguments'=>'password'],
    ];
}

function database_schema_plan(array $requested,array $snapshot): array {
    if(!array_is_list($requested)||count($requested)<1||count($requested)>count(database_schema_versions()))throw new InvalidArgumentException('Choose one or more registered database updates.');
    foreach($requested as $marker)if(!is_string($marker)||$marker==='')throw new InvalidArgumentException('Choose registered database updates.');
    if(count(array_unique($requested))!==count($requested))throw new InvalidArgumentException('Choose each database update once.');
    foreach($snapshot as $row)if(($row['state']??null)==='unsupported')throw new RuntimeException('Resolve unsupported database feature versions before applying another update.');
    $migrations=array_map(fn(array $row):array=>$row+['effects'=>[],'note'=>''],database_schema_migrations());$selected=[];$effects=[];
    foreach($requested as $marker){if(!is_string($marker)||!isset($snapshot[$marker],$migrations[$marker]))throw new InvalidArgumentException('Unknown or separately managed database update.');$selected[$marker]=true;}
    $visit=function(string $marker)use(&$visit,&$selected,&$effects,$snapshot,$migrations):void{
        $row=$snapshot[$marker]??null;if(!$row||!isset($migrations[$marker]))throw new RuntimeException('A selected database dependency is not managed by this workflow.');
        if(in_array($row['state'],['current','unsupported'],true)){
            if($row['state']==='current')return;
            throw new RuntimeException($row['label'].' has an unsupported installed version.');
        }
        $selected[$marker]=true;
        foreach($migrations[$marker]['depends'] as $dependency){
            $dependencyRow=$snapshot[$dependency]??null;
            if(!$dependencyRow)throw new RuntimeException('A registered database dependency is missing from the status snapshot.');
            if($dependencyRow['state']==='current')continue;
            if($dependencyRow['state']==='unsupported')throw new RuntimeException($dependencyRow['label'].' has an unsupported installed version.');
            if(!isset($migrations[$dependency]))throw new RuntimeException($dependencyRow['label'].' must be prepared through its feature setup before this update.');
            $visit($dependency);
        }
        foreach($migrations[$marker]['effects'] as $effect){
            $effectRow=$snapshot[$effect]??null;
            if(!$effectRow)throw new RuntimeException('A migration effect is missing from the status snapshot.');
            if($effectRow['state']==='unsupported')throw new RuntimeException($effectRow['label'].' has an unsupported installed version.');
            if($effectRow['state']!=='current')$effects[$marker][]=$effect;
        }
    };
    foreach(array_keys($selected) as $marker)$visit($marker);
    foreach(array_keys($selected) as $marker)if(($snapshot[$marker]['state']??'current')!=='current')foreach($migrations[$marker]['effects'] as $effect)unset($selected[$effect]);
    $ordered=[];$temporary=[];$complete=[];
    $sort=function(string $marker)use(&$sort,&$ordered,&$temporary,&$complete,$selected,$migrations):void{
        if(isset($complete[$marker]))return;if(isset($temporary[$marker]))throw new LogicException('Cyclic database migration dependency.');$temporary[$marker]=true;
        foreach($migrations[$marker]['depends'] as $dependency)if(isset($selected[$dependency]))$sort($dependency);
        unset($temporary[$marker]);$complete[$marker]=true;$ordered[]=$marker;
    };
    $keys=array_keys($selected);sort($keys,SORT_STRING);foreach($keys as $marker)$sort($marker);
    $rowFor=fn(string $marker):array=>['marker'=>$marker,'installed'=>$snapshot[$marker]['installed'],'current'=>$snapshot[$marker]['current'],'state'=>$snapshot[$marker]['state'],'label'=>$snapshot[$marker]['label']];
    $rows=[];$steps=[];foreach($ordered as $marker){$row=$snapshot[$marker];if($row['state']==='current')continue;$steps[]=$marker;$step=$rowFor($marker);$step['note']=$migrations[$marker]['note'];$rows[]=$step;foreach($effects[$marker]??[] as $effect){$effectRow=$rowFor($effect);$effectRow['note']='Prepared as a declared effect of '.$row['label'].'.';$rows[]=$effectRow;}}
    if(!$rows)throw new InvalidArgumentException('The selected database features are already current.');
    $review=['format'=>1,'steps'=>$steps,'rows'=>$rows];$hash=hash('sha256',json_encode($review,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    return ['requested'=>array_values(array_unique($requested)),'order'=>$steps,'rows'=>$rows,'review_hash'=>$hash];
}

function database_schema_execute_reviewed(array $requested,string $reviewHash,callable $snapshot,callable $runStep,?callable $recordStep=null): array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$reviewHash))throw new InvalidArgumentException('Reload and review the exact database update plan.');
    $before=$snapshot();$plan=database_schema_plan($requested,$before);
    if(!hash_equals($plan['review_hash'],$reviewHash))throw new RuntimeException('Database state or selected updates changed. Reload and review the plan again.');
    $reviewed=array_fill_keys(array_column($plan['rows'],'marker'),true);$migrations=array_map(fn(array $row):array=>$row+['effects'=>[],'note'=>''],database_schema_migrations());$applied=[];
    foreach($plan['order'] as $marker){
        $stepRow=$before[$marker];if($recordStep)$recordStep('started',$marker,$stepRow,null);
        try{
            $runStep($marker,$migrations[$marker]);$after=$snapshot();
            if(array_keys($after)!==array_keys($before))throw new RuntimeException('Registered database feature scope changed during update.');
            foreach($after as $changed=>$row){
                if(($before[$changed]['installed']??null)!==($row['installed']??null)&&!isset($reviewed[$changed]))throw new RuntimeException('An unreviewed registered database feature changed: '.$row['label'].'.');
            }
            foreach([$marker,...$migrations[$marker]['effects']] as $target){
                if(($after[$target]['state']??null)!=='current')throw new RuntimeException('Database update did not reach its reviewed target: '.($after[$target]['label']??$target).'.');
            }
        }catch(Throwable $e){if($recordStep)$recordStep('failed',$marker,$stepRow,$e);throw $e;}
        if($recordStep)$recordStep('completed',$marker,$stepRow,null);
        $before=$after;$applied[]=$marker;
    }
    foreach($plan['rows'] as $row)if(($before[$row['marker']]['state']??null)!=='current')throw new RuntimeException('Reviewed database update remains incomplete: '.$row['label'].'.');
    return ['applied'=>$applied,'review_hash'=>$reviewHash,'rows'=>$plan['rows']];
}

function database_schema_current_owner(): array {
    if((function_exists('support_current')&&support_current())||(function_exists('support_binding')&&support_binding()!==''))throw new RuntimeException('End client review before updating the database.');
    $actor=require_admin();if(!admin_session_matches($actor))throw new RuntimeException('Your administrator session changed. Sign in again.');
    if(staff_ready()){$actor=staff_owner('');if(($actor['role']??'')!=='owner')throw new RuntimeException('Only the active owner can update the database.');}
    return $actor;
}

function database_schema_bound_review(array $requested,string $reviewHash): array {
    if(!array_is_list($requested)||count($requested)<1||array_filter($requested,fn($marker)=>!is_string($marker)||$marker==='')||count(array_unique($requested))!==count($requested))throw new RuntimeException('Reload and review the exact database update plan before applying it.');
    $stored=$_SESSION['database_schema_review']??null;
    if(!is_array($stored)||($stored['requested']??null)!==$requested||!is_string($stored['review_hash']??null)||!hash_equals($stored['review_hash'],$reviewHash))throw new RuntimeException('Reload and review the exact database update plan before applying it.');
    return ['requested'=>$requested,'review_hash'=>$stored['review_hash']];
}

function database_schema_run_migration(string $marker,array $migration,array $actor): void {
    require_once CWP_CODE_ROOT.'/'.$migration['file'];$function=$migration['function'];
    if(!function_exists($function))throw new LogicException('Registered database migration is unavailable: '.$marker);
    if($migration['arguments']==='none'){$function();return;}
    if($migration['arguments']==='password'){$function('');return;}
    if($migration['arguments']==='owner'){$function((int)$actor['id'],'');return;}
    throw new LogicException('Unknown registered database migration arguments.');
}

function database_schema_locked(callable $work): mixed {
    $key='cwp-database-schema-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if((int)query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1)throw new RuntimeException('Another database update is in progress. Please retry.');
    try{return $work();}finally{query('SELECT RELEASE_LOCK(?)',[$key]);}
}

function database_schema_record_step(string $outcome,string $marker,array $row,string $reviewHash,?Throwable $error=null): void {
    if(!in_array($outcome,['started','completed','failed'],true))throw new InvalidArgumentException('Unknown database update outcome.');
    $reason=$error?preg_replace('/[^\x20-\x7e]/','?',substr($error->getMessage(),0,180)):'';
    $detail=json_encode(['marker'=>$marker,'label'=>$row['label'],'from'=>$row['installed'],'to'=>$row['current'],'review'=>substr($reviewHash,0,16),'reason'=>$reason],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    try{query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)($_SESSION['uid']??0),'database.update.'.$outcome,$detail,gmdate('Y-m-d H:i:s')]);}
    catch(Throwable $e){
        if($outcome==='started')throw new RuntimeException('Database update history is unavailable. No migration was started.',0,$e);
        if($outcome==='completed')throw new RuntimeException('The database step reached its target but completion history could not be recorded. Recheck database status before continuing.',0,$e);
        error_log('CWP database update failure history write failed');
    }
}

function database_schema_history(int $limit=12): array {
    $limit=max(1,min(30,$limit));
    try{$history=all("SELECT action,detail,created_at FROM ".table('audit')." WHERE action IN ('database.update.started','database.update.completed','database.update.failed') ORDER BY id DESC LIMIT ".$limit);}
    catch(Throwable){return [];}
    $rows=[];foreach($history as $entry){$detail=json_decode((string)($entry['detail']??''),true);if(!is_array($detail)||!is_string($detail['marker']??null)||!isset(database_schema_versions()[$detail['marker']]))continue;$rows[]=['outcome'=>substr((string)$entry['action'],strlen('database.update.')),'created_at'=>(string)($entry['created_at']??''),'marker'=>$detail['marker'],'label'=>(string)($detail['label']??database_schema_label($detail['marker'])),'from'=>(string)($detail['from']??''),'to'=>(string)($detail['to']??''),'reason'=>(string)($detail['reason']??'')];}
    return $rows;
}

function database_schema_apply(array $requested,string $reviewHash): array {
    return database_schema_locked(function()use($requested,$reviewHash):array {
        $actor=database_schema_current_owner();
        return database_schema_execute_reviewed($requested,$reviewHash,'database_schema_snapshot',
            fn(string $marker,array $migration)=>database_schema_run_migration($marker,$migration,$actor),
            fn(string $outcome,string $marker,array $row,?Throwable $error)=>database_schema_record_step($outcome,$marker,$row,$reviewHash,$error));
    });
}
