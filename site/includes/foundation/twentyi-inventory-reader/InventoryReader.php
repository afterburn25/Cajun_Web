<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventoryReader;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\HttpTransport;
use CajunWebPro\Foundation\Provider\ProviderCredential;
use CajunWebPro\Foundation\Provider\TransportException;
use CajunWebPro\Foundation\Provider\TransportRequest;
use CajunWebPro\Foundation\TwentyIInventory\InventoryProjector;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
use CajunWebPro\Foundation\TwentyIInventory\InventorySnapshot;
use InvalidArgumentException;
use Throwable;

/**
 * Callable authenticated read of one 20i inventory operation. The
 * component connects the validated descriptors/projector to the
 * credential resolver and injected transport — it performs exactly
 * one bounded request per call and returns the exact
 * account/operation-bound sanitized snapshot.
 *
 * Only operations with implemented bounded projections are executable.
 * Implementation is distinct from account-specific live acceptance and
 * permission to make a call: the application owns those gates. Sensitive
 * or unsupported descriptors are refused before credentials or transport.
 *
 * The secret is resolved once at the read boundary, attached to the
 * request, and never retained on this component, the snapshot, or
 * any exception. Resolver or transport faults surface only as
 * bounded classifications — raw exception text, request headers and
 * response bodies cannot escape.
 */
final class InventoryReader
{
    public const PROVIDER_ID = 'twenty-i';

    /** Same bound the probe adapter applies to a raw General API key. */
    private const MAX_KEY_BYTES = 4096;
    private const MAX_ACCOUNT_BYTES = 128;
    /** Matches the snapshot's scope bound — observations stay bounded. */
    private const MAX_SCOPE_BYTES = 190;

    /**
     * Documented list reads with bounded projections. Descriptor registration
     * alone never authorizes a call or demonstrates live account entitlement.
     */
    private const EXECUTABLE = [InventoryRead::PACKAGES_LIST, InventoryRead::CLOUD_SERVERS_LIST, InventoryRead::DOMAINS_LIST, InventoryRead::DOMAIN_REGISTRATION_PERIODS, InventoryRead::CLOUD_PROVIDERS_LIST];

    /** True only for operations with evidence enough to execute. */
    public static function supports(string $operation): bool
    {
        return in_array($operation, self::EXECUTABLE, true);
    }

    /**
     * Perform one bounded read and return its snapshot. The snapshot
     * scope is derived inside this boundary — `twenty-i|<account>|<op>` —
     * so a caller can never mislabel which configured account or
     * operation produced an observation.
     *
     * @param int|string|null $id Resource/reseller selector for
     *        operations that take one; ignored for `packages.list`.
     * @throws InvalidArgumentException When the read is refused before
     *         the outbound call: wrong provider, unsupported or
     *         non-executable operation, unusable account identity,
     *         or an unresolvable/invalid secret.
     */
    public static function read(
        HttpTransport $transport,
        #[\SensitiveParameter] ProviderCredential $credential,
        string $operation,
        int|string|null $id,
        int $observedAt,
    ): InventorySnapshot {
        if ($credential->providerId !== self::PROVIDER_ID) {
            throw new InvalidArgumentException('The credential is not bound to this provider.');
        }
        if (!self::supports($operation)) {
            throw new InvalidArgumentException('That inventory read is not an executable operation.');
        }
        $account = $credential->accountIdentity;
        if ($account === '' || strlen($account) > self::MAX_ACCOUNT_BYTES
            || preg_match('//u', $account) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $account)) {
            throw new InvalidArgumentException('The configured account identity is not usable.');
        }
        $scope = self::PROVIDER_ID.'|'.$account.'|'.$operation;
        if (strlen($scope) > self::MAX_SCOPE_BYTES) {
            throw new InvalidArgumentException('The configured account identity exceeds the scope bound.');
        }
        if ($observedAt < 0) {
            throw new InvalidArgumentException('Observation time must be non-negative.');
        }

        // Secret resolved once, at the read boundary, for exactly one
        // request. It is unset before the snapshot is built and never
        // appears on any returned value or thrown classification.
        try {
            $secret = $credential->secret();
        } catch (Throwable) {
            throw new InvalidArgumentException('The provider credential could not be resolved.');
        }
        if ($secret === '' || strlen($secret) > self::MAX_KEY_BYTES || preg_match('/[\x00-\x1f\x7f]/', $secret)) {
            throw new InvalidArgumentException('The provider credential is empty or invalid.');
        }
        $request = InventoryRead::request($operation, $id ?? 0);
        $authorized = new TransportRequest($request->method, $request->url, [
            'Authorization' => 'Bearer '.base64_encode($secret),
            'Accept' => 'application/json',
        ], $request->body, $request->timeoutSeconds);
        unset($secret, $request);

        try {
            $response = $transport->send($authorized);
        } catch (TransportException $failure) {
            unset($authorized);
            return InventoryProjector::unavailableFromTransport($failure, $observedAt, $scope);
        } catch (Throwable) {
            unset($authorized);
            return InventorySnapshot::unavailable('transport_unknown', $observedAt, $scope);
        }
        unset($authorized);
        return match ($operation) {
            InventoryRead::PACKAGES_LIST => InventoryProjector::packagesList($response, $observedAt, $scope),
            InventoryRead::CLOUD_SERVERS_LIST => InventoryProjector::cloudServersList($response, $observedAt, $scope),
            InventoryRead::CLOUD_PROVIDERS_LIST => InventoryProjector::cloudProvidersList($response, $observedAt, $scope),
            InventoryRead::DOMAINS_LIST => InventoryProjector::domainsList($response, $observedAt, $scope),
            InventoryRead::DOMAIN_REGISTRATION_PERIODS => RegistrationPeriods::project($response, $observedAt, $scope),
        };
    }
}
