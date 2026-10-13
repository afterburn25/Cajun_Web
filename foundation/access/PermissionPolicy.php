<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Access;

/** Pure permission decisions. Account mutations and authentication live in the adapter. */
final class PermissionPolicy
{
    public const STAFF_MANAGE = 'staff.manage';
    public const STAFF_GRANT = 'staff.permissions.manage';
    public const MAIL_ACCOUNTS_MANAGE = 'mail.accounts.manage';
    public const MAIL_READ = 'mail.read';
    public const MAIL_SEND = 'mail.send';
    public const MAIL_TEMPLATES_MANAGE = 'mail.templates.manage';
    public const BILLING_SETTINGS_MANAGE = 'billing.settings.manage';
    public const HOSTING_SETTINGS_MANAGE = 'hosting.settings.manage';

    private const GLOBAL_PERMISSIONS = [
        self::STAFF_MANAGE,
        self::STAFF_GRANT,
        self::MAIL_ACCOUNTS_MANAGE,
        self::MAIL_TEMPLATES_MANAGE,
        self::BILLING_SETTINGS_MANAGE,
        self::HOSTING_SETTINGS_MANAGE,
    ];

    public function allows(
        AccessContext $actor,
        string $permission,
        ?string $mailboxId = null,
    ): bool {
        if (
            trim($actor->actorId) === ''
            || $actor->principalType !== 'staff'
            || !in_array($actor->role, ['owner', 'administrator', 'staff'], true)
            || !$actor->active
            || $actor->authorizationVersion < 1
            || $actor->sessionAuthorizationVersion !== $actor->authorizationVersion
        ) {
            return false;
        }

        $mailPermission = in_array($permission, [self::MAIL_READ, self::MAIL_SEND], true);
        if (!$mailPermission && !in_array($permission, self::GLOBAL_PERMISSIONS, true)) {
            return false;
        }

        // A role label alone grants no privileges to newly created administrators.
        if ($actor->role !== 'owner' && !in_array($permission, $actor->permissions, true)) {
            return false;
        }

        if (!$mailPermission) {
            // Do not accidentally treat a global decision as authorization for a mailbox.
            return $mailboxId === null;
        }

        if ($mailboxId === null || trim($mailboxId) === '') {
            return false;
        }

        // Even owners need an explicit current grant for the requested mailbox.
        foreach ($actor->mailboxes as $grant) {
            if ($grant->mailboxId === $mailboxId) {
                return $grant->enabled
                    && ($permission === self::MAIL_READ ? $grant->canRead : $grant->canSend);
            }
        }

        return false;
    }
}
