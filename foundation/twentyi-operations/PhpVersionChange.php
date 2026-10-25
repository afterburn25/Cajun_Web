<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportRequest};
use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersion;
use InvalidArgumentException;

require_once __DIR__.'/../twentyi-package-php/PackagePhpVersion.php';

/** Owner reference34e713 L2491-2498: one string setter; acknowledgement is unspecified. */
final class PhpVersionChange
{
    public const OPERATION = 'packages.phpversion';

    /**
     * Validates wire shape only. The application must independently bind the value
     * to freshly observed package options and current owner/client authority.
     */
    public static function params(array $params): array
    {
        $keys = array_keys($params);
        sort($keys);
        if ($keys !== ['packageId','value']) {
            throw new InvalidArgumentException('PHP changes require exactly a package and version value.');
        }
        if (!is_int($params['packageId']) || $params['packageId'] < 1 || $params['packageId'] > 999999999999999999) {
            throw new InvalidArgumentException('Select an allocated hosting package.');
        }
        $value = PackagePhpVersion::value($params['value']);
        return ['packageId'=>(int)$params['packageId'], 'value'=>(string)$value];
    }

    public static function request(ProviderOperation $operation): TransportRequest
    {
        if ($operation->name !== self::OPERATION) {
            throw new InvalidArgumentException('Unsupported PHP version operation.');
        }
        $params = self::params($operation->params);
        return new TransportRequest(
            'POST', 'https://api.20i.com/package/'.$params['packageId'].'/web/phpVersion',
            ['Content-Type'=>'application/json','Accept'=>'application/json'],
            json_encode(['value'=>$params['value']], JSON_THROW_ON_ERROR), 15.0,
        );
    }

    /** Exact endpoint and canonical sole-field body; no version-list authority is implied. */
    public static function wire(TransportRequest $request): void
    {
        if ($request->method !== 'POST'
            || preg_match('~\Ahttps://api\.20i\.com/package/([1-9][0-9]{0,17})/web/phpVersion\z~', $request->url, $match) !== 1
            || !is_string($request->body) || strlen($request->body) > 1024) {
            throw new InvalidArgumentException('Unsupported PHP version request.');
        }
        try {
            $body = json_decode($request->body, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Unsupported PHP version body.');
        }
        if (!is_array($body) || array_keys($body) !== ['value']) {
            throw new InvalidArgumentException('Unsupported PHP version body.');
        }
        $params = self::params(['packageId'=>(int)$match[1], 'value'=>$body['value']]);
        $expectedBody = json_encode(['value'=>$params['value']], JSON_THROW_ON_ERROR);
        if ($request->body !== $expectedBody) {
            throw new InvalidArgumentException('Unsupported PHP version body.');
        }
    }
}
