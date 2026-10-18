<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventoryReader;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use InvalidArgumentException;
use JsonException;
use stdClass;
use Throwable;

/** Bounded domain lookup through the existing authenticated provider transport.
 * Only the official documented name/can=register result is a checked availability
 * signal. It never establishes a price, purchasable TLD, entitlement or reservation.
 * Other names remain suggestions until a separate exact-name check is performed.
 */
final class DomainSearch
{
    public const MAX_BODY_BYTES = 262144;
    public const MAX_LINE_BYTES = 4096;
    public const MAX_RECORDS = 256;
    public const TIMEOUT_SECONDS = 15.0;
    public const URL_PREFIX = 'https://api.20i.com/domain-search/';

    /** First native lookup accepts one ASCII name, not a bulk/free-text query. */
    public static function name(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 253 || !str_contains($value, '.')
            || preg_match('/\A[a-zA-Z0-9.-]+\z/', $value) !== 1) {
            throw new InvalidArgumentException('Enter one complete ASCII domain name.');
        }
        $name = strtolower($value);
        foreach (explode('.', $name) as $label) {
            if (strlen($label) > 63 || preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/', $label) !== 1
                || str_starts_with($label, 'xn--')) {
                throw new InvalidArgumentException('Enter one complete ASCII domain name.');
            }
        }
        $labels = explode('.', $name);
        if (preg_match('/\A[a-z]{2,63}\z/', end($labels)) !== 1) {
            throw new InvalidArgumentException('Enter a domain with an alphabetic extension.');
        }
        return $name;
    }

    public static function request(string $name): TransportRequest
    {
        return new TransportRequest('GET', self::URL_PREFIX.self::name($name),
            ['Accept'=>'application/json'], null, self::TIMEOUT_SECONDS);
    }

    private static function result(string $query, string $scope, int $at, string $state,
        string $reason, bool $available = false, array $suggestions = []): array
    {
        return ['query'=>$query, 'scope'=>$scope, 'observed_at'=>$at, 'state'=>$state,
            'reason'=>$reason, 'registration_available'=>$available, 'suggestions'=>$suggestions];
    }

    /** Parses complete bounded transport bytes; newline packets may arrive in any order.
     * Both documented NDJSON and the official console's displayed JSON array are
     * accepted. Any malformed, conflicting or truncated response removes availability.
     */
    public static function project(TransportResponse $response, string $query, int $at, string $scope): array
    {
        $query = self::name($query);
        if ($at < 0 || $scope === '' || strlen($scope) > 190 || preg_match('/\A[\x20-\x7e]+\z/', $scope) !== 1) {
            throw new InvalidArgumentException('Invalid search context.');
        }
        $empty = static fn(string $state, string $reason): array => self::result($query,$scope,$at,$state,$reason);
        if ($response->status !== 200) {
            $reason = match (true) {
                in_array($response->status,[401,403],true) => 'authentication',
                $response->status === 429 => 'rate_limited',
                $response->status >= 500 => 'remote_error',
                default => 'unexpected_status',
            };
            return $empty('unavailable',$reason);
        }
        if (strlen($response->body) > self::MAX_BODY_BYTES) return $empty('unavailable','response_limit');
        $body = $response->body;
        if (trim($body) === '') return $empty('unavailable','empty_response');
        $records = [];
        try {
            if (str_starts_with(ltrim($body),'[')) {
                $records = json_decode($body,false,8,JSON_THROW_ON_ERROR);
                if (!is_array($records) || count($records) > self::MAX_RECORDS) return $empty('unavailable','response_limit');
                foreach ($records as $record) {
                    if (strlen(json_encode($record,JSON_THROW_ON_ERROR)) > self::MAX_LINE_BYTES) return $empty('unavailable','response_limit');
                }
            } else {
                foreach (explode("\n",$body) as $line) {
                    if (trim($line) === '') continue;
                    if (strlen($line) > self::MAX_LINE_BYTES || count($records) >= self::MAX_RECORDS) return $empty('unavailable','response_limit');
                    $records[] = json_decode($line,false,8,JSON_THROW_ON_ERROR);
                }
            }
        } catch (JsonException) { return $empty('unavailable','malformed_response'); }

        $header = false; $checked = null; $partial = false; $suggestions = [];
        foreach ($records as $record) {
            if (!$record instanceof stdClass) { $partial=true; continue; }
            $keys = array_keys(get_object_vars($record)); sort($keys);
            if ($keys === ['header']) {
                if ($header || !$record->header instanceof stdClass
                    || array_keys(get_object_vars($record->header)) !== ['names']
                    || !is_array($record->header->names) || count($record->header->names) !== 1) { $partial=true; continue; }
                try { $header = self::name($record->header->names[0]) === $query; }
                catch (InvalidArgumentException) { $partial=true; }
                if (!$header) $partial=true;
                continue;
            }
            try { $name = self::name($record->name ?? null); }
            catch (InvalidArgumentException) { $partial=true; continue; }
            if ($name !== $query) {
                // Additional names are never promoted by a suggestion's can value.
                $suggestions[$name] = $name;
                continue;
            }
            // Unknown fields can alter semantics, e.g. provisional suggestions.
            // Never ignore them when deciding whether this exact name was checked.
            if ($keys !== ['can','name'] || $record->can !== 'register' || $checked !== null) {
                $partial=true; continue;
            }
            $checked = true;
        }
        if (!$header || $checked !== true) $partial=true;
        return self::result($query,$scope,$at,$partial?'partial':'checked',
            $partial?'availability_unverified':'registration_available',!$partial,array_values($suggestions));
    }

    /** No retry and no query/credential/body retained in an exception. */
    public static function read(HttpTransport $transport, #[\SensitiveParameter] ProviderCredential $credential,
        string $query, int $at): array
    {
        $query = self::name($query);
        $account = $credential->accountIdentity;
        if ($credential->providerId !== 'twenty-i' || $at < 0 || $account === '' || strlen($account) > 128
            || preg_match('/\A[a-zA-Z0-9_-]+\z/', $account) !== 1) {
            throw new InvalidArgumentException('Invalid provider search context.');
        }
        $scope = 'twenty-i|'.$account.'|domains.search';
        try { $secret=$credential->secret(); }
        catch (Throwable) { throw new InvalidArgumentException('Provider credentials are unavailable.'); }
        if ($secret === '' || strlen($secret)>4096 || preg_match('/[\x00-\x1f\x7f]/',$secret)) {
            throw new InvalidArgumentException('Provider credentials are unavailable.');
        }
        $descriptor=self::request($query);
        $request=new TransportRequest('GET',$descriptor->url,
            ['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'],null,self::TIMEOUT_SECONDS);
        unset($secret,$descriptor);
        try { $response=$transport->send($request); }
        catch (TransportException $e) {
            return self::result($query,$scope,$at,'unavailable','transport_'.$e->kind);
        } catch (Throwable) { return self::result($query,$scope,$at,'unavailable','transport_unknown'); }
        finally { unset($request); }
        return self::project($response,$query,$at,$scope);
    }
}
