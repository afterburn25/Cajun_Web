<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** DNS is resolved once before connecting; the operating system owns its resolver timeout. */
final class EndpointResolver
{
    public static function resolve(string $host,?callable $lookup=null): array
    {
        $host=EndpointPolicy::host($host);$seen=[];$addresses=[];
        $lookup??=static fn(string $name)=>@dns_get_record($name,DNS_A|DNS_AAAA|DNS_CNAME);
        for ($depth=0;$depth<8;$depth++) {
            if (isset($seen[$host])) throw new ProbeFailure('connection');$seen[$host]=true;
            try {$records=$lookup($host);} catch (\Throwable) {throw new ProbeFailure('connection');}
            if (!is_array($records) || !$records || count($records)>64) throw new ProbeFailure('connection');
            $targets=[];
            foreach ($records as $record) {
                if (!is_array($record)) throw new ProbeFailure('protocol');
                if (($record['type']??'')==='A') $addresses[]=$record['ip']??null;
                elseif (($record['type']??'')==='AAAA') $addresses[]=$record['ipv6']??null;
                elseif (($record['type']??'')==='CNAME') {
                    if (!is_string($record['target']??null)) throw new ProbeFailure('protocol');
                    $name=EndpointPolicy::host(rtrim($record['target'],'.'));$targets[$name]=$name;
                }
            }
            if ($addresses) $addresses=EndpointPolicy::addresses($addresses);
            if (!$targets) return EndpointPolicy::addresses($addresses);
            if (count($targets)!==1) throw new ProbeFailure('protocol');$host=array_values($targets)[0];
        }
        throw new ProbeFailure('limit');
    }
}
