<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailCompose;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One bounded outbound attachment value. The MIME token is canonical adapter-
 * supplied metadata, not a claim that content inspection verified its type.
 * This model grants no file, sender, recipient, persistence or transport access.
 */
final readonly class DraftAttachment
{
    public const MAX_BYTES = 262144;
    public const MAX_FILES = 4;
    public const MAX_TOTAL_BYTES = 524288;

    public string $name;
    public string $mime;
    public string $sha256;
    private string $bytes;

    public function __construct(string $name, string $mime, #[\SensitiveParameter] string $bytes)
    {
        if (strlen($name)<1 || strlen($name)>180 || trim($name)!==$name
            || !preg_match('/\A[\x20-\x7E]+\z/D', $name)
            || preg_match('/["\\\\\/:;?*<>|]/', $name) || $name==='.' || $name==='..') {
            throw new InvalidArgumentException('Attachment name is not canonical.');
        }
        if (strlen($mime)>100 || !preg_match('/\A[a-z0-9][a-z0-9!#$&^_+.-]*\/[a-z0-9][a-z0-9!#$&^_+.-]*\z/D', $mime)) {
            throw new InvalidArgumentException('Attachment type is not a canonical MIME token.');
        }
        if ($bytes==='' || strlen($bytes)>self::MAX_BYTES) {
            throw new InvalidArgumentException('Attachment size is outside the allowed bound.');
        }
        $this->name=$name;
        $this->mime=$mime;
        $this->bytes=$bytes;
        $this->sha256=hash('sha256',$bytes);
    }

    /** Explicit private-content boundary for a later MIME/storage adapter. */
    public function bytes(): string { return $this->bytes; }

    /** Ordered metadata for immutable fingerprint binding; contains no content. */
    public function descriptor(): array
    {
        return ['name'=>$this->name,'mime'=>$this->mime,'sha256'=>$this->sha256,'bytes'=>strlen($this->bytes)];
    }

    /** @param list<DraftAttachment> $list @return list<DraftAttachment> */
    public static function list(array $list): array
    {
        if (!array_is_list($list) || count($list)>self::MAX_FILES) {
            throw new InvalidArgumentException('Attachment list is not canonical.');
        }
        $total=0;
        $names=[];
        foreach ($list as $item) {
            if (!$item instanceof self) throw new InvalidArgumentException('Attachment list is not canonical.');
            $key=strtolower($item->name);
            if (isset($names[$key])) throw new InvalidArgumentException('Attachment names must be unique.');
            $names[$key]=true;
            $total+=strlen($item->bytes);
        }
        if ($total>self::MAX_TOTAL_BYTES) throw new InvalidArgumentException('Attachment total exceeds the allowed bound.');
        return $list;
    }

    /** @param list<DraftAttachment> $list @return list<array{name:string,mime:string,sha256:string,bytes:int}> */
    public static function descriptors(array $list): array
    {
        return array_map(static fn(self $item):array=>$item->descriptor(), self::list($list));
    }
}
