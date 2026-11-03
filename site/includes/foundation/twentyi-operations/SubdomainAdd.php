<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyI\Operations;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportRequest};
use CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsObservation;
use InvalidArgumentException;

require_once __DIR__.'/../twentyi-document-roots/DocumentRootsObservation.php';

/** Owner reference34e713 L866-881. Add one child only; no documented POST acknowledgement. */
final class SubdomainAdd
{
    public const OPERATION = 'packages.subdomainadd';

    /** Parent attachment and current owner authority must be proved by the dedicated native caller. */
    public static function params(array $params): array
    {
        $keys = array_keys($params); sort($keys);
        if ($keys !== ['packageId','parentDomain','subdomain']) {
            throw new InvalidArgumentException('Subdomain addition requires exactly a package, parent and new name.');
        }
        if (!is_int($params['packageId']) || $params['packageId'] < 1 || $params['packageId'] > 999999999999999999) {
            throw new InvalidArgumentException('Select an allocated hosting package.');
        }
        if (!DocumentRootsObservation::validName($params['parentDomain']) || !DocumentRootsObservation::validName($params['subdomain'])) {
            throw new InvalidArgumentException('Use canonical lowercase ASCII website names.');
        }
        $parent = $params['parentDomain']; $name = $params['subdomain'];
        if (!str_ends_with($name, '.'.$parent)) {
            throw new InvalidArgumentException('The new name must be a child of the selected attached parent.');
        }
        $label = substr($name, 0, -strlen('.'.$parent));
        if ($label === '' || str_contains($label, '.')) {
            throw new InvalidArgumentException('Add exactly one label below the selected attached parent.');
        }
        return ['packageId'=>(int)$params['packageId'],'parentDomain'=>(string)$parent,'subdomain'=>(string)$name];
    }

    public static function request(ProviderOperation $operation): TransportRequest
    {
        if ($operation->name !== self::OPERATION) throw new InvalidArgumentException('Unsupported subdomain operation.');
        $params = self::params($operation->params);
        return new TransportRequest('POST', 'https://api.20i.com/package/'.$params['packageId'].'/web/subdomains',
            ['Content-Type'=>'application/json','Accept'=>'application/json'], self::body($params['subdomain']), 15.0);
    }

    /** Fixed wire shape only: the provider body deliberately contains no parent or local authority. */
    public static function wire(TransportRequest $request): void
    {
        if ($request->method !== 'POST'
            || preg_match('~\Ahttps://api\.20i\.com/package/([1-9][0-9]{0,17})/web/subdomains\z~', $request->url) !== 1
            || !is_string($request->body) || strlen($request->body) > 8192) {
            throw new InvalidArgumentException('Unsupported subdomain request.');
        }
        try { $body = json_decode($request->body, true, 4, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new InvalidArgumentException('Unsupported subdomain body.'); }
        if (!is_array($body) || array_keys($body) !== ['add','rem'] || !is_array($body['add'])
            || !array_is_list($body['add']) || count($body['add']) !== 1 || $body['rem'] !== []
            || !DocumentRootsObservation::validName($body['add'][0]) || substr_count($body['add'][0], '.') < 2
            || $request->body !== self::body($body['add'][0])) {
            throw new InvalidArgumentException('Unsupported subdomain body.');
        }
    }

    private static function body(string $name): string
    {
        return json_encode(['add'=>[$name],'rem'=>[]], JSON_THROW_ON_ERROR);
    }
}
