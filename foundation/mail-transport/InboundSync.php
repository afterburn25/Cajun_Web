<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailTransport;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Webmail\ConnectionResult;
use CajunWebPro\Foundation\Webmail\MailboxProfile;
use CajunWebPro\Foundation\Webmail\MailMessage;

require_once __DIR__ . '/ImapSession.php';
require_once __DIR__ . '/ImapParser.php';
require_once __DIR__ . '/MailSocket.php';
require_once __DIR__ . '/MailTransportException.php';
require_once __DIR__ . '/../webmail/ConnectionResult.php';
require_once __DIR__ . '/../webmail/MailMessage.php';
require_once __DIR__ . '/../webmail/MailboxProfile.php';

/**
 * Inbound-only synchronization over the configured IMAP profile: connect,
 * authenticate, EXAMINE each folder, fetch unseen header summaries, map them
 * to `MailMessage` identities, log out. The caller injects the socket
 * factory so this component never opens a network connection itself and
 * tests run fully scripted.
 *
 * Honest by construction: a disabled profile is never dialed; a failure is
 * reported as `ConnectionResult::failure` with the classified kind; message
 * bodies are never fetched; nothing is written back to the server.
 */
final class InboundSync
{
    /** Unseen summaries collected per folder per check; the newest bound. */
    public const MAX_MESSAGES_PER_FOLDER = 200;

    /**
     * @param callable(MailboxProfile):MailSocket $socketFactory opens the
     *        connection for the profile's host/port/encryption. For
     *        'starttls' profiles the returned socket must still be plaintext
     *        until `ImapSession::startTls()` upgrades it.
     * @param list<string> $folders folders to read; default INBOX only
     * @return array{result:ConnectionResult, messages:list<MailMessage>}
     */
    public static function checkMailbox(
        MailboxProfile $profile,
        string $username,
        #[\SensitiveParameter] string $password,
        callable $socketFactory,
        array $folders = ['INBOX'],
        int $now = 0,
    ): array {
        $now = $now ?: time();
        if ($profile->status !== MailboxProfile::STATUS_CONFIGURED) {
            return ['result' => ConnectionResult::untested($profile->id, ConnectionResult::TARGET_IMAP), 'messages' => []];
        }
        if ($folders === [] || count($folders) > 20) {
            return ['result' => ConnectionResult::failure($profile->id, ConnectionResult::TARGET_IMAP, $now, 'Invalid folder list'), 'messages' => []];
        }
        $session = null;
        try {
            $socket = $socketFactory($profile);
            $session = new ImapSession($socket, $profile->imapEncryption === MailboxProfile::ENCRYPTION_SSL);
            $session->greeting();
            if ($profile->imapEncryption === MailboxProfile::ENCRYPTION_STARTTLS) {
                $session->startTls();
            }
            $session->login($username, $password);
            $messages = [];
            foreach ($folders as $folder) {
                $box = $session->examine($folder);
                $uids = array_slice($session->searchUnseen(), -self::MAX_MESSAGES_PER_FOLDER);
                foreach ($session->fetchSummaries($uids) as $row) {
                    $flags = array_values(array_intersect($row['flags'], MailMessage::FLAGS));
                    $messages[] = new MailMessage(
                        $row['uid'],
                        $box['uidValidity'],
                        $profile->id,
                        $folder,
                        $row['from'],
                        $row['subject'],
                        $row['date'],
                        $flags,
                    );
                }
            }
            return ['result' => ConnectionResult::success($profile->id, ConnectionResult::TARGET_IMAP, $now), 'messages' => $messages];
        } catch (MailTransportException $e) {
            return ['result' => ConnectionResult::failure($profile->id, ConnectionResult::TARGET_IMAP, $now, $e->kind() . ': ' . $e->getMessage()), 'messages' => []];
        } catch (\InvalidArgumentException $e) {
            return ['result' => ConnectionResult::failure($profile->id, ConnectionResult::TARGET_IMAP, $now, 'input: invalid request data'), 'messages' => []];
        } finally {
            $session?->logout();
        }
    }
}
