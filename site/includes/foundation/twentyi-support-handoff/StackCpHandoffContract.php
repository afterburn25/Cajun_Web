<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Descriptions only. No sender, request object, arbitrary URL, or authorization admission. */
final class StackCpHandoffContract
{
    public const SDK_COMMIT = '696a5f1299cd2b1e67ae7f688d644ac800f41b67';

    public static function describe(StackCpHandoffContext $context, string $phase): array
    {
        $copy = StackCpHandoffContext::validated($context);
        $review = $copy->data['review'];
        $id = substr($review['stack_user_identity'], strlen('stack-user:'));
        $base = ['operation'=>StackCpHandoffContext::OPERATION,'context_sha256'=>$copy->sha256,
            'executable'=>false,'session_scope'=>'whole-stack-user','remote_scope_restricted'=>false];
        $part = match ($phase) {
            'membership'=>['method'=>'GET','origin'=>'https://api.20i.com','path'=>'/package/'.$review['package']['package_id'].'/stackUserList','credential_role'=>'general-api-key','access_mutation'=>false],
            'grants'=>['method'=>'GET','origin'=>'https://api.20i.com','path'=>'/reseller/*/stackUser/'.$id.'/grants','credential_role'=>'general-api-key','access_mutation'=>false],
            'token'=>['method'=>'POST','origin'=>'https://auth-api.20i.com:3000','path'=>'/login/authenticate','credential_role'=>'auth-client-key','access_mutation'=>true,
                'documented_fields'=>['grant_type'=>'client_credentials','scope'=>$review['stack_user_identity']]],
            'brand'=>['method'=>'GET','origin'=>'https://api.20i.com','path'=>'/reseller/*/customisations','credential_role'=>'general-api-key','access_mutation'=>false],
            'session'=>['method'=>'POST','origin'=>'https://www.stackcp.com','path'=>'/login/implicitBranded','credential_role'=>'ephemeral-access-token','access_mutation'=>true,
                'documented_field_names'=>['brand'],'brand_schema_verified'=>false],
            'browser'=>['method'=>'GET','origin'=>'https://www.stackcp.com','path'=>'/login/sso','credential_role'=>'ephemeral-session-query','access_mutation'=>true,
                'documented_query_names'=>['PHPSESSID'],'remote_expiry_verified'=>false,'remote_revoke_verified'=>false],
            default=>throw new \InvalidArgumentException('Unknown StackCP handoff phase.'),
        };
        return $base + $part;
    }

    /** Local allowlist for the future default destination; never accepts a bearer URL. */
    public static function defaultDestination(string $origin, string $path): bool
    {
        return $origin === 'https://www.stackcp.com' && $path === '/login/sso';
    }
}
