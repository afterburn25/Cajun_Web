<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\RecurringBilling;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

require_once __DIR__.'/../billing-calendar/BillingCalendar.php';

use CajunWebPro\Foundation\BillingCalendar\BillingCalendar;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

/** Exact offered terms and proposed period facts, never authority to issue or collect. */
final readonly class RecurringBillingTerms
{
    public const MAX_BYTES = 32768;
    public const MAX_EPOCH = 253402300799;
    private const FIELDS = ['format', 'id', 'client_id', 'project_id', 'source_subscription',
        'title', 'terms', 'currency', 'amount_cents', 'cadence', 'anchor', 'alignment',
        'timezone', 'due_after_days', 'collection'];
    private const SOURCE_FIELDS = ['id', 'revision', 'sha256'];

    private function __construct(
        private string $bytes,
        private BillingCalendar $calendar,
        private DateTimeZone $timezone,
    ) {}

    public static function fromArray(mixed $data): self
    {
        self::keys($data, self::FIELDS);
        if ($data['format'] !== 1) {
            throw new InvalidArgumentException('Unsupported recurring billing terms format.');
        }
        self::hex($data['id'], 32);
        self::integer($data['client_id'], 1, 2147483647);
        self::integer($data['project_id'], 1, 2147483647);
        $source = $data['source_subscription'];
        self::keys($source, self::SOURCE_FIELDS);
        self::hex($source['id'], 32);
        self::integer($source['revision'], 1, 256);
        self::hex($source['sha256'], 64);
        self::text($data['title'], 120, false);
        self::text($data['terms'], 16384, true);
        if ($data['currency'] !== 'USD' || $data['collection'] !== 'invoice-only') {
            throw new InvalidArgumentException('Choose supported invoice currency and collection terms.');
        }
        self::integer($data['amount_cents'], 0, 99999999);
        self::integer($data['due_after_days'], 0, 60);
        $calendar = new BillingCalendar($data['anchor'], $data['cadence'], $data['alignment']);
        $calendar->period(0); // Even the first whole period must have a representable end.
        if (!is_string($data['timezone']) || !in_array($data['timezone'], DateTimeZone::listIdentifiers(DateTimeZone::ALL), true)) {
            throw new InvalidArgumentException('Choose an explicit supported billing timezone.');
        }
        $canonical = [];
        foreach (self::FIELDS as $field) {
            $canonical[$field] = $data[$field];
        }
        $canonical['source_subscription'] = [];
        foreach (self::SOURCE_FIELDS as $field) {
            $canonical['source_subscription'][$field] = $source[$field];
        }
        $bytes = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Recurring billing terms are too large.');
        }
        return new self($bytes, $calendar, new DateTimeZone($data['timezone']));
    }

    public static function fromJson(mixed $bytes): self
    {
        if (!is_string($bytes) || strlen($bytes) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Invalid recurring billing terms document.');
        }
        try {
            $data = json_decode($bytes, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Invalid recurring billing terms JSON.', 0, $error);
        }
        $terms = self::fromArray($data);
        if ($terms->json() !== $bytes) {
            throw new InvalidArgumentException('Recurring billing terms must use their exact canonical representation.');
        }
        return $terms;
    }

    public function data(): array { return json_decode($this->bytes, true, 8, JSON_THROW_ON_ERROR); }
    public function json(): string { return $this->bytes; }
    public function fingerprint(): string { return hash('sha256', $this->bytes); }

    /** These facts do not assert consent, active status, billability or an issued invoice. */
    public function period(mixed $index): array
    {
        $period = $this->calendar->period($index);
        $data = $this->data();
        // Calendar-day arithmetic in UTC avoids 23/25-hour local days changing a due date.
        $due = (new DateTimeImmutable($period['start'].'T00:00:00', new DateTimeZone('UTC')))
            ->add(new DateInterval('P'.$data['due_after_days'].'D'))->format('Y-m-d');
        if (!preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $due)) {
            throw new InvalidArgumentException('Billing due date exceeds the supported date range.');
        }
        return ['key' => $data['id'].':'.$period['index'], 'index' => $period['index'],
            'start' => $period['start'], 'end' => $period['end'], 'due' => $due,
            'timezone' => $data['timezone'], 'currency' => $data['currency'], 'amount_cents' => $data['amount_cents']];
    }

    public function billingDate(mixed $epoch): string
    {
        self::integer($epoch, 0, self::MAX_EPOCH);
        $date = (new DateTimeImmutable('@'.$epoch))->setTimezone($this->timezone)->format('Y-m-d');
        if (!preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $date)) {
            throw new InvalidArgumentException('Local billing date exceeds the supported date range.');
        }
        return $date;
    }

    public function periodAt(mixed $epoch): ?array
    {
        $period = $this->calendar->periodAt($this->billingDate($epoch));
        return $period === null ? null : $this->period($period['index']);
    }

    private static function keys(mixed $data, array $fields): void
    {
        if (!is_array($data) || count($data) !== count($fields)) {
            throw new InvalidArgumentException('Invalid recurring billing terms fields.');
        }
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException('Missing recurring billing terms field.');
            }
        }
    }

    private static function integer(mixed $value, int $minimum, int $maximum): void
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('Recurring billing terms number is outside its supported range.');
        }
    }

    private static function hex(mixed $value, int $length): void
    {
        if (!is_string($value) || !preg_match('/\A[a-f0-9]{'.$length.'}\z/', $value)) {
            throw new InvalidArgumentException('Invalid recurring billing terms identity or fingerprint.');
        }
    }

    private static function text(mixed $value, int $limit, bool $multiline): void
    {
        if (!is_string($value) || strlen($value) > $limit || !preg_match('//u', $value)
            || !preg_match('/[^\s\p{Z}]/u', $value)
            || preg_match($multiline ? '/[\x00-\x08\x0b-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Recurring billing terms need bounded literal text.');
        }
    }
}
