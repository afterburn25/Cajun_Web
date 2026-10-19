<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/StackCpLaunchTransport.php';
require_once __DIR__.'/StackCpLaunchDelivery.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException};

/** Executable SDK subset. Native caller owns the mutex, durable phases, and current authority. */
final class StackCpLaunchSender
{
    public static function launch(StackCpHandoffContext $context, callable $credentials, callable $authorize,
        callable $beforePhase, callable $afterPhase, ?callable $lookup=null, ?callable $transportFactory=null): StackCpLaunchDelivery
    {
        $context = StackCpHandoffContext::validated($context);
        $secrets = []; $phase = 'brand'; $posted = false;
        $guard = static function() use ($authorize,&$phase,&$posted): void {
            try { $authorize(); }
            catch (\PDOException $e) { throw $e; }
            catch (\RuntimeException|\InvalidArgumentException) { throw new StackCpLaunchFailure($phase,'access_changed',$posted); }
        };
        $start = static function(string $next) use (&$phase,&$posted,$beforePhase,$guard,$lookup,$transportFactory): HttpTransport {
            $phase=$next; $guard();
            $beforePhase($phase); // Must durably commit BEFORE DNS, credentials or HTTP; never inside an SQL transaction.
            $guard();
            try { $address=StackCpLaunchTransport::resolve($phase,$lookup); }
            catch (\InvalidArgumentException|\CajunWebPro\Foundation\MailTransport\ProbeFailure) { throw new StackCpLaunchFailure($phase,'dns',$posted); }
            $guard();
            $transport=$transportFactory === null ? new StackCpLaunchTransport($phase,$address,$guard) : $transportFactory($phase,$address,$guard);
            if (!$transport instanceof HttpTransport) throw new \LogicException('Invalid StackCP transport factory.');
            return $transport;
        };
        $key = static function(string $role) use ($credentials,$guard,&$secrets,&$phase,&$posted): string {
            $guard();
            try { $value=$credentials($role); }
            catch (\PDOException $e) { throw $e; }
            catch (\RuntimeException|\InvalidArgumentException) { throw new StackCpLaunchFailure($phase,'credentials',$posted); }
            $guard();
            if (!is_string($value) || $value==='' || strlen($value)>4096 || preg_match('//u',$value)!==1
                || preg_match('/[\x00-\x20\x7f]/',$value)) throw new StackCpLaunchFailure($phase,'credentials',$posted);
            return $secrets[$role]=$value;
        };
        $send = static function(HttpTransport $transport, #[\SensitiveParameter] string $secret, #[\SensitiveParameter] ?string $body=null) use ($guard,&$phase,&$posted): TransportResponse {
            $headers=['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json'];
            if ($body!==null) $headers['Content-Type']='application/json';
            $request=new TransportRequest($phase==='brand'?'GET':'POST',StackCpLaunchTransport::url($phase),$headers,$body,15);
            $guard(); if ($phase!=='brand') $posted=true;
            try { $response=$transport->send($request); }
            catch (TransportException) { throw new StackCpLaunchFailure($phase,'transport',$posted); }
            $guard();
            if (strlen($response->body)>StackCpLaunchTransport::MAX_RESPONSE_BYTES) throw new StackCpLaunchFailure($phase,'body_limit',$posted);
            return $response;
        };
        try {
            // The independent general-key GET is deliberately first: a custom brand cannot create a token.
            $transport=$start('brand'); $general=$key('general-api-key');
            $response=$send($transport,$general);
            $brand=StackCpDefaultBrand::fromResponse($response->status,$response->body,[$general]);
            unset($response); $guard(); $afterPhase('brand'); $guard();

            $transport=$start('token'); $auth=$key('auth-client-key');
            $brand->assertNoEcho([$general,$auth]);
            $response=$send($transport,$auth,json_encode(['grant_type'=>'client_credentials','scope'=>$context->data['review']['stack_user_identity']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            $token=StackCpSensitiveResponse::parseToken($context,$response->status,$response->body,$secrets);
            unset($response);
            if ($token->state!=='accepted_subset') throw new StackCpLaunchFailure('token',$token->reason,true);
            $guard(); $afterPhase('token'); $guard();

            $transport=$start('session');
            return $token->consume('token',static function(#[\SensitiveParameter] string $access) use ($context,$brand,$transport,$send,$guard,$afterPhase,&$secrets): StackCpLaunchDelivery {
                $brand->assertNoEcho([...array_values($secrets),$access]);
                $secrets['access-token']=$access;
                $response=$brand->withBody(static fn(#[\SensitiveParameter] string $body): TransportResponse=>$send($transport,$access,$body));
                $session=StackCpSensitiveResponse::parseSession($context,$response->status,$response->body,$secrets);
                unset($response);
                if ($session->state!=='accepted_subset') throw new StackCpLaunchFailure('session',$session->reason,true);
                $guard(); $afterPhase('session'); $guard();
                return StackCpLaunchDelivery::fromSession($context,$session);
            });
        } catch (StackCpLaunchFailure $e) {
            // A failure validating retained brand bytes belongs to the current phase;
            // once any POST was invoked, later failures cannot promise no access effect.
            throw new StackCpLaunchFailure($phase,$e->reason,$posted || $e->possibly_sent);
        } finally { unset($secrets,$general,$auth,$token,$brand,$response,$transport); }
    }
}
