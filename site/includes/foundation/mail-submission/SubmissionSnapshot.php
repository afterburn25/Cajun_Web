<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailSubmission;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailCompose\{DraftAttachment,DraftContent,MessageThread};
use InvalidArgumentException;

require_once __DIR__.'/../mail-compose/DraftContent.php';
require_once __DIR__.'/../mail-compose/DraftAttachment.php';
require_once __DIR__.'/../mail-compose/MessageThread.php';

/**
 * Immutable input to future maintained MIME preparation, not a dispatch grant.
 * The adapter must supply server-generated identity/time and current-authorized
 * saved draft/account revisions. This class has no I/O, credentials or storage.
 * Fingerprints bind the full private snapshot; they are not authentication.
 */
final readonly class SubmissionSnapshot implements \JsonSerializable
{
    public string $from;
    public string $messageId;
    public string $fingerprint;
    /** @var list<DraftAttachment> */
    public array $attachments;

    public function __construct(
        public string $id,
        public int $authorId,
        public string $draftId,
        public int $draftRevision,
        public string $accountId,
        public int $accountRevision,
        string $from,
        public DraftContent $content,
        public int $preparedEpoch,
        public ?MessageThread $thread = null,
        public ?string $originFingerprint = null,
        array $attachments = [],
    ) {
        DraftAttachment::list($attachments);
        $attachmentCopy=[];
        foreach($attachments as $attachment)$attachmentCopy[]=$attachment;
        $this->attachments=$attachmentCopy;
        foreach ([$id,$draftId,$accountId] as $opaque) {
            if (!preg_match('/\A[a-f0-9]{32}\z/',$opaque)) {
                throw new InvalidArgumentException('Submission, draft and account identities must be exact opaque ids.');
            }
        }
        if ($authorId<1 || $draftRevision<1 || $accountRevision<1) {
            throw new InvalidArgumentException('A submission requires a saved author, draft and account revision.');
        }
        // A finite UTC instant representable by the eventual four-digit MIME date.
        if ($preparedEpoch<1 || $preparedEpoch>253402300799) {
            throw new InvalidArgumentException('The prepared timestamp is outside the supported UTC range.');
        }
        if (count($content->to)+count($content->cc)+count($content->bcc)===0) {
            throw new InvalidArgumentException('Select at least one recipient before preparing a submission.');
        }
        if (($thread===null)!==($originFingerprint===null)) {
            throw new InvalidArgumentException('Copied-content submissions require thread context and origin binding together.');
        }
        if ($originFingerprint!==null && !preg_match('/\A[a-f0-9]{64}\z/',$originFingerprint)) {
            throw new InvalidArgumentException('Invalid copied draft origin binding.');
        }
        // Share the established bare-address validation; no second address grammar.
        $sender=new DraftContent([$from],[],[],'','');
        $this->from=$sender->to[0];
        $domain=substr($this->from,strrpos($this->from,'@')+1);
        $this->messageId='<cwp.'.$id.'@'.$domain.'>';
        $this->fingerprint=hash('sha256',json_encode($this->privatePayload(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    /**
     * Ordered, reference-free envelope recipients. Bcc stays in the envelope;
     * future MIME generation must independently prove it is absent in headers.
     * @return list<string>
     */
    public function envelopeRecipients():array
    {
        return array_merge($this->content->to,$this->content->cc,$this->content->bcc);
    }

    /** Sensitive snapshots require the explicit privatePayload boundary. */
    public function jsonSerialize(): mixed { throw new \LogicException('Submission object serialization is unavailable.'); }

    public function __serialize(): array { throw new \LogicException('Submission object serialization is unavailable.'); }

    public function __unserialize(array $data): void { throw new \LogicException('Submission object serialization is unavailable.'); }

    /** Sensitive complete preparation payload: never put in logs, URLs or bells. */
    public function privatePayload():array
    {
        $payload=[
            'format'=>1,'id'=>$this->id,'author_id'=>$this->authorId,
            'draft_id'=>$this->draftId,'draft_revision'=>$this->draftRevision,
            'account_id'=>$this->accountId,'account_revision'=>$this->accountRevision,
            'from'=>$this->from,'message_id'=>$this->messageId,'prepared_epoch'=>$this->preparedEpoch,
            'to'=>$this->content->to,'cc'=>$this->content->cc,'bcc'=>$this->content->bcc,
            'subject'=>$this->content->subject,'body'=>$this->content->body,
        ];
        if ($this->content->html!==null) {
            $payload['format']+=4;
            $payload['body_html']=$this->content->html;
        }
        if ($this->thread!==null) {
            $payload['format']+=1;
            $payload['thread']=$this->thread->toArray();
            $payload['origin_fingerprint']=$this->originFingerprint;
        }
        if ($this->attachments!==[]) {
            $payload['format']+=2;
            $payload['attachments']=DraftAttachment::descriptors($this->attachments);
        }
        return $payload;
    }
}
