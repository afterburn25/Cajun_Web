<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
foreach (['HttpTransport','TransportRequest','TransportResponse','TransportException'] as $file) require_once __DIR__.'/../provider/'.$file.'.php';
foreach (['EndpointPolicy','EndpointResolver','ProbeFailure'] as $file) require_once __DIR__.'/../mail-transport/'.$file.'.php';
require_once __DIR__.'/StackCpDefaultBrand.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver};

/** Dedicated fixed SDK endpoints. This does not widen the generic provider transport. */
final class StackCpLaunchTransport implements HttpTransport
{
    public const MAX_RESPONSE_BYTES = 16384;
    private \Closure $authorize;
    private string $address;
    public function __construct(private readonly string $phase, string $address, callable $authorize)
    {
        self::endpoint($phase);
        $this->address = EndpointPolicy::addresses([$address])[0];
        $this->authorize = \Closure::fromCallable($authorize);
    }
    public static function endpoint(string $phase): array
    {
        return match ($phase) {
            'brand'=>['GET','api.20i.com',443,'/reseller/*/customisations'],
            'token'=>['POST','auth-api.20i.com',3000,'/login/authenticate'],
            'session'=>['POST','www.stackcp.com',443,'/login/implicitBranded'],
            default=>throw new \InvalidArgumentException('Invalid StackCP phase.'),
        };
    }
    public static function url(string $phase): string
    {
        [, $host, $port, $path] = self::endpoint($phase);
        return 'https://'.$host.($port === 443 ? '' : ':'.$port).$path;
    }
    public static function resolve(string $phase, ?callable $lookup=null): string
    { return EndpointPolicy::addresses(EndpointResolver::resolve(self::endpoint($phase)[1],$lookup))[0]; }

    private function validate(#[\SensitiveParameter] TransportRequest $request): void
    {
        [$method] = self::endpoint($this->phase);
        if ($request->url !== self::url($this->phase) || $request->method !== $method
            || $request->timeoutSeconds > 15
            || array_keys($request->headers) !== ($method === 'GET' ? ['Authorization','Accept'] : ['Authorization','Accept','Content-Type'])
            || $request->headers['Accept'] !== 'application/json') self::deny();
        $authorization = $request->headers['Authorization'];
        if (!is_string($authorization) || !str_starts_with($authorization,'Bearer ')) self::deny();
        $encoded = substr($authorization,7); $secret = base64_decode($encoded,true);
        if (!is_string($secret) || $secret === '' || strlen($secret) > 4096 || base64_encode($secret) !== $encoded
            || preg_match('//u',$secret) !== 1 || preg_match('/[\x00-\x20\x7f]/',$secret)) self::deny();
        if ($method === 'GET') { if ($request->body !== null) self::deny(); return; }
        if ($request->headers['Content-Type'] !== 'application/json' || !is_string($request->body)
            || strlen($request->body) > StackCpDefaultBrand::MAX_BODY_BYTES + 10) self::deny();
        if ($this->phase === 'token') {
            try { $value = json_decode($request->body,true,4,JSON_THROW_ON_ERROR); }
            catch (\JsonException) { self::deny(); }
            if (!is_array($value) || array_keys($value) !== ['grant_type','scope']
                || $value['grant_type'] !== 'client_credentials' || !is_string($value['scope'])
                || preg_match('/\Astack-user:[1-9][0-9]{0,17}\z/',$value['scope']) !== 1
                || json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) !== $request->body) self::deny();
        } else {
            try { StackCpDefaultBrand::validateSessionBody($request->body); }
            catch (StackCpLaunchFailure) { self::deny(); }
        }
    }
    private static function deny(): never { throw new \InvalidArgumentException('Unsupported StackCP request.'); }

    public function send(#[\SensitiveParameter] TransportRequest $request): TransportResponse
    {
        $this->validate($request); ($this->authorize)();
        if (!function_exists('curl_init')) throw new TransportException('unavailable');
        $curl = curl_init($request->url);
        if ($curl === false) throw new TransportException('unavailable');
        [, $host, $port] = self::endpoint($this->phase);
        $ip = str_contains($this->address,':') ? '['.$this->address.']' : $this->address;
        $body = ''; $headerBytes = 0; $limited = false;
        try {
            $headers = ['Authorization: '.$request->headers['Authorization'],'Accept: application/json','Expect:'];
            $options = $request->method === 'POST'
                ? [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$request->body,
                    CURLOPT_HTTPHEADER=>array_merge($headers,['Content-Type: application/json'])]
                : [CURLOPT_HTTPGET=>true, CURLOPT_HTTPHEADER=>$headers];
            $options += [
                CURLOPT_RETURNTRANSFER=>false, CURLOPT_HEADER=>false,
                CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_PROXY=>'', CURLOPT_PRE_PROXY=>'', CURLOPT_NOPROXY=>'*',
                CURLOPT_NETRC=>CURL_NETRC_IGNORED, CURLOPT_HTTPAUTH=>CURLAUTH_NONE,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_SSLVERSION=>CURL_SSLVERSION_TLSv1_2,
                CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ip],
                CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1, CURLOPT_HTTP09_ALLOWED=>false,
                CURLOPT_FRESH_CONNECT=>true, CURLOPT_FORBID_REUSE=>true, CURLOPT_NOSIGNAL=>true,
                CURLOPT_CONNECTTIMEOUT_MS=>5000, CURLOPT_TIMEOUT_MS=>(int)ceil($request->timeoutSeconds*1000),
                CURLOPT_LOW_SPEED_LIMIT=>1, CURLOPT_LOW_SPEED_TIME=>5, CURLOPT_BUFFERSIZE=>16384,
                CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$headerBytes,&$limited): int {
                    $headerBytes += strlen($line);
                    if ($headerBytes > 16384) { $limited=true; return 0; }
                    return strlen($line); // Discard Set-Cookie and all other response headers.
                },
                CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$body,&$limited): int {
                    if (strlen($body)+strlen($chunk) > self::MAX_RESPONSE_BYTES) { $limited=true; return 0; }
                    $body .= $chunk; return strlen($chunk);
                },
            ];
            if (!curl_setopt_array($curl,$options)) throw new TransportException('unavailable');
            ($this->authorize)();
            $ok = curl_exec($curl); $error = curl_errno($curl); $status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
            ($this->authorize)();
            if ($limited) throw new TransportException('protocol');
            if ($ok === false) throw new TransportException(match ($error) {
                CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST=>'connect',
                CURLE_SSL_CACERT, CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM,
                CURLE_SSL_CIPHER, CURLE_SSL_CACERT_BADFILE, 83=>'tls',
                CURLE_OPERATION_TIMEDOUT=>'timeout', default=>'unknown',
            });
            if ($status < 100 || $status > 599) throw new TransportException('protocol');
            // Bodies remain inside the launch parser; no response headers escape this boundary.
            return new TransportResponse($status,[],$body);
        } finally { unset($options,$headers,$body,$curl); }
    }
    public function __debugInfo(): array { return ['phase'=>$this->phase]; }
    public function __serialize(): never { throw new \LogicException('StackCP transport cannot be serialized.'); }
}
