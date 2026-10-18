<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIDomainExpiry;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{HttpTransport, ProviderCredential, TransportRequest, TransportException};
use CajunWebPro\Foundation\TwentyIInventory\InventoryDomain;

/** Credential-owning adapter for the retained pure registrar-expiry contract. */
final class DomainExpiryReader
{
    public static function acceptsUrl(string $url): bool
    {
        $suffix = '/upstreamExpiryDate';
        if (!str_starts_with($url, DomainExpiry::PREFIX) || !str_ends_with($url, $suffix)) return false;
        $name = substr($url, strlen(DomainExpiry::PREFIX), -strlen($suffix));
        try {
            $domain = InventoryDomain::fromItem((object)['id' => 1, 'name' => $name]);
            return DomainExpiry::PREFIX . $domain->name . $suffix === $url;
        } catch (\InvalidArgumentException) { return false; }
    }

    public static function read(
        HttpTransport $transport,
        #[\SensitiveParameter] ProviderCredential $credential,
        DomainExpiryBinding $binding,
        int $at,
    ): DomainExpiryObservation {
        if ($credential->providerId !== 'twenty-i'
            || $credential->accountIdentity !== $binding->registrar->data['profile_id'] || $at < 0) {
            throw new \InvalidArgumentException('Invalid registrar expiry account.');
        }
        try { $secret = $credential->secret(); }
        catch (\Throwable) { throw new \InvalidArgumentException('Provider credentials are unavailable.'); }
        if ($secret === '' || strlen($secret) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $secret)) {
            throw new \InvalidArgumentException('Provider credentials are unavailable.');
        }
        $descriptor = DomainExpiry::request($binding);
        $request = new TransportRequest('GET', $descriptor->url,
            ['Authorization' => 'Bearer ' . base64_encode($secret), 'Accept' => 'application/json'], null, 15.0);
        unset($descriptor);
        try {
            try { $response = $transport->send($request); }
            catch (TransportException $error) {
                return new DomainExpiryObservation($binding, 'unavailable', null, 'transport_' . $error->kind, $at);
            } catch (\Throwable) {
                return new DomainExpiryObservation($binding, 'unavailable', null, 'transport_unknown', $at);
            }
            $observation = DomainExpiry::project($response, $binding, $at);
            if ($observation->state === 'complete'
                && (str_contains($observation->upstreamExpiryDate, $secret)
                    || str_contains($observation->upstreamExpiryDate, base64_encode($secret)))) {
                return new DomainExpiryObservation($binding, 'unavailable', null, 'timestamp_unsupported', $at);
            }
            return $observation;
        } finally { unset($secret, $request); }
    }
}
