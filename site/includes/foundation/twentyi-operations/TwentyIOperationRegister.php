<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * The closed catalog of documented package-lifecycle writes.
 *
 * Every entry is taken literally from docs/TWENTYI_OWNER_API_REFERENCE.md
 * (§1, owner upload hash f7c738e7…). The owner complete-reference clarification corrects updatePackage and
 * deleteWeb to ID arrays with scoped per-package maps. Registry-specific
 * contact/DNS schemas and userStatus value domains still need intake.
 *
 * Being in this register is not execution authority. Execution runs through ProviderGateway with a
 * durable OperationLedger and operation-specific acceptance.
 */
final class TwentyIOperationRegister
{
    /** @var array<string,TwentyIOperation> */
    private array $operations;

    public function __construct()
    {
        $operations = [
            new TwentyIOperation(
                'packages.subdomainadd', 'POST', '/package/{packageId}/web/subdomains',
                ['packageId'], ['parentDomain'=>'hostname','subdomain'=>'hostname'],
                ['parentDomain','subdomain'], shapeComplete: true, // Local parent context; dedicated typed builder emits only add/rem.
            ),
            new TwentyIOperation(
                'packages.directoryindexing', 'POST', '/package/{packageId}/web/directoryIndexing',
                ['packageId'], ['value'=>'bool'], ['value'], shapeComplete: true,
            ),
            new TwentyIOperation(
                'packages.forcessl', 'POST', '/package/{packageId}/web/forceSSL',
                ['packageId'], ['value'=>'bool'], ['value'], shapeComplete: true,
            ),
            new TwentyIOperation(
                'cloudsites.create', 'POST', '/cloud_server/{cloudServerId}/addWeb',
                ['cloudServerId'], ['domain_name'=>'hostname','extra_domain_names'=>'hostname_list'],
                ['domain_name','extra_domain_names'], shapeComplete: true,
            ),
            new TwentyIOperation(
                'packages.create', 'POST', '/reseller/{resellerId}/addWeb',
                ['resellerId'],
                [
                    'type' => 'string',
                    'domain_name' => 'hostname',
                    'extra_domain_names' => 'hostname_list',
                    'label' => 'string',
                    'documentRoots' => 'map',
                    'stackUser' => 'string',
                    'packageTemplate' => 'string',
                ],
                ['type', 'domain_name'],
                shapeComplete: true,
                mayChargeBilling: true,
                mayTriggerEmail: true, // linking stackUser can send a welcome email
            ),
            new TwentyIOperation(
                'packages.update', 'POST', '/reseller/{resellerId}/updatePackage',
                ['resellerId'],
                [
                    'id' => 'id_list',
                    'billing' => 'package_map',
                    'delete-id' => 'id_list',
                    'labels' => 'package_map',
                    'limit' => 'package_map',
                    'limit-set' => 'package_map',
                    'packageBundleTypes' => 'package_map',
                ],
                [], // At least one bounded update/delete ID list is required below.
                shapeComplete: true,
                destructive: true,      // mixed update/delete interface via delete-id
                mayChargeBilling: true, // billing field changes subscription billing
            ),
            new TwentyIOperation(
                'domains.renew', 'POST', '/reseller/{resellerId}/renewDomain',
                ['resellerId'],
                [
                    'name' => 'hostname',
                    'years' => 'int',
                    'renewPrivacy' => 'bool',
                ],
                ['name', 'years'],
                shapeComplete: true, // owner reference L239-249 lists the complete field set
                mayChargeBilling: true, // balance-funded renewal
            ),
            new TwentyIOperation(
                'domains.register', 'POST', '/reseller/{resellerId}/addDomain',
                ['resellerId'],
                [],
                [],
                shapeComplete: false, // owner supplied contact structures; typed registry validation pending
                mayChargeBilling: true,
            ),
            new TwentyIOperation(
                'domains.transfer', 'POST', '/reseller/{resellerId}/transferDomain',
                ['resellerId'],
                [],
                [],
                shapeComplete: false, // owner supplied contact structures; full-source intake pending
                mayChargeBilling: true,
            ),
            new TwentyIOperation(
                'domains.nameservers', 'POST', '/package/{packageId}/domain/{domainId}/nameservers',
                ['packageId', 'domainId'],
                ['ns' => 'hostname_list', 'old-ns' => 'hostname_list'],
                ['ns', 'old-ns'],
                shapeComplete: true, // Exact disjoint additions/removals; native consent/read workflow remains separate.
            ),
            new TwentyIOperation(
                'domains.dns', 'POST', '/package/{packageId}/domain/{domainId}/dns',
                ['packageId', 'domainId'],
                [],
                [],
                shapeComplete: false, // owner supplied examples; supported record validation pending
            ),
            new TwentyIOperation(
                'packages.userstatus', 'POST', '/package/{packageId}/userStatus',
                ['packageId'],
                [],
                [],
                shapeComplete: false, // owner supplied fields; value-domain implementation pending
            ),
            new TwentyIOperation(
                'packages.delete', 'POST', '/reseller/{resellerId}/deleteWeb',
                ['resellerId'],
                ['delete-id' => 'id_list'],
                ['delete-id'],
                shapeComplete: true, // Owner complete-reference clarification: array of IDs.
                destructive: true,
            ),
            // Official provision-mailbox-api; the dedicated builder resolves a protected
            // password reference transiently. Generic approval never admits this operation.
            new TwentyIOperation(
                'mailboxes.create', 'POST', '/package/{packageId}/email/{domain}',
                ['packageId', 'domain'],
                ['new'=>'json'], ['new'], shapeComplete: true,
            ),
        ];
        $this->operations = [];
        foreach ($operations as $operation) {
            $this->operations[$operation->name] = $operation;
        }
    }

    public function get(string $name): ?TwentyIOperation
    {
        return $this->operations[$name] ?? null;
    }

    /**
     * Operations whose documented field set is complete enough to
     * construct a request. Shape-incomplete entries never appear here.
     *
     * @return list<string>
     */
    public function buildableNames(): array
    {
        $names = [];
        foreach ($this->operations as $name => $operation) {
            if ($operation->shapeComplete) {
                $names[] = $name;
            }
        }
        return $names;
    }

    /** Public owner approval is narrower than request construction. */
    public function genericNames(): array { return ['packages.update']; }

    /**
     * Only the documented label-only subset has no purchase, deletion, plan,
     * billing or welcome-email effect. Commercial/message operations require
     * a dedicated verified order/quote/consent workflow, still being implemented.
     * A JSON scope plus a password is not such authorization.
     */
    public function allowsGenericApproval(string $operation,array $params): bool
    {
        $spec=$this->get($operation);
        if($spec===null || !$spec->shapeComplete)return false;
        if($spec->mayChargeBilling || $spec->mayTriggerEmail || $spec->destructive) {
            if($operation!=='packages.update')return false;
            $keys=array_keys($params);sort($keys);
            if($keys!==['id','labels','resellerId'] || !is_array($params['labels']) || !$params['labels'])return false;
            foreach($params['labels'] as $value)if(!is_string($value))return false;
            return true; // The builder separately enforces target IDs and bounds.
        }
        return false; // Future operations need an explicit reviewed admission rule.
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->operations);
    }

    /**
     * Documented write surfaces deliberately excluded from this
     * register. Each name is a refusal reason, not a hidden operation:
     * these need separate evidence and gates before any request shape
     * can even be constructed.
     *
     * @return array<string,string>
     */
    public function exclusions(): array
    {
        return [
            'resellers.users' => 'StackCP user create/update carries passwords, grants and optional new-user email; access-control write requiring a dedicated grant model.',
            'resellers.grants' => 'Grant changes alter access to arbitrary resources; requires an explicit reviewed grant flow.',
            'domains.register' => 'Registration charges the account and creates a real domain order; requires billing acceptance and order confirmation.',
            'domains.transfer' => 'Transfers are billing, legal-ownership and potentially irreversible operations; requires a dedicated transfer gate.',
            'domains.renew' => 'Renewal charges the account; requires billing acceptance.',
            'domains.nameservers' => 'Nameserver changes move live DNS; requires per-domain authorization and confirmation.',
            'domains.dns' => 'DNS record writes alter live zone data; requires zone review and record-specific validation.',
            'vps.create' => 'addVPS/addManagedVPS provisions billed infrastructure; requires billing acceptance and documented schema.',
            'vps.power' => 'Reboot/shutdown examples are GET-labelled mutations; method alone must not grant execution.',
            'vps.renew' => 'VPS renewal charges the account; requires billing acceptance.',
            'vps.vnc' => 'VNC endpoints return console credentials; credential-bearing reads/writes stay excluded.',
            'ssh.access' => 'SSH key/configuration writes grant server access; requires a dedicated access gate.',
            'email.send' => 'Any operation that sends customer email needs template review and explicit enablement.',
            'billing.charge' => 'Direct billing operations are never exposed through a generic operation surface.',
        ];
    }
}
