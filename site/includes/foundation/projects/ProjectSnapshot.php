<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\Projects;

/** Exact public project fields. This value object does not grant access or render HTML. */
final readonly class ProjectSnapshot {
    public const FIELDS=['title','summary','stage','milestones'];
    public const STAGES=['discovery','design','development','review','launched','care','paused'];
    private function __construct(private string $bytes){}

    public static function fromArray(array $fields):self {
        if(array_keys($fields)!==self::FIELDS)throw new \InvalidArgumentException('Invalid project fields or order.');
        foreach(['title'=>160,'summary'=>10000,'stage'=>30,'milestones'=>15000] as $key=>$limit){
            $text=$fields[$key];$multiline=in_array($key,['summary','milestones'],true);
            // Legacy project forms preserve CRLF and CR. History must retain those exact bytes.
            $control=$multiline?'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u':'/[\x00-\x1F\x7F]/u';
            if(!is_string($text)||strlen($text)>$limit||preg_match('//u',$text)!==1||preg_match($control,$text))throw new \InvalidArgumentException('Invalid or oversized project '.$key.'.');
        }
        if(trim($fields['title'])===''||!in_array($fields['stage'],self::STAGES,true))throw new \InvalidArgumentException('Choose a title and an existing project stage.');
        return new self(json_encode($fields,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }
    public function data():array {return json_decode($this->bytes,true,4,JSON_THROW_ON_ERROR);}
    public function json():string {return $this->bytes;}
    public function fingerprint():string {return hash('sha256',$this->json());}
    /** Stable field order; differences never infer business completion or payment. */
    public function changesFrom(self $before):array {
        $after=$this->data();$prior=$before->data();$changed=[];foreach(self::FIELDS as $key)if($after[$key]!==$prior[$key])$changed[]=$key;
        return $changed;
    }
}
