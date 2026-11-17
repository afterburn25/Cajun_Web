<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Projects;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Immutable staff-only project note.
 *
 * Current authority, project existence, atomic storage, audit insertion and
 * escaped rendering remain adapter responsibilities. This record deliberately
 * has no client projection: private content must never become a public project
 * event, public conversation entry, client notification or customer message.
 */
final readonly class ProjectPrivateNote
{
    public const MAX_BODY_BYTES = 5000;
    public const MAX_PAGE = 100;
    public const MAX_RECORD_BYTES = 16384;

    private const FIELDS = ['format','id','project_id','staff_id','created_at','body'];

    private function __construct(private string $bytes) {}

    public static function create(int $id, int $projectId, int $staffId, int $createdAt, string $body): self
    {
        return self::fromJson(self::encode([
            'format'=>1,
            'id'=>$id,
            'project_id'=>$projectId,
            'staff_id'=>$staffId,
            'created_at'=>$createdAt,
            'body'=>self::canonicalBody($body),
        ]));
    }

    /** Prepare the exact canonical body before an adapter inserts its auto-increment record. */
    public static function canonicalBody(string $body): string
    {
        if (strlen($body)>self::MAX_BODY_BYTES || preg_match('//u',$body)!==1
            || self::hasForbiddenFormat($body)) {
            throw new \InvalidArgumentException('Write a valid private project note.');
        }
        $body=self::trimBody($body);
        self::body($body);
        return $body;
    }

    /** Parse one exact canonical record recovered from private storage. */
    public static function fromJson(string $bytes): self
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_RECORD_BYTES) {
            throw new \InvalidArgumentException('Invalid private project note record size.');
        }
        $data=json_decode($bytes,true,4,JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_keys($data)!==self::FIELDS || $data['format']!==1 || self::encode($data)!==$bytes) {
            throw new \InvalidArgumentException('Invalid or noncanonical private project note.');
        }
        self::recordId($data['id']);
        foreach (['project_id','staff_id'] as $field) self::scopedId($data[$field]);
        if (!is_int($data['created_at']) || $data['created_at']<0 || $data['created_at']>253402300799) {
            throw new \InvalidArgumentException('Invalid private project note time.');
        }
        self::body($data['body']);
        return new self($bytes);
    }

    /**
     * Validate a bounded newest-first page from already-authorized storage.
     * The adapter must still recheck current staff and project authority.
     *
     * @param list<ProjectPrivateNote> $notes
     */
    public static function verifyStaffPage(array $notes, int $projectId): void
    {
        self::scopedId($projectId);
        if (!array_is_list($notes) || count($notes)>self::MAX_PAGE) {
            throw new \InvalidArgumentException('Choose a bounded private project note page.');
        }
        $previousId=null;
        foreach ($notes as $note) {
            if (!$note instanceof self) throw new \InvalidArgumentException('Invalid private project note page.');
            $data=$note->data();
            if ($data['project_id']!==$projectId || ($previousId!==null && $data['id']>=$previousId)) {
                throw new \LogicException('Private project note page is mixed or out of order.');
            }
            $previousId=$data['id'];
        }
    }

    public function json(): string { return $this->bytes; }
    public function fingerprint(): string { return hash('sha256',$this->bytes); }
    public function data(): array { return json_decode($this->bytes,true,4,JSON_THROW_ON_ERROR); }

    /** Staff projection after the adapter has rechecked live project authority. */
    public function forStaffProject(int $projectId): array
    {
        self::scopedId($projectId);
        $data=$this->data();
        if ($data['project_id']!==$projectId) throw new \InvalidArgumentException('Private project note unavailable.');
        unset($data['format']);
        return $data;
    }

    /** A private note has no client/contact/support-session projection. */
    public function forClient(): never
    {
        throw new \LogicException('Private project notes cannot be projected to clients.');
    }

    /** Body-free facts suitable for a strict audit reference. */
    public function auditReference(): array
    {
        $data=$this->data();
        return ['id'=>$data['id'],'project_id'=>$data['project_id'],'staff_id'=>$data['staff_id'],'created_at'=>$data['created_at']];
    }

    private static function encode(array $data): string
    {
        return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }

    private static function recordId(mixed $value): void
    {
        if (!is_int($value) || $value<1) {
            throw new \InvalidArgumentException('Invalid private project note identity.');
        }
    }

    private static function scopedId(mixed $value): void
    {
        if (!is_int($value) || $value<1 || $value>2147483647) {
            throw new \InvalidArgumentException('Invalid private project or staff identity.');
        }
    }

    private static function body(mixed $body): void
    {
        if (!is_string($body) || strlen($body)<2 || strlen($body)>self::MAX_BODY_BYTES
            || self::trimBody($body)!==$body || preg_match('//u',$body)!==1
            || self::hasForbiddenFormat($body)
            || preg_match('/[\p{L}\p{N}\p{P}\p{S}]/u',$body)!==1) {
            throw new \InvalidArgumentException('Write a valid private project note.');
        }
    }

    private static function trimBody(string $body): string
    {
        $trimmed=preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u','',$body);
        if (!is_string($trimmed)) throw new \InvalidArgumentException('Write a valid private project note.');
        return $trimmed;
    }

    private static function hasForbiddenFormat(string $body): bool
    {
        if (preg_match('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u',$body)===1) return true;
        if (preg_match('/\p{Noncharacter_Code_Point}/u',$body)===1
            || preg_match('/[\x{115F}\x{1160}\x{2800}\x{303F}\x{3164}\x{FFA0}]/u',$body)===1) return true;
        $chars=preg_split('//u',$body,-1,PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) return true;
        $count=count($chars);
        foreach ($chars as $index=>$char) {
            if (preg_match('/\p{Default_Ignorable_Code_Point}/u',$char)!==1) continue;
            if ($char==="\u{FE0E}" || $char==="\u{FE0F}") {
                if ($index===0 || !(preg_match('/\p{Extended_Pictographic}/u',$chars[$index-1])===1
                    || preg_match('/[0-9#*]/u',$chars[$index-1])===1)) return true;
                continue;
            }
            if ($char!=="\u{200C}" && $char!=="\u{200D}") return true;
            if ($index===0 || $index===$count-1) return true;
            if ($char==="\u{200D}") {
                $emojiLeft=$index-1;
                while ($emojiLeft>=0 && (preg_match('/\p{Grapheme_Extend}/u',$chars[$emojiLeft])===1
                    || preg_match('/\p{Emoji_Modifier}/u',$chars[$emojiLeft])===1)) $emojiLeft--;
                if ($emojiLeft>=0 && preg_match('/\p{Extended_Pictographic}/u',$chars[$emojiLeft])===1
                    && preg_match('/\p{Extended_Pictographic}/u',$chars[$index+1])===1) continue;
            }
            if (preg_match('/[\p{L}\p{M}]/u',$chars[$index-1])!==1
                || preg_match('/[\p{L}\p{M}]/u',$chars[$index+1])!==1) return true;
            $left=$index-1;$right=$index+1;
            while ($left>=0 && preg_match('/\p{M}/u',$chars[$left])===1) $left--;
            while ($right<$count && preg_match('/\p{M}/u',$chars[$right])===1) $right++;
            if ($left<0 || $right>=$count || preg_match('/\p{L}/u',$chars[$left])!==1
                || preg_match('/\p{L}/u',$chars[$right])!==1
                || preg_match('/\p{Latin}/u',$chars[$left])===1
                || preg_match('/\p{Latin}/u',$chars[$right])===1) return true;
        }
        return false;
    }
}
