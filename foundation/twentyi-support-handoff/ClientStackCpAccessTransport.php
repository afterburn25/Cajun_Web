<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
foreach (['HttpTransport','TransportRequest','TransportResponse','TransportException'] as $file) require_once __DIR__.'/../provider/'.$file.'.php';
foreach (['EndpointPolicy','EndpointResolver','ProbeFailure'] as $file) require_once __DIR__.'/../mail-transport/'.$file.'.php';
require_once __DIR__.'/../twentyi-stackcp-access/StackCpAccessBinding.php';
require_once __DIR__.'/../twentyi-stackcp-access/StackCpAccessJson.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver};

/** Only the two fixed client access reads. No token, session or access mutation is admitted. */
final class ClientStackCpAccessTransport implements HttpTransport
{
    public const MAX_RESPONSE_BYTES = 131072;
    private \Closure $authorize;
    private string $address;
    public function __construct(private readonly string $phase, private readonly int $packageId,
        private readonly string $stackIdentity, string $address, callable $authorize)
    {
        self::url($phase,$packageId,$stackIdentity);
        $this->address = EndpointPolicy::addresses([$address])[0];
        $this->authorize = \Closure::fromCallable($authorize);
    }
    public static function url(string $phase,int $packageId,string $stackIdentity): string
    {
        if ($packageId < 1 || $packageId > 2147483647) self::deny();
        \CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding::identity($stackIdentity);
        return 'https://api.20i.com'.match ($phase) {
            'membership'=>'/package/'.$packageId.'/stackUserList',
            'grants'=>'/reseller/*/stackUser/'.substr($stackIdentity,11).'/grants',
            default=>throw new \InvalidArgumentException('Invalid client StackCP read phase.'),
        };
    }
    public static function resolve(?callable $lookup=null): string
    { return EndpointPolicy::addresses(EndpointResolver::resolve('api.20i.com',$lookup))[0]; }

    private function validate(#[\SensitiveParameter] TransportRequest $request): void
    {
        if ($request->url !== self::url($this->phase,$this->packageId,$this->stackIdentity)
            || $request->method !== 'GET' || $request->body !== null || $request->timeoutSeconds > 15
            || array_keys($request->headers) !== ['Authorization','Accept']
            || $request->headers['Accept'] !== 'application/json') self::deny();
        $authorization = $request->headers['Authorization'];
        if (!is_string($authorization) || !str_starts_with($authorization,'Bearer ')) self::deny();
        $encoded = substr($authorization,7); $secret = base64_decode($encoded,true);
        if (!is_string($secret) || $secret === '' || strlen($secret) > 4096 || base64_encode($secret) !== $encoded
            || preg_match('//u',$secret) !== 1 || preg_match('/[\x00-\x20\x7f]/',$secret)) self::deny();
    }
    private static function deny(): never { throw new \InvalidArgumentException('Unsupported StackCP request.'); }

    public function send(#[\SensitiveParameter] TransportRequest $request): TransportResponse
    {
        $this->validate($request); ($this->authorize)();
        if (!function_exists('curl_init')) throw new TransportException('unavailable');
        $curl = curl_init($request->url);
        if ($curl === false) throw new TransportException('unavailable');
        $host='api.20i.com'; $port=443;
        $ip = str_contains($this->address,':') ? '['.$this->address.']' : $this->address;
        $body = ''; $headerBytes = 0; $limited = false;
        try {
            $headers = ['Authorization: '.$request->headers['Authorization'],'Accept: application/json','Expect:'];
            $options = [CURLOPT_HTTPGET=>true, CURLOPT_HTTPHEADER=>$headers];
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
            // Bodies remain inside the bounded access parser; no response headers escape this boundary.
            return new TransportResponse($status,[],$body);
        } finally { unset($options,$headers,$body,$curl); }
    }
    public function __debugInfo(): array { return ['phase'=>$this->phase]; }
    public function __serialize(): never { throw new \LogicException('StackCP transport cannot be serialized.'); }
}
