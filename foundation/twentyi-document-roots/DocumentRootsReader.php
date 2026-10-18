<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDocumentRoots;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};

/** Explicit fixed GET through a caller-supplied transport; not registered in the shipping application. */
final class DocumentRootsReader
{
    public const MAX_BODY_BYTES = 65536;

    public static function read(HttpTransport $transport, #[\SensitiveParameter] ProviderCredential $credential,
        DocumentRootsBinding $binding, int $at): DocumentRootsObservation
    {
        $binding = DocumentRootsBinding::validated($binding);
        if ($at < 0 || $credential->providerId !== 'twenty-i' || $credential->accountIdentity !== $binding->data['package']['profile_id']) {
            throw new \InvalidArgumentException('Invalid document roots account.');
        }
        try { $secret = $credential->secret(); }
        catch (\Throwable) { throw new \InvalidArgumentException('Provider credentials are unavailable.'); }
        try {
            if ($secret === '' || strlen($secret) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $secret)) {
                throw new \InvalidArgumentException('Provider credentials are unavailable.');
            }
            $request = new TransportRequest('GET', 'https://api.20i.com/package/'.$binding->data['package']['package_id'].'/web/documentRoots',
                ['Authorization'=>'Bearer '.base64_encode($secret), 'Accept'=>'application/json'], null, 15.0);
            try { $response = $transport->send($request); }
            catch (TransportException $e) { return self::unavailable($binding, 'transport_'.$e->kind, $at); }
            catch (\Throwable) { return self::unavailable($binding, 'transport_unknown', $at); }
            return self::project($response, $binding, $secret, $at);
        } finally { unset($secret, $request); }
    }

    private static function unavailable(DocumentRootsBinding $binding, string $reason, int $at): DocumentRootsObservation
    {
        return new DocumentRootsObservation($binding, 'unavailable', [], $reason, $at);
    }

    /** Used only for already-rejected syntax/type classification; never accepts a decoded whole map. */
    private static function rejectedBody(string $body): string
    {
        try { json_decode($body, false, 8, JSON_THROW_ON_ERROR); return 'shape_unsupported'; }
        catch (\JsonException) { return 'malformed_body'; }
    }

    private static function project(TransportResponse $response, DocumentRootsBinding $binding,
        #[\SensitiveParameter] string $secret, int $at): DocumentRootsObservation
    {
        if ($response->status !== 200) return self::unavailable($binding, match (true) {
            in_array($response->status, [401,403], true)=>'authentication', $response->status===429=>'rate_limited',
            $response->status>=500=>'upstream_unavailable', default=>'http_status',
        }, $at);
        if (strlen($response->body) > self::MAX_BODY_BYTES) return self::unavailable($binding, 'body_limit', $at);
        $body = trim($response->body, " \t\r\n");
        if (preg_match('/\A\[[ \t\r\n]*\]\z/', $body)) return self::unavailable($binding, 'provider_empty_array', $at);
        if (preg_match('/\A\{[ \t\r\n]*\}\z/', $body)) return new DocumentRootsObservation($binding, 'complete', [], '', $at);
        if (!str_starts_with($body, '{')) return self::unavailable($binding, self::rejectedBody($body), $at);

        // Parse only the documented flat string map. Decode one key/value token at a time,
        // so duplicate decoded names are rejected before a whole-object decoder could erase them.
        $string = '"(?:[^"\\\\\x00-\x1f]|\\\\(?:["\\\\/bfnrt]|u[0-9a-fA-F]{4}))*"';
        $pattern = '~\G[ \t\r\n]*(?<key>'.$string.')[ \t\r\n]*:[ \t\r\n]*(?<value>'.$string.')[ \t\r\n]*(?<end>[,}])~';
        $offset = 1; $entries = []; $seen = []; $encoded = base64_encode($secret);
        while (true) {
            if (preg_match($pattern, $body, $m, 0, $offset) !== 1) return self::unavailable($binding, self::rejectedBody($body), $at);
            try { $name = json_decode($m['key'], false, 2, JSON_THROW_ON_ERROR); $path = json_decode($m['value'], false, 2, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { return self::unavailable($binding, 'malformed_body', $at); }
            if (isset($seen[$name])) return self::unavailable($binding, 'duplicate_name', $at);
            $seen[$name] = true;
            if (str_contains($name, $secret) || str_contains($name, $encoded) || str_contains($path, $secret) || str_contains($path, $encoded)) {
                return self::unavailable($binding, 'credential_echo', $at);
            }
            if (!DocumentRootsObservation::validName($name) || !DocumentRootsObservation::validPath($path)) {
                return self::unavailable($binding, 'entry_unsupported', $at);
            }
            if (count($entries) >= DocumentRootsObservation::MAX_ENTRIES) return self::unavailable($binding, 'body_limit', $at);
            $entries[] = ['name'=>$name, 'path'=>$path]; $offset += strlen($m[0]);
            if ($m['end'] === '}') {
                if (trim(substr($body, $offset), " \t\r\n") !== '') return self::unavailable($binding, self::rejectedBody($body), $at);
                return new DocumentRootsObservation($binding, 'complete', $entries, '', $at);
            }
        }
    }
}
