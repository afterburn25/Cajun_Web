<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use DirectoryTree\ImapEngine\Connection\ImapParser;
use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;
use DirectoryTree\ImapEngine\Connection\Responses\{Response,TaggedResponse,UntaggedResponse,ContinuationResponse};
use DirectoryTree\ImapEngine\Connection\Tokens\{Atom,Number,Token};

/** Fixed APPEND-only write sequence to one named folder; no arbitrary command API. */
final class AppendSession
{
    private ImapParser $parser;
    private BoundedTokenizer $tokenizer;
    private int $tag=0;
    private int $responses=0;
    private \Closure $authorize;
    public function __construct(private StreamInterface $stream, callable $authorize)
    {
        $this->authorize=\Closure::fromCallable($authorize);
        $this->tokenizer=new BoundedTokenizer($stream,ReadProfile::Append);
        $this->parser=new ImapParser($this->tokenizer);
    }

    private static function word(mixed $token): string
    {
        if(!$token instanceof Atom) throw new ReceivingFailure('protocol');
        return strtoupper($token->value);
    }
    private function next(): Response
    {
        if(++$this->responses>40) throw new ReceivingFailure('limit');
        $response=$this->parser->next();
        if(!$response instanceof Response || count($response->tokens())<1) throw new ReceivingFailure('protocol');
        return $response;
    }
    private function command(string $command): string
    {
        $tag='CWA'.str_pad((string)++$this->tag,3,'0',STR_PAD_LEFT);
        $this->stream->fwrite($tag.' '.$command."\r\n"); return $tag;
    }
    private function replies(string $tag, string $failure='protocol'): array
    {
        $rows=[];
        while(true) {
            $row=$this->next(); $tokens=$row->tokens();
            if($row instanceof TaggedResponse) {
                if(!($tokens[0]??null) instanceof Token || $tokens[0]->value!==$tag) throw new ReceivingFailure('protocol');
                $status=self::word($tokens[1]??null);
                if(in_array($status,['NO','BAD'],true)) throw new ReceivingFailure($failure);
                if($status!=='OK') throw new ReceivingFailure('protocol');
                $this->tokenizer->assertBoundary();
                $rows[]=$row; return $rows;
            }
            if(!$row instanceof UntaggedResponse || self::word($tokens[1]??null)==='BYE') throw new ReceivingFailure('connection');
            $rows[]=$row;
        }
    }
    private function capabilities(): array
    {
        $caps=[]; $found=false;
        foreach($this->replies($this->command('CAPABILITY')) as $row) {
            if($row instanceof TaggedResponse) continue;
            $tokens=$row->tokens(); $type=self::word($tokens[1]??null);
            if($type==='OK') continue;
            if($type!=='CAPABILITY' || $found) throw new ReceivingFailure('protocol');
            $found=true;
            foreach(array_slice($tokens,2) as $token) $caps[]=self::word($token);
        }
        if(!$found) throw new ReceivingFailure('protocol');
        return $caps;
    }
    private function authorize(): void
    {
        try { ($this->authorize)(); }
        catch(\Throwable) { throw new ReceivingFailure('access_changed'); }
        if($this->stream instanceof ReceivingStream) $this->stream->assertWithinBudget();
    }

    /** A destination folder name safe to quote on the wire: printable ASCII, no specials. */
    public static function folder(string $name): string
    {
        if(!preg_match('/\A[\x20-\x7E]{1,64}\z/D',$name) || str_contains($name,'"') || str_contains($name,'\\')
            || str_contains($name,'%') || str_contains($name,'*')) throw new ReceivingFailure('folder');
        return $name;
    }
    /** Exact saved MIME is the only accepted payload; bounds match the submission contract. */
    public static function message(#[\SensitiveParameter] string $bytes): string
    {
        if(strlen($bytes)>65536 || strlen($bytes)<32 || !str_contains($bytes,"\r\n\r\n")
            || preg_match('/[^\x09\x0A\x0D\x20-\x7E]/',$bytes)) throw new ReceivingFailure('protocol');
        return $bytes;
    }

    public function append(string $encryption, #[\SensitiveParameter] string $user, #[\SensitiveParameter] string $password,
        string $folder, #[\SensitiveParameter] string $message): void
    {
        $started=hrtime(true);
        try {
            if(!in_array($encryption,['ssl','starttls'],true)) throw new ReceivingFailure('protocol');
            if($user==='' || $password==='' || strlen($user)>190 || strlen($password)>1024 || str_contains($user,"\0") || str_contains($password,"\0")) throw new ReceivingFailure('credential');
            $folder=self::folder($folder); $message=self::message($message);
            $greeting=$this->next();
            if(!$greeting instanceof UntaggedResponse || self::word($greeting->tokens()[1]??null)!=='OK') throw new ReceivingFailure('protocol');
            $caps=$this->capabilities();
            if($encryption==='starttls') {
                if(!in_array('STARTTLS',$caps,true)) throw new ReceivingFailure('upgrade');
                $this->replies($this->command('STARTTLS'),'upgrade');
                $this->tokenizer->assertBoundary();
                if($this->stream->setSocketSetCrypto(true,null)!==true) throw new ReceivingFailure('tls');
                $caps=$this->capabilities();
            }
            if(!in_array($this->stream->meta()['crypto']['protocol']??'', ['TLSv1.2','TLSv1.3'],true)) throw new ReceivingFailure('tls');
            if(!in_array('AUTH=PLAIN',$caps,true)) throw new ReceivingFailure('mechanism');
            $tag=$this->command('AUTHENTICATE PLAIN'); $challenge=$this->next();
            if($challenge instanceof TaggedResponse && ($challenge->tokens()[0]->value??null)===$tag && in_array(self::word($challenge->tokens()[1]??null),['NO','BAD'],true)) throw new ReceivingFailure('authentication');
            if(!$challenge instanceof ContinuationResponse || count($challenge->tokens())!==1) throw new ReceivingFailure('protocol');
            $this->authorize();
            $this->stream->fwrite(base64_encode("\0".$user."\0".$password)."\r\n");
            $this->replies($tag,'authentication'); $this->authorize();
            $tag=$this->command('APPEND "'.$folder.'" (\\Seen) {'.strlen($message).'}');
            $go=$this->next();
            if($go instanceof TaggedResponse && ($go->tokens()[0]->value??null)===$tag && in_array(self::word($go->tokens()[1]??null),['NO','BAD'],true)) throw new ReceivingFailure('folder');
            if(!$go instanceof ContinuationResponse || count($go->tokens())!==1) throw new ReceivingFailure('protocol');
            $this->authorize();
            $this->stream->fwriteLiteral($message);
            $this->stream->fwrite("\r\n");
            $this->replies($tag,'folder'); $this->authorize();
            if((hrtime(true)-$started)/1e9>20) throw new ReceivingFailure('timeout');
            try { $this->command('LOGOUT'); } catch(\Throwable) {}
        } catch(ReceivingFailure $e) { throw $e; }
        catch(\Throwable) { throw new ReceivingFailure('protocol'); }
        finally { $this->stream->close(); }
    }
}
