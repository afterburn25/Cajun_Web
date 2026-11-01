<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Desk;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

require_once __DIR__ . '/TicketMessage.php';
require_once __DIR__ . '/TraceLink.php';

/**
 * HC-12 — an internal support ticket: the service-desk record for a
 * client conversation, kept separate from mailbox mail.
 *
 * Status: `open → pending → resolved → closed` (terminal). `resolved`
 * can reopen to `open` — a fix that didn't take is honestly recorded,
 * not hidden. Assignment to a staff ref is explicit; a ticket is never
 * assigned to nobody silently. Optional trace links connect it to the
 * order/service/invoice it concerns.
 *
 * @internal Roadmap HC-12; additively inserted.
 */
final class SupportTicket
{
    public const OPEN = 'open';
    public const PENDING = 'pending';
    public const RESOLVED = 'resolved';
    public const CLOSED = 'closed';

    private const FLOW = [
        self::OPEN => [self::PENDING, self::RESOLVED, self::CLOSED],
        self::PENDING => [self::OPEN, self::RESOLVED, self::CLOSED],
        self::RESOLVED => [self::OPEN, self::CLOSED],
        self::CLOSED => [],
    ];

    private string $status = self::OPEN;
    private ?string $assigneeRef = null;
    /** @var list<TicketMessage> */
    private array $messages = [];
    /** @var list<array{status:string,at:int,actor:string,note:string}> */
    private array $history = [];

    /**
     * @param list<TraceLink> $trace related records (order/service/invoice)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $clientRef,
        public readonly string $subject,
        public readonly array $trace = [],
    ) {
        foreach (['id' => $id, 'clientRef' => $clientRef, 'subject' => $subject] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Ticket {$f} must not be empty.");
            }
        }
        foreach ($trace as $l) {
            if (!$l instanceof TraceLink) {
                throw new InvalidArgumentException('Ticket trace entries must be TraceLink records.');
            }
        }
        $this->history[] = ['status' => self::OPEN, 'at' => 0, 'actor' => '', 'note' => 'opened'];
    }

    public function status(): string
    {
        return $this->status;
    }

    public function assigneeRef(): ?string
    {
        return $this->assigneeRef;
    }

    public function history(): array
    {
        return $this->history;
    }

    public function assign(string $staffRef, string $actor, int $at): void
    {
        if (trim($staffRef) === '') {
            throw new InvalidArgumentException('An assignment needs a staff reference.');
        }
        $this->assigneeRef = $staffRef;
        $this->history[] = ['status' => $this->status, 'at' => $at, 'actor' => $actor, 'note' => 'assigned to ' . $staffRef];
    }

    public function transition(string $to, string $actor, int $at): void
    {
        if (!in_array($to, self::FLOW[$this->status], true)) {
            throw new LogicException("Ticket cannot move {$this->status} → {$to}.");
        }
        $this->move($to, $actor, $at, 'status ' . $to);
    }

    /** Record a message. Internal notes never become client-visible. */
    public function post(TicketMessage $message, string $actor, int $at): void
    {
        if ($this->status === self::CLOSED) {
            throw new LogicException('Cannot post to a closed ticket.');
        }
        if ($message->ticketId !== $this->id) {
            throw new InvalidArgumentException('Message belongs to a different ticket.');
        }
        $this->messages[] = $message;
        $this->history[] = ['status' => $this->status, 'at' => $at, 'actor' => $actor, 'note' => 'message ' . $message->id];
    }

    /** Messages the client may see — internal staff notes excluded. */
    public function clientMessages(): array
    {
        return array_values(array_filter($this->messages, static fn (TicketMessage $m) => $m->clientVisible()));
    }

    /** All messages including internal notes — staff view only. */
    public function allMessages(): array
    {
        return $this->messages;
    }

    /**
     * Newer messages not authored by the reader. Staff pass
     * `$includeInternal` so staff-only notes count for them; clients
     * must pass false so internal notes stay invisible even in counts.
     */
    public function unreadFor(string $readerRef, int $sinceAt, bool $includeInternal = false): int
    {
        $pool = $includeInternal ? $this->messages : $this->clientMessages();
        return count(array_filter(
            $pool,
            static fn (TicketMessage $m) => $m->createdAt > $sinceAt && $m->authorRef !== $readerRef
        ));
    }

    private function move(string $status, string $actor, int $at, string $note): void
    {
        $this->status = $status;
        $this->history[] = ['status' => $status, 'at' => $at, 'actor' => $actor, 'note' => $note];
    }
}
