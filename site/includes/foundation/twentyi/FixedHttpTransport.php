<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver,ProbeFailure};
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportException,TransportRequest,TransportResponse};

/** Fixed HTTPS transport. No configurable URL, proxy, CA, redirect or private-address bypass. */
final class FixedHttpTransport implements HttpTransport
{
    private const HEADER_LIMIT = 16384;
    /** Canonical write bodies are bounded to 8KiB by the request builder. */
    private const REQUEST_BODY_LIMIT = 8192;
    private const URL_BASE = 'https://api.20i.com/';
    private string $address;
    private \Closure $authorize;

    /** DNS precedes credential resolution; the host resolver owns its blocking DNS timeout. */
    public static function resolve(?callable $lookup=null): string
    {
        try {
            return EndpointPolicy::addresses(EndpointResolver::resolve('api.20i.com',$lookup))[0];
        } catch (\InvalidArgumentException|ProbeFailure) {
            throw new TransportException(TransportException::KIND_UNAVAILABLE);
        }
    }

    /** Caller resolves/validates the whole DNS answer set before resolving any credential. */
    public function __construct(string $address, callable $authorize)
    {
        $this->address = EndpointPolicy::addresses([$address])[0];
        $this->authorize = \Closure::fromCallable($authorize);
    }

    private function validate(#[\SensitiveParameter] TransportRequest $request): void
    {
        if ($request->timeoutSeconds > 15) {
            throw new \InvalidArgumentException('Unsupported provider request.');
        }
        if ($request->method === 'GET') {
            if ((!in_array($request->url, [TwentyIAdapter::URL, 'https://api.20i.com/cloud_server', 'https://api.20i.com/domain', 'https://api.20i.com/domain-period', 'https://api.20i.com/reseller/*/cloudProviders'], true) && !$this->isDomainSearch($request->url) && !$this->isDomainContract($request->url) && !$this->isPackagePhpVersion($request->url) && !$this->isDomainExpiry($request->url) && !$this->isDocumentRoots($request->url) && !$this->isPackageForceSsl($request->url) && !$this->isDirectoryIndexing($request->url) && !$this->isWordpressInstalled($request->url)) || $request->body !== null
                || array_keys($request->headers) !== ['Authorization','Accept']
                || $request->headers['Accept'] !== 'application/json') {
                throw new \InvalidArgumentException('Unsupported provider probe request.');
            }
        } elseif ($request->method === 'POST') {
            // Only the documented write endpoints may ever carry a body:
            // a fixed-host URL on the catalogued reseller paths, a
            // bounded JSON body and three fixed headers.
            $path = str_starts_with($request->url, self::URL_BASE) ? substr($request->url, strlen(self::URL_BASE)) : '';
            $domainRenewal = $path === 'reseller/*/renewDomain';
            $forceSsl = preg_match('~\Apackage/[1-9][0-9]{0,17}/web/forceSSL\z~', $path) === 1;
            $mailbox = str_starts_with($path, 'package/') && !$forceSsl;
            $cloudWebsite = str_starts_with($path, 'cloud_server/');
            if ((!preg_match('/^reseller\/(?:\*|[1-9][0-9]{0,17})\/(addWeb|updatePackage|deleteWeb)$/D', $path) && !$mailbox && !$cloudWebsite && !$forceSsl && !$domainRenewal)
                || !is_string($request->body) || $request->body === ''
                || strlen($request->body) > self::REQUEST_BODY_LIMIT
                || array_keys($request->headers) !== ['Authorization','Accept','Content-Type']
                || $request->headers['Accept'] !== 'application/json'
                || $request->headers['Content-Type'] !== 'application/json') {
                throw new \InvalidArgumentException('Unsupported provider write request.');
            }
            if ($domainRenewal) {
                require_once __DIR__.'/../twentyi-inventory/InventoryDomain.php';
                require_once __DIR__.'/../twentyi-operations/DomainRenewal.php';
                \CajunWebPro\Foundation\TwentyI\Operations\DomainRenewal::wire($request);
            }
            if ($forceSsl) {
                require_once __DIR__.'/../twentyi-operations/ForceSslChange.php';
                \CajunWebPro\Foundation\TwentyI\Operations\ForceSslChange::wire($request);
            }
            if ($cloudWebsite) {
                require_once __DIR__.'/../twentyi-operations/CloudWebsiteCreate.php';
                \CajunWebPro\Foundation\TwentyI\Operations\CloudWebsiteCreate::wire($request);
            }
            if ($mailbox) {
                require_once __DIR__.'/../twentyi-operations/MailboxCreate.php';
                // This validates the exact creation body as well as the package/domain path.
                // No other package mutation or mail-object action is admitted here.
                \CajunWebPro\Foundation\TwentyI\Operations\MailboxCreate::wire($request);
            }
        } else {
            throw new \InvalidArgumentException('Unsupported provider request method.');
        }
        $this->validateAuthorization($request->headers['Authorization'] ?? null);
    }

    private function isDomainExpiry(string $url): bool
    {
        require_once __DIR__.'/../twentyi-inventory/InventoryDomain.php';
        require_once __DIR__.'/../twentyi-domain-expiry/DomainExpiry.php';
        require_once __DIR__.'/../twentyi-domain-expiry/DomainExpiryReader.php';
        return \CajunWebPro\Foundation\TwentyIDomainExpiry\DomainExpiryReader::acceptsUrl($url);
    }

    private function isDomainContract(string $url): bool
    {
        require_once __DIR__.'/../twentyi-inventory/InventoryDomain.php';
        require_once __DIR__.'/../twentyi-domain-contracts/DomainContract.php';
        return \CajunWebPro\Foundation\TwentyIDomainContracts\DomainContract::acceptsUrl($url);
    }

    private function isDomainSearch(string $url): bool
    {
        require_once __DIR__.'/../twentyi-inventory-reader/DomainSearch.php';
        $prefix=\CajunWebPro\Foundation\TwentyIInventoryReader\DomainSearch::URL_PREFIX;
        if (!str_starts_with($url,$prefix)) return false;
        $name=substr($url,strlen($prefix));
        try { return \CajunWebPro\Foundation\TwentyIInventoryReader\DomainSearch::name($name)===$name; }
        catch (\InvalidArgumentException) { return false; }
    }

    /** One exact allocated-package scalar read; no package path prefix authority. */
    private function isPackagePhpVersion(string $url): bool
    {
        return preg_match('~\Ahttps://api\.20i\.com/package/[1-9][0-9]{0,17}/web/phpVersion\z~',$url)===1;
    }

    /** One exact reported-flag GET; the documented setter is not admitted. */
    private function isPackageForceSsl(string $url): bool
    {
        return preg_match('~\Ahttps://api\.20i\.com/package/[1-9][0-9]{0,17}/web/forceSSL\z~',$url)===1;
    }

    /** One exact directory-listing GET; the documented setter is not admitted. */
    private function isDirectoryIndexing(string $url): bool
    {
        return preg_match('~\Ahttps://api\.20i\.com/package/[1-9][0-9]{0,17}/web/directoryIndexing\z~',$url)===1;
    }

    private function isWordpressInstalled(string $url): bool
    {
        return preg_match('~\Ahttps://api\.20i\.com/package/[1-9][0-9]{0,17}/web/wordpressIsInstalled\z~',$url)===1;
    }

    /** Only the documented fixed package map read, without a path or domain override. */
    private function isDocumentRoots(string $url): bool
    {
        return preg_match('~\Ahttps://api\.20i\.com/package/[1-9][0-9]{0,17}/web/documentRoots\z~',$url)===1;
    }

    private function validateAuthorization(mixed $authorization): void
    {
        if (!is_string($authorization) || !str_starts_with($authorization, 'Bearer ')
            || strlen($authorization) > 7 + 4 * (int)ceil(TwentyIAdapter::MAX_KEY_BYTES / 3)) {
            throw new \InvalidArgumentException('Invalid provider authorization.');
        }
        $encoded = substr($authorization,7);$decoded = base64_decode($encoded,true);
        if ($decoded === false || $decoded === '' || strlen($decoded) > TwentyIAdapter::MAX_KEY_BYTES
            || base64_encode($decoded) !== $encoded || preg_match('/[\x00-\x1f\x7f]/',$decoded)) {
            throw new \InvalidArgumentException('Invalid provider authorization.');
        }
    }

    public function send(#[\SensitiveParameter] TransportRequest $request): TransportResponse
    {
        $this->validate($request);
        ($this->authorize)();
        if (!function_exists('curl_init')) throw new TransportException(TransportException::KIND_UNAVAILABLE);
        $curl = curl_init($request->url);
        if ($curl === false) throw new TransportException(TransportException::KIND_UNAVAILABLE);
        $body = '';$headerBytes = 0;$limited = false;
        $ip = str_contains($this->address,':') ? '['.$this->address.']' : $this->address;
        try {
            $headers = ['Authorization: '.$request->headers['Authorization'],'Accept: application/json'];
            $options = $request->method === 'POST'
                ? [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $request->body, CURLOPT_HTTPHEADER => array_merge($headers,['Content-Type: application/json'])]
                : [CURLOPT_HTTPGET => true, CURLOPT_HTTPHEADER => $headers];
            $options += [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_PROXY => '',
                CURLOPT_PRE_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_NETRC => CURL_NETRC_IGNORED,
                CURLOPT_HTTPAUTH => CURLAUTH_NONE,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_RESOLVE => ['api.20i.com:443:'.$ip],
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTP09_ALLOWED => false,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_CONNECTTIMEOUT_MS => 5000,
                CURLOPT_TIMEOUT_MS => (int)ceil($request->timeoutSeconds * 1000),
                CURLOPT_LOW_SPEED_LIMIT => 1,
                CURLOPT_LOW_SPEED_TIME => 5,
                CURLOPT_BUFFERSIZE => 16384,
                CURLOPT_HEADERFUNCTION => static function($handle,string $line) use (&$headerBytes,&$limited): int {
                    $headerBytes += strlen($line);
                    if ($headerBytes > self::HEADER_LIMIT) {$limited=true;return 0;}
                    return strlen($line); // Do not retain or return remote headers.
                },
                CURLOPT_WRITEFUNCTION => static function($handle,string $chunk) use (&$body,&$limited): int {
                    if (strlen($body)+strlen($chunk) > TwentyIAdapter::MAX_BODY_BYTES) {$limited=true;return 0;}
                    $body .= $chunk;return strlen($chunk);
                },
            ];
            if (!curl_setopt_array($curl,$options)) throw new TransportException(TransportException::KIND_UNAVAILABLE);
            ($this->authorize)();
            $ok = curl_exec($curl);
            $error = curl_errno($curl);$status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
            // This callback may refuse changed/expired application authority; no body is returned then.
            ($this->authorize)();
            if ($limited) throw new TransportException(TransportException::KIND_PROTOCOL);
            if ($ok === false) {
                $kind = match ($error) {
                    CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST => TransportException::KIND_CONNECT,
                    // PHP exposes certificate failure as CURLE_SSL_CACERT (libcurl code 60).
                    // Issuer error 83 has no PHP constant on some supported builds.
                    CURLE_SSL_CACERT, CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM,
                    CURLE_SSL_CIPHER, CURLE_SSL_CACERT_BADFILE, 83 => TransportException::KIND_TLS,
                    CURLE_OPERATION_TIMEDOUT => TransportException::KIND_TIMEOUT,
                    default => TransportException::KIND_PROTOCOL,
                };
                throw new TransportException($kind); // Never use curl_error() or provider text.
            }
            if ($status < 100 || $status > 599) throw new TransportException(TransportException::KIND_PROTOCOL);
            // Refuse literal echoes of this request's credentials instead of returning them.
            // This is not a general-purpose response redaction or disclosure guarantee for other data.
            $encoded = substr($request->headers['Authorization'],7);
            $secret = base64_decode($encoded,true);
            if (str_contains($body,$encoded) || str_contains($body,$secret)) {
                throw new TransportException(TransportException::KIND_PROTOCOL);
            }
            return new TransportResponse($status,[],$body);
        } finally {
            // No reusable cookie/auth/connection handle survives this explicit probe.
            unset($options,$curl,$body,$encoded,$secret);
        }
    }
}
