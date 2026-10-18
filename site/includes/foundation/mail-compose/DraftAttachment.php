<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailCompose;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One bounded outbound attachment carried by a saved draft or an immutable
 * submission. Wire names stay printable ASCII; no filesystem, sender
 * identity or recipient authority is implied here.
 */
final readonly class DraftAttachment
{
    public const MAX_BYTES = 262144;
    public const MAX_FILES = 4;
    public const MAX_TOTAL_BYTES = 524288;

    public string $name;
    public string $mime;
    public string $sha256;

    public function __construct(string $name,string $mime,#[\SensitiveParameter] public string $bytes)
    {
        // Printable ASCII only: the wire name must survive unchanged through
        // maintained MIME encoding without folding or encoded-word surprises.
        $clean=preg_replace('/[\x00-\x1F\x7F-\xFF"\\\\\/:;?*<>|]/','',$name);
        $clean=trim(substr($clean,0,180));
        if($clean===''||$clean==='.'||$clean==='..')throw new InvalidArgumentException('Attachment names must be plain printable text.');
        $this->name=$clean;
        if(!preg_match('/\A[A-Za-z0-9!#$&^_+.-]+\/[A-Za-z0-9!#$&^_+.-]+\z/D',$mime)||strlen($mime)>100) {
            throw new InvalidArgumentException('Attachment type is not a supported MIME token.');
        }
        $this->mime=$mime;
        if($bytes===''||strlen($bytes)>self::MAX_BYTES)throw new InvalidArgumentException('Attachments must be between 1 byte and 256 KiB.');
        $this->sha256=hash('sha256',$bytes);
    }

    /** Immutable descriptor bound into submission fingerprints; contains no bytes. */
    public function descriptor():array
    {
        return ['name'=>$this->name,'mime'=>$this->mime,'sha256'=>$this->sha256,'bytes'=>strlen($this->bytes)];
    }

    /** @param list<DraftAttachment> $list */
    public static function list(array $list):array
    {
        if(count($list)>self::MAX_FILES)throw new InvalidArgumentException('A draft supports at most 4 attachments.');
        $total=0;
        foreach($list as $item) {
            if(!$item instanceof self)throw new InvalidArgumentException('Attachments must be bounded file records.');
            $total+=strlen($item->bytes);
        }
        if($total>self::MAX_TOTAL_BYTES)throw new InvalidArgumentException('Attachments are limited to 512 KiB in total.');
        return $list;
    }

    /** @return list<array> ordered descriptors for fingerprint binding */
    public static function descriptors(array $list):array
    {
        return array_map(static fn(self $item):array=>$item->descriptor(),self::list($list));
    }
}
