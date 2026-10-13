<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Invitations;

use CajunWebPro\Foundation\Access\AuthorizationVersion;
use Closure;
use InvalidArgumentException;
use LogicException;
use PDO;
use Throwable;

/**
 * MySQL/MariaDB staff invitation persistence, intentionally not an HTTP endpoint.
 * The caller authorizes issuance and implements activation in the SAME transaction.
 * The required InnoDB table is explicit; this class never installs or migrates it.
 */
final class StaffInvitationStore
{
    private readonly string $table;
    private readonly Closure $clock;

    public function __construct(private readonly PDO $db, string $table, ?Closure $clock = null)
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new InvalidArgumentException('Native MySQL/MariaDB PDO is required.');
        }
        if ($db->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new InvalidArgumentException('PDO exception mode is required.');
        }
        $this->table = self::tableIdentifier($table);
        $this->clock = $clock ?? static fn(): int => time();
        $statement = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        if (strcasecmp((string)$statement->fetchColumn(), 'InnoDB') !== 0) {
            throw new InvalidArgumentException('The invitation table must already exist and use InnoDB.');
        }
    }

    /** Return additive DDL for an explicit migration; never execute it automatically. */
    public static function createTableSql(string $table): string
    {
        return 'CREATE TABLE ' . self::tableIdentifier($table) . ' ('
            . 'subject_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,'
            . 'token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,'
            . 'auth_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . 'expires_at BIGINT UNSIGNED NOT NULL, consumed_at BIGINT UNSIGNED NULL,'
            . 'INDEX invitation_expiry (expires_at)) ENGINE=InnoDB';
    }

    public function issue(
        string $subjectId,
        #[\SensitiveParameter] string $authorizationVersion,
        int $ttlSeconds = 86400,
    ): IssuedInvitation {
        self::validateSubject($subjectId);
        if (!AuthorizationVersion::valid($authorizationVersion)) {
            throw new InvalidArgumentException('A current authorization token is required.');
        }
        if ($ttlSeconds < 60 || $ttlSeconds > 86400) {
            throw new InvalidArgumentException('Invitation lifetime must be between one minute and one day.');
        }
        $token = bin2hex(random_bytes(32));
        $expires = ($this->clock)() + $ttlSeconds;
        // One row per staff subject: a resend replaces every previously issued link.
        $statement = $this->db->prepare('INSERT INTO ' . $this->table
            . ' (subject_id, token_hash, auth_version, expires_at, consumed_at) VALUES (?, ?, ?, ?, NULL)'
            . ' ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash), auth_version=VALUES(auth_version),'
            . ' expires_at=VALUES(expires_at), consumed_at=NULL');
        $statement->execute([$subjectId, self::digest($token), $authorizationVersion, $expires]);
        return new IssuedInvitation($token, $expires);
    }

    /**
     * $activate(PDO $db, string $subjectId, string $issuedAuthVersion): bool
     * Lock/reload the real staff account, check active state/current version and
     * atomically set its validated password/revocation token. No mail/network/DDL,
     * commit or rollback inside the callback. false rolls back all callback writes.
     */
    public function redeem(#[\SensitiveParameter] string $token, callable $activate): bool
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            return false;
        }
        if ($this->db->inTransaction()) {
            throw new LogicException('Redemption must own its transaction.');
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT subject_id, auth_version, expires_at, consumed_at FROM '
                . $this->table . ' WHERE token_hash=? FOR UPDATE');
            $statement->execute([self::digest($token)]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            // Read time AFTER acquiring the lock, including when another request held it.
            if (!$record || $record['consumed_at'] !== null || (int)$record['expires_at'] <= ($this->clock)()) {
                $this->db->rollBack();
                return false;
            }
            if ($activate($this->db, (string)$record['subject_id'], (string)$record['auth_version']) !== true) {
                $this->db->rollBack();
                return false;
            }
            if (!$this->db->inTransaction()) {
                throw new LogicException('Activation callback must not end the transaction.');
            }
            // A slow activation cannot turn an expired link into an accepted one.
            $now = ($this->clock)();
            if ((int)$record['expires_at'] <= $now) {
                $this->db->rollBack();
                return false;
            }
            $statement = $this->db->prepare('UPDATE ' . $this->table
                . ' SET consumed_at=? WHERE subject_id=? AND token_hash=? AND consumed_at IS NULL');
            $statement->execute([$now, $record['subject_id'], self::digest($token)]);
            if ($statement->rowCount() !== 1) {
                throw new LogicException('Invitation changed during activation.');
            }
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** Can participate in the caller's account-deactivation transaction. */
    public function revoke(string $subjectId): void
    {
        self::validateSubject($subjectId);
        $statement = $this->db->prepare('DELETE FROM ' . $this->table . ' WHERE subject_id=?');
        $statement->execute([$subjectId]);
    }

    private static function digest(#[\SensitiveParameter] string $token): string
    {
        // Domain separation prevents reusing this digest as an unrelated reset token.
        return hash('sha256', "cwp:staff-invitation:v1\0" . $token);
    }

    private static function validateSubject(string $subjectId): void
    {
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,127}\z/', $subjectId) !== 1) {
            throw new InvalidArgumentException('Invalid staff subject identifier.');
        }
    }

    private static function tableIdentifier(string $table): string
    {
        if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/', $table) !== 1) {
            throw new InvalidArgumentException('Invalid invitation table identifier.');
        }
        return '`' . $table . '`';
    }
}
