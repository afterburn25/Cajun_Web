<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIHostingDns;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Immutable local review document. It has no transport, URL, wire payload or provider-result method. */
final readonly class HostingDnsProposal
{
    public array $data;
    public string $json;
    public string $sha256;

    /** The caller supplies a protected credential only for echo checks; it is never retained. */
    public function __construct(HostingDnsContext $context, array $records, array $deleteIds, #[\SensitiveParameter] string $credential)
    {
        if ($credential === '' || strlen($credential) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $credential)) {
            throw new \InvalidArgumentException('Protected credentials are unavailable for proposal review.');
        }
        try {
            $context = HostingDnsContext::validated($context);
            if (!array_is_list($records) || !array_is_list($deleteIds) || count($records) + count($deleteIds) < 1
                || count($records) + count($deleteIds) > 32) throw new \InvalidArgumentException('Review between 1 and 32 local DNS changes.');
            $values = [$context->data['package_domain_id'], $context->data['zone_name']];
            $projected = []; $seen = []; $hosts = [];
            foreach ($records as $record) {
                if (!$record instanceof HostingDnsRecord) throw new \InvalidArgumentException('A DNS proposal requires typed records.');
                $copy = new HostingDnsRecord($record->type, $record->fields);
                $item = ['type'=>$copy->type, 'fields'=>$copy->fields];
                $fingerprint = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if (isset($seen[$fingerprint])) throw new \InvalidArgumentException('Remove duplicate DNS proposal records.');
                $seen[$fingerprint] = true; $host = $copy->fields['host'];
                if ($host !== '@' && strlen($host.'.'.$context->data['zone_name']) > 253) throw new \InvalidArgumentException('The proposed name exceeds the local DNS name limit.');
                if (isset($hosts[$host]) && ($copy->type === 'CNAME' || in_array('CNAME', $hosts[$host], true))) {
                    throw new \InvalidArgumentException('A CNAME cannot share a proposed name with another record.');
                }
                $hosts[$host][] = $copy->type; $projected[] = $item;
                array_push($values, ...array_values($copy->fields));
            }
            $deletes = [];
            foreach ($deleteIds as $id) {
                $id = HostingDnsContext::opaqueId($id);
                if (in_array($id, $deletes, true)) throw new \InvalidArgumentException('Remove duplicate DNS deletion references.');
                $deletes[] = $id; $values[] = $id;
            }
            $encoded = base64_encode($credential);
            foreach ($values as $value) if (str_contains($value, $credential) || str_contains($value, $encoded)) {
                throw new \InvalidArgumentException('Private credential material is not allowed in a DNS proposal.');
            }
            $this->data = ['format'=>'cwp-hosting-dns-proposal/v1', 'context'=>$context->data, 'context_sha256'=>$context->sha256,
                'records'=>$projected, 'delete_references'=>$deletes, 'execution'=>'unavailable'];
            $this->json = json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (strlen($this->json) > 32768) throw new \InvalidArgumentException('The local DNS proposal is too large.');
            $this->sha256 = hash('sha256', $this->json);
        } finally { unset($credential, $encoded, $values); }
    }
}
