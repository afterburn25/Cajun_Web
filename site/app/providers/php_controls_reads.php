<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_connection.php';
require_once CWP_CODE_ROOT.'/app/providers/php_controls_read_limits.php';

use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
use CajunWebPro\Foundation\Provider\{ProviderCredential,TransportException};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpVersion,PackagePhpVersionBinding,PackagePhpVersionObservation,PackagePhpOptionSet};

final class ProviderPhpControlsReadAccessChanged extends RuntimeException {}

/** Current scalar only. No available-options URL/parser is admitted here. */
function provider_php_controls_read_transport(array $profile,#[SensitiveParameter] string $secret,string $address,callable $authorize,PackagePhpVersionBinding $binding,string $kind): PackagePhpVersionObservation
{
    if ($kind!=='current') throw new LogicException('The available PHP response contract is not verified.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($secret,$authorize):string { $authorize(); return $secret; });
    return PackagePhpVersion::read(new FixedHttpTransport($address,$authorize),$credential,$binding,time());
}

/** Normalize only established reader codes; no exception text can become a result. */
function provider_php_controls_read_failure(string $reason): array
{
    return match ($reason) {
        'authentication'=>['unavailable','authentication'],
        'transport_timeout','timeout'=>['unknown','timeout'],
        'transport_protocol','protocol'=>['unknown','protocol'],
        'transport_unknown','unknown'=>['unknown','unknown'],
        'transport_connect','transport_tls','transport_unavailable','connect','tls','unavailable'=>['unavailable','unavailable'],
        'response_limit'=>['unavailable','response_limit'],
        'rate_limited','remote_error','unexpected_status'=>['unavailable','unexpected_status'],
        'malformed_body','unexpected_shape','invalid_value'=>['unavailable','unexpected_response'],
        default=>throw new LogicException('Unrecognized PHP reader failure.'),
    };
}

/** No transaction may survive into a resolver, key opening or transport callback. */
function provider_php_controls_read_connection(array $context): array
{
    if (db()->inTransaction()) throw new LogicException('PHP reads cannot inherit a network transaction.');
    provider_php_controls_guard();
    try { return provider_php_controls_connection($context); }
    catch (InvalidArgumentException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        throw new ProviderPhpControlsReadAccessChanged('Current PHP read authority changed.');
    }
}

/** Internal typed seam validation, not a provider-options parser or public input. */
function provider_php_controls_read_projection(mixed $observed,string $kind,PackagePhpVersionBinding $binding,int $started,#[SensitiveParameter] string $secret): array
{
    if ($kind==='current') {
        if (!$observed instanceof PackagePhpVersionObservation || $observed->binding->data!==$binding->data
            || $observed->scope!==$binding->scope || $observed->observedAt<$started || $observed->observedAt>time()+30) {
            throw new LogicException('The PHP observation does not match its exact intent.');
        }
        if ($observed->state!=='complete') {
            [$state,$reason]=provider_php_controls_read_failure($observed->reason);
            return [$state,$reason,null,null,null];
        }
        $texts=[$observed->value];
        $value=PackagePhpVersion::value($observed->value);$options=null;$hash=null;
    } elseif ($kind==='available' && $observed instanceof PackagePhpOptionSet) {
        // This path is only an injected synthetic test projection until a real
        // provider adapter is reviewed. Neither HTTP nor configuration supplies it.
        $value=null;$options=$observed->options;$hash=$observed->sha256;$texts=[];
        foreach ($options as $option) foreach (['value','label','handler'] as $field) $texts[]=$option[$field];
    } else throw new LogicException('The PHP reader returned the wrong observation type.');
    foreach ($texts as $text) {
        if (str_contains($text,$secret) || str_contains($text,base64_encode($secret))) {
            return ['unavailable','unexpected_response',null,null,null];
        }
    }
    return ['complete','',$value,$options,$hash];
}

/**
 * Internal executor. The only actor sources are the real owner/client wrappers.
 * Resolver and transport are PHP test dependencies, never HTTP/config inputs.
 * Transport receives (profile, secret, address, authorize, binding, kind).
 */
function provider_php_controls_read_attempt(string $linkId,string $kind,?int $clientId,#[SensitiveParameter] ?string $password,?callable $resolver,?callable $transport): array
{
    if (db()->inTransaction()) throw new LogicException('PHP reads cannot inherit a transaction.');
    provider_php_controls_lifecycle_id($linkId);
    if (!in_array($kind,['current','available'],true) || (($clientId===null)===($password===null))) {
        throw new InvalidArgumentException('Select an explicit PHP read.');
    }
    if ($clientId!==null) provider_php_controls_lifecycle_number($clientId,1);
    return staff_locked(function()use($linkId,$kind,$clientId,$password,$resolver,$transport):array {
        [$context,$intent]=staff_transaction(function()use($linkId,$kind,$clientId,$password):array {
            unset($GLOBALS['cwp_settings']);
            if ($clientId===null) {
                $owner=provider_settings_owner($password);
                $context=provider_php_controls_owner_context($linkId);
                if ($context['document']['actor']!==['kind'=>'staff','id'=>(int)$owner['id'],'auth_version'=>$owner['auth_version']]) {
                    throw new RuntimeException('Your current owner identity changed.');
                }
            } else $context=provider_php_controls_client_context($clientId,$linkId);
            if (!provider_php_controls_lifecycle_ready()) throw new RuntimeException('Prepare PHP control history first.');
            $now=time();provider_php_controls_read_limit($context['binding']->data['profile_id'],$context['document']['actor'],$now);
            $intent=provider_php_controls_lifecycle_read(['format'=>1,'id'=>bin2hex(random_bytes(16)),'seq'=>provider_php_controls_lifecycle_next('read'),'kind'=>$kind,'context'=>$context['document'],'started_epoch'=>$now]);
            $saved=provider_php_controls_lifecycle_insert('read',$intent);
            if ($saved['document']!==$intent) throw new LogicException('The saved PHP intent changed.');
            return [$context,$intent];
        });
        $authorize=static fn():array=>provider_php_controls_read_connection($context);
        $state='unavailable';$reason='unexpected_response';$value=null;$options=null;$optionsHash=null;
        try {
            $authorize();
            // Until the actual available-options schema/admission is verified,
            // retain an unavailable result without DNS or opening credentials.
            if ($kind==='current' || $transport!==null) {
                try { $address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0]; }
                catch (InvalidArgumentException|RuntimeException $e) {
                    if ($e instanceof PDOException) throw $e;
                    throw new TransportException(TransportException::KIND_UNAVAILABLE);
                }
                $profile=$authorize();
                try { $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials'])); }
                catch (InvalidArgumentException|RuntimeException $e) {
                    if ($e instanceof PDOException) throw $e;
                    $secret=null;$reason='credential';
                }
                if ($secret!==null) {
                    if (!hash_equals($context['binding']->data['key_fingerprint'],provider_inventory_fingerprint($secret))) {
                        throw new ProviderPhpControlsReadAccessChanged('The PHP credential context changed.');
                    }
                    $authorize();
                    $observed=($transport??'provider_php_controls_read_transport')($profile,$secret,$address,$authorize,$context['binding'],$kind);
                    [$state,$reason,$value,$options,$optionsHash]=provider_php_controls_read_projection($observed,$kind,$context['binding'],$intent['started_epoch'],$secret);
                }
            }
        } catch (ProviderPhpControlsReadAccessChanged) {
            $state='unavailable';$reason='access_changed';$value=null;$options=null;$optionsHash=null;
        } catch (TransportException $e) {
            [$state,$reason]=provider_php_controls_read_failure($e->kind);$value=null;$options=null;$optionsHash=null;
        } finally { unset($secret,$observed,$profile); }
        // Even a failed transport cannot retain a positive result after revocation.
        try { $authorize(); }
        catch (ProviderPhpControlsReadAccessChanged) { $state='unavailable';$reason='access_changed';$value=null;$options=null;$optionsHash=null; }
        return staff_transaction(function()use($context,$intent,$state,$reason,$value,$options,$optionsHash):array {
            try { provider_php_controls_reauthorize($context); }
            catch (InvalidArgumentException|RuntimeException $e) {
                if ($e instanceof PDOException) throw $e;
                $state='unavailable';$reason='access_changed';$value=null;$options=null;$optionsHash=null;
            }
            $original=provider_php_controls_lifecycle_get('read',$intent['id']);
            if (($original['document']??null)!==$intent) throw new RuntimeException('Restore the exact PHP read intent.');
            $result=provider_php_controls_lifecycle_result(['format'=>1,'read_id'=>$intent['id'],'read_sha256'=>provider_php_controls_lifecycle_sha($intent),'state'=>$state,'reason'=>$reason,'finished_epoch'=>max(time(),$intent['started_epoch']),'value'=>$value,'options'=>$options,'options_sha256'=>$optionsHash],$intent);
            $saved=provider_php_controls_lifecycle_insert('result',$result);
            if ($saved['document']!==$result) throw new LogicException('The saved PHP result changed.');
            return ['id'=>$intent['id'],'kind'=>$intent['kind'],'state'=>$result['state'],'reason'=>$result['reason']];
        });
    });
}

function provider_php_controls_read_owner(string $linkId,string $kind,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null): array
{
    return provider_php_controls_read_attempt($linkId,$kind,null,$password,$resolver,$transport);
}
function provider_php_controls_read_client(int $clientId,string $linkId,string $kind,?callable $resolver=null,?callable $transport=null): array
{
    return provider_php_controls_read_attempt($linkId,$kind,$clientId,null,$resolver,$transport);
}
