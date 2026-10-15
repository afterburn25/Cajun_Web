<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * The closed catalog of documented package-lifecycle writes.
 *
 * Every entry is taken literally from docs/TWENTYI_OWNER_API_REFERENCE.md
 * (§1, owner upload hash f7c738e7…). The reference shows complete body
 * fields for addWeb and updatePackage only; userStatus and deleteWeb
 * name their endpoints without body fields, so they are catalogued
 * shape-incomplete and the request builder refuses them until the real
 * field set is verified.
 *
 * Being in this register is not execution authority. The adapter still
 * reports supports() === false and no application path calls these
 * operations; a future wiring must run through ProviderGateway with a
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
                    'id' => 'int',
                    'billing' => 'json',
                    'delete-id' => 'int',
                    'labels' => 'json',
                    'limit' => 'json',
                    'limit-set' => 'json',
                    'packageBundleTypes' => 'json',
                ],
                ['id'],
                shapeComplete: true,
                destructive: true,      // mixed update/delete interface via delete-id
                mayChargeBilling: true, // billing field changes subscription billing
            ),
            new TwentyIOperation(
                'packages.userstatus', 'POST', '/package/{packageId}/userStatus',
                ['packageId'],
                [],
                [],
                shapeComplete: false, // endpoint documented; body fields not shown
            ),
            new TwentyIOperation(
                'packages.delete', 'POST', '/reseller/{resellerId}/deleteWeb',
                ['resellerId'],
                [],
                [],
                shapeComplete: false, // endpoint documented; body fields not shown
                destructive: true,
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
