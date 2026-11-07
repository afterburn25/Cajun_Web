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

function database_schema_migrations(): array {
    return [
        'staff_schema_version'=>['depends'=>[],'file'=>'database/migrations/staff_migrate.php','function'=>'staff_migrate','arguments'=>'owner'],
        'studio_schema_version'=>['depends'=>[],'file'=>'database/migrations/workspace.php','function'=>'studio_migrate','arguments'=>'none'],
        'backups_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/backups_migrate.php','function'=>'backups_migrate','arguments'=>'password'],
        'license_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/license_migrate.php','function'=>'license_migrate','arguments'=>'password'],
        'payments_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version'],'file'=>'database/migrations/payments_migrate.php','function'=>'payments_migrate','arguments'=>'password'],
        'provider_inventory_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','provider_settings_schema_version'],'file'=>'database/migrations/provider_inventory_migrate.php','function'=>'provider_inventory_migrate','arguments'=>'password'],
        'provider_operations_schema_version'=>['depends'=>['staff_schema_version','provider_settings_schema_version','provider_inventory_schema_version'],'file'=>'database/migrations/provider_operations_migrate.php','function'=>'provider_operations_migrate','arguments'=>'password'],
        'software_commerce_version'=>['depends'=>['staff_schema_version','studio_schema_version','license_schema_version','catalog_schema_version'],'file'=>'database/migrations/software_commerce_migrate.php','function'=>'software_commerce_prepare','arguments'=>'password'],
        'license_financing_schema_version'=>['depends'=>['staff_schema_version','license_schema_version','recurring_billing_schema_version'],'file'=>'database/migrations/license_financing_migrate.php','function'=>'license_financing_prepare','arguments'=>'password'],
        'license_finance_gateway_schema_version'=>['depends'=>['staff_schema_version','license_finance_offers_schema_version','payments_schema_version','invoice_ledger_schema_version'],'file'=>'database/migrations/license_finance_gateway_migrate.php','function'=>'license_finance_gateway_prepare','arguments'=>'password'],
        'service_plans_checkout_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','service_plans_schema_version'],'file'=>'database/migrations/service_plans_checkout_migrate.php','function'=>'service_plans_checkout_migrate','arguments'=>'password'],
        'recurring_billing_checkout_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version','recurring_billing_schema_version','service_plans_checkout_schema_version','invoice_ledger_schema_version'],'file'=>'database/migrations/recurring_billing_checkout_migrate.php','function'=>'recurring_billing_checkout_migrate','arguments'=>'password'],
    ];
}

function database_schema_plan(array $requested,array $snapshot): array {
    if(!array_is_list($requested)||count($requested)<1||count($requested)>count(database_schema_versions()))throw new InvalidArgumentException('Choose one or more registered database updates.');
    $migrations=database_schema_migrations();$selected=[];
    foreach($requested as $marker){if(!is_string($marker)||!isset($snapshot[$marker],$migrations[$marker]))throw new InvalidArgumentException('Unknown or separately managed database update.');$selected[$marker]=true;}
    $visit=function(string $marker)use(&$visit,&$selected,$snapshot,$migrations):void{
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
    };
    foreach(array_keys($selected) as $marker)$visit($marker);
    $ordered=[];$temporary=[];$complete=[];
    $sort=function(string $marker)use(&$sort,&$ordered,&$temporary,&$complete,$selected,$migrations):void{
        if(isset($complete[$marker]))return;if(isset($temporary[$marker]))throw new LogicException('Cyclic database migration dependency.');$temporary[$marker]=true;
        foreach($migrations[$marker]['depends'] as $dependency)if(isset($selected[$dependency]))$sort($dependency);
        unset($temporary[$marker]);$complete[$marker]=true;$ordered[]=$marker;
    };
    $keys=array_keys($selected);sort($keys,SORT_STRING);foreach($keys as $marker)$sort($marker);
    $rows=[];foreach($ordered as $marker){$row=$snapshot[$marker];if($row['state']==='current')continue;$rows[]=['marker'=>$marker,'installed'=>$row['installed'],'current'=>$row['current'],'state'=>$row['state'],'label'=>$row['label']];}
    if(!$rows)throw new InvalidArgumentException('The selected database features are already current.');
    $review=['format'=>1,'rows'=>$rows];$hash=hash('sha256',json_encode($review,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    return ['requested'=>array_values(array_unique($requested)),'order'=>array_column($rows,'marker'),'rows'=>$rows,'review_hash'=>$hash];
}
