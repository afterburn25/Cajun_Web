<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Durable provider operation storage.
 *
 * `provider_operations` is the OperationLedger backing store: one row
 * per idempotency key. A `claimed` row reserves the key; a resolved row
 * holds the bounded OperationResult fields needed to replay it. A stale
 * claim (crash between claim and record) stays claimed and classifies
 * uncertain rather than re-executing.
 *
 * `provider_operation_grants` is the owner approval record: an explicit
 * grant binds a profile, an operation name, the canonical JSON of the
 * approved parameters and their scope fingerprint, AND the profile
 * revision approved at grant time. Any settings change — including a
 * credential replacement — invalidates the grant; a disabled profile
 * suspends it. A grant is single-use — execution records the
 * idempotency key that consumed it, so approval is per request
 * instance, never a standing capability. Grants expire and can be
 * revoked; a missing/expired/revoked/consumed/stale-revision grant is
 * a refusal, never a guess. Grant rows are audit evidence — they never
 * contain secrets or provider payloads.
 */
function provider_operations_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$ascii=' CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'provider_operations'=>'CREATE TABLE `'.$prefix.'provider_operations` (id '.$id.' PRIMARY KEY,idempotency_key VARCHAR(190)'.$ascii.' NOT NULL,fingerprint CHAR(64)'.$ascii.' NOT NULL,operation VARCHAR(64)'.$ascii.' NULL,status VARCHAR(16)'.$ascii.' NOT NULL,result_message VARCHAR(200) NOT NULL,provider_code VARCHAR(64)'.$ascii.' NULL,external_id VARCHAR(190)'.$ascii.' NULL,attempts INT UNSIGNED NOT NULL,claimed_at DATETIME NOT NULL,claimed_epoch BIGINT UNSIGNED NOT NULL,resolved_epoch BIGINT UNSIGNED NULL,UNIQUE KEY operation_key(idempotency_key),INDEX operation_history(operation,claimed_epoch))'.$engine,
        'provider_operation_grants'=>'CREATE TABLE `'.$prefix.'provider_operation_grants` (id '.$id.' PRIMARY KEY,seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,profile_id '.$id.' NOT NULL,operation VARCHAR(64)'.$ascii.' NOT NULL,scope_fingerprint CHAR(64)'.$ascii.' NOT NULL,granted_by INT UNSIGNED NOT NULL,granted_at DATETIME NOT NULL,expires_epoch BIGINT UNSIGNED NOT NULL,revoked_at DATETIME NULL,revoked_by INT UNSIGNED NULL,profile_revision INT UNSIGNED NOT NULL,scope_params VARCHAR(4096) NULL,consumed_key VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL,consumed_at DATETIME NULL,INDEX grant_scope(profile_id,operation),INDEX grant_actor(granted_by),INDEX grant_revoker(revoked_by),FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (granted_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT,FOREIGN KEY (revoked_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
