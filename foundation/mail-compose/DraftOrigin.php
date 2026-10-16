<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailCompose;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once __DIR__.'/ReplyDraft.php';
use InvalidArgumentException;

/** Immutable source/creation binding; native code must separately establish current authority. */
final readonly class DraftOrigin
{
    public string $json;
    public string $fingerprint;
    private function __construct(public array $data) {
        $this->json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $this->fingerprint=hash('sha256',$this->json);
    }
    public static function contentPayload(DraftContent $content):array {
        return ['to'=>$content->to,'cc'=>$content->cc,'bcc'=>$content->bcc,'subject'=>$content->subject,'body'=>$content->body];
    }
    public static function contentJson(DraftContent $content):string {
        return json_encode(self::contentPayload($content),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
    public static function contentFromJson(string $json):DraftContent {
        $v=json_decode($json,true,8,JSON_THROW_ON_ERROR);
        if(!is_array($v)||array_keys($v)!==['to','cc','bcc','subject','body']||!is_string($v['subject'])||!is_string($v['body']))throw new InvalidArgumentException('Invalid copied draft content.');
        foreach(['to','cc','bcc'] as $k)if(!is_array($v[$k]))throw new InvalidArgumentException('Invalid copied draft recipients.');
        $content=new DraftContent($v['to'],$v['cc'],$v['bcc'],$v['subject'],$v['body']);
        if(self::contentJson($content)!==$json)throw new InvalidArgumentException('Noncanonical copied draft content.');
        return $content;
    }
    public static function create(string $id,int $author,string $source,int $sourceRevision,string $validity,string $uid,string $read,string $outgoing,int $outgoingRevision,ReplyDraft $prepared):self {
        return self::fromJson(json_encode([
            'format'=>1,'draft_id'=>$id,'author_id'=>$author,'source_account_id'=>$source,'source_revision'=>$sourceRevision,
            'folder'=>'INBOX','uidvalidity'=>$validity,'uid'=>$uid,'read_id'=>$read,'mode'=>$prepared->mode,
            'initial_account_id'=>$outgoing,'initial_account_revision'=>$outgoingRevision,'thread'=>$prepared->thread->toArray(),
            'omissions'=>$prepared->omissions,'initial_content_sha256'=>hash('sha256',self::contentJson($prepared->content)),
        ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }
    public static function fromJson(string $json):self {
        if(strlen($json)>16384)throw new InvalidArgumentException('Copied draft origin exceeds its limit.');
        $v=json_decode($json,true,8,JSON_THROW_ON_ERROR);
        if(!is_array($v)||array_keys($v)!==['format','draft_id','author_id','source_account_id','source_revision','folder','uidvalidity','uid','read_id','mode','initial_account_id','initial_account_revision','thread','omissions','initial_content_sha256']||$v['format']!==1)throw new InvalidArgumentException('Invalid copied draft origin.');
        foreach(['draft_id','source_account_id','read_id','initial_account_id'] as $k)if(!is_string($v[$k])||!preg_match('/\A[a-f0-9]{32}\z/',$v[$k]))throw new InvalidArgumentException('Invalid copied draft identity.');
        foreach(['author_id','source_revision','initial_account_revision'] as $k)if(!is_int($v[$k])||$v[$k]<1||$v[$k]>2147483647)throw new InvalidArgumentException('Invalid copied draft revision.');
        foreach(['uidvalidity','uid'] as $k)if(!is_string($v[$k])||!preg_match('/\A[1-9][0-9]{0,9}\z/',$v[$k])||(strlen($v[$k])===10&&strcmp($v[$k],'4294967295')>0))throw new InvalidArgumentException('Invalid source message identity.');
        if($v['folder']!=='INBOX'||!in_array($v['mode'],['reply','forward'],true)||!is_array($v['thread']))throw new InvalidArgumentException('Unsupported copied draft origin.');
        $thread=MessageThread::fromStored($v['thread']);
        if($v['mode']==='forward'&&$thread->toArray()!==MessageThread::none()->toArray())throw new InvalidArgumentException('Forward cannot inherit reply headers.');
        if(!is_array($v['omissions'])||!array_is_list($v['omissions'])||count($v['omissions'])>4)throw new InvalidArgumentException('Invalid copied draft omissions.');
        $seen=[];
        foreach($v['omissions'] as $flag) {
            if(!is_string($flag)||!in_array($flag,['subject_or_header_shortened','plain_text_unavailable','additional_inline_parts','quoted_text_shortened'],true)||isset($seen[$flag]))throw new InvalidArgumentException('Invalid copied draft omissions.');
            $seen[$flag]=true;
        }
        if(!is_string($v['initial_content_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$v['initial_content_sha256']))throw new InvalidArgumentException('Invalid copied draft creation fingerprint.');
        $copy=new self($v);
        if($copy->json!==$json)throw new InvalidArgumentException('Noncanonical copied draft origin.');
        return $copy;
    }
}
