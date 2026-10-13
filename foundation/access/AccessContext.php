<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Access;

use InvalidArgumentException;

/**
 * An authorization snapshot built by a trusted application adapter.
 * Read identity, role, active state, revision and grants from current server data.
 * Only sessionAuthorizationVersion comes from the authenticated server session.
 * This object does not authenticate a request or read a database.
 */
final readonly class AccessContext
{
    /** @param list<string> $permissions @param list<MailboxGrant> $mailboxes */
    public function __construct(
        public string $actorId,
        public string $principalType,
        public string $role,
        public bool $active,
        #[\SensitiveParameter] public string $authorizationVersion,
        #[\SensitiveParameter] public string $sessionAuthorizationVersion,
        public array $permissions = [],
        public array $mailboxes = [],
    ) {
        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                throw new InvalidArgumentException('Permission identifiers must be strings.');
            }
        }

        $seen = [];
        foreach ($mailboxes as $grant) {
            if (!$grant instanceof MailboxGrant) {
                throw new InvalidArgumentException('Mailbox grants must be MailboxGrant objects.');
            }
            if (in_array($grant->mailboxId, $seen, true)) {
                throw new InvalidArgumentException('Conflicting duplicate mailbox grants are not allowed.');
            }
            $seen[] = $grant->mailboxId;
        }
    }
}
