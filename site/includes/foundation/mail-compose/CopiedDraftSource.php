<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailCompose;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

require_once __DIR__.'/ReplyDraft.php';

/**
 * One transient, raw-to-derived copy capability. Raw MIME is consumed inside
 * the factory and is never retained, serialized or returned to application UI.
 */
final readonly class CopiedDraftSource
{
    private function __construct(
        public int $actorId,
        public string $accountId,
        public int $accountRevision,
        public string $uidvalidity,
        public string $uid,
        public string $readId,
        public string $messageBytes,
        public ReplyDraft $draft,
    ) {}

    public static function fromRead(
        int $actorId,
        string $accountId,
        int $accountRevision,
        string $uidvalidity,
        string $uid,
        string $readId,
        string $mode,
        #[\SensitiveParameter] string $raw,
        ?string $self=null,
    ): self {
        foreach([$accountId,$readId] as $id)if(!preg_match('/\A[a-f0-9]{32}\z/D',$id))throw new InvalidArgumentException('Invalid copied source identity.');
        foreach([$actorId,$accountRevision] as $number)if($number<1||$number>2147483647)throw new InvalidArgumentException('Invalid copied source revision.');
        foreach([$uidvalidity,$uid] as $number)if(!preg_match('/\A[1-9][0-9]{0,9}\z/D',$number)||(strlen($number)===10&&strcmp($number,'4294967295')>0))throw new InvalidArgumentException('Invalid copied message identity.');
        if($raw===''||strlen($raw)>262144)throw new InvalidArgumentException('Copied message source exceeds its limit.');
        if(($mode==='reply-all')!==($self!==null))throw new InvalidArgumentException('Reply-all sender context must be exact.');
        $draft=ReplyDraft::prepare($mode,$raw,$self);
        return new self($actorId,$accountId,$accountRevision,$uidvalidity,$uid,$readId,(string)strlen($raw),$draft);
    }

    /** A transient authorization result cannot be persisted as another source channel. */
    public function __serialize(): array
    {
        throw new \LogicException('Copied draft sources are transient.');
    }

    /** Native PHP unserialization is not a second trusted source channel. */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('Create copied draft sources from one authorized transient read.');
    }
}
