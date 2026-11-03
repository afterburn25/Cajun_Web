<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI\Operations;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportRequest};
use CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsObservation;
use InvalidArgumentException;

require_once __DIR__.'/../twentyi-document-roots/DocumentRootsObservation.php';

/**
 * Owner reference34e713 L432-441: change one existing attached hostname's root.
 * This request shape proves neither attachment, platform eligibility, directory
 * existence nor filesystem confinement. The caller must establish current
 * authority and retain a separate complete document-roots readback.
 * No setter acknowledgement schema is documented.
 */
final class DocumentRootChange
{
    public const OPERATION = 'packages.documentroot';
    public const MAX_PATH_BYTES = 1024;
    public const MAX_BODY_BYTES = 8192;

    /** Local writable subset only; do not normalize or infer an empty/root value. */
    public static function path(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > self::MAX_PATH_BYTES
            || preg_match('~\A[A-Za-z0-9_-][A-Za-z0-9._-]*(?:/[A-Za-z0-9_-][A-Za-z0-9._-]*)*\z~', $value) !== 1) {
            throw new InvalidArgumentException('Use a nonempty relative folder path with supported ASCII segments.');
        }
        return (string) $value;
    }

    /** These detached fields are a typed request, never current account authority. */
    public static function params(array $params): array
    {
        $keys = array_keys($params); sort($keys);
        if ($keys !== ['name','packageId','path']) {
            throw new InvalidArgumentException('Document root change requires exactly a package, hostname and folder path.');
        }
        if (!is_int($params['packageId']) || $params['packageId'] < 1 || $params['packageId'] > 999999999999999999) {
            throw new InvalidArgumentException('Select an allocated hosting package.');
        }
        if (!DocumentRootsObservation::validName($params['name'])) {
            throw new InvalidArgumentException('Select an exact supported attached hostname.');
        }
        return ['packageId'=>(int)$params['packageId'], 'name'=>(string)$params['name'], 'path'=>self::path($params['path'])];
    }

    public static function request(ProviderOperation $operation): TransportRequest
    {
        if ($operation->name !== self::OPERATION) throw new InvalidArgumentException('Unsupported document root operation.');
        $params = self::params($operation->params);
        $body = json_encode(['update'=>[$params['name']=>$params['path']]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($body) > self::MAX_BODY_BYTES) throw new InvalidArgumentException('Document root request exceeds the bounded size.');
        return new TransportRequest('POST', 'https://api.20i.com/package/'.$params['packageId'].'/web/updateDocRoot',
            ['Content-Type'=>'application/json','Accept'=>'application/json'], $body, 15.0);
    }

    /** Only the exact route and canonical one-host update object enter transport. */
    public static function wire(TransportRequest $request): void
    {
        if ($request->method !== 'POST'
            || preg_match('~\Ahttps://api\.20i\.com/package/([1-9][0-9]{0,17})/web/updateDocRoot\z~', $request->url, $match) !== 1
            || !is_string($request->body) || strlen($request->body) > self::MAX_BODY_BYTES) {
            throw new InvalidArgumentException('Unsupported document root request.');
        }
        try { $body = json_decode($request->body, true, 4, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new InvalidArgumentException('Unsupported document root body.'); }
        if (!is_array($body) || array_keys($body) !== ['update'] || !is_array($body['update']) || count($body['update']) !== 1) {
            throw new InvalidArgumentException('Unsupported document root body.');
        }
        $name = array_key_first($body['update']);
        $params = self::params(['packageId'=>(int)$match[1], 'name'=>$name, 'path'=>$body['update'][$name]]);
        $expected = json_encode(['update'=>[$params['name']=>$params['path']]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        // Duplicate decoded names/properties, alternate escaping, lists and extra
        // whitespace cannot survive equality with the sole canonical object.
        if ($request->body !== $expected) throw new InvalidArgumentException('Unsupported document root body.');
    }
}
