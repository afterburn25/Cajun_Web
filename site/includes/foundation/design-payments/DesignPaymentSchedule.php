<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\DesignPayments;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Immutable quoted allocations. This class grants no access and records no payment. */
final readonly class DesignPaymentSchedule
{
    public const MAX_CENTS = 99999999;
    public const MAX_BYTES = 131072;
    public const FIELDS = ['format', 'id', 'client_id', 'project_id', 'proposal', 'currency', 'title', 'terms', 'items', 'stages'];
    public const PROPOSAL_FIELDS = ['id', 'version', 'content_sha256'];
    public const ITEM_FIELDS = ['id', 'description', 'quantity', 'unit_cents', 'discount_cents', 'tax_cents'];
    public const STAGE_FIELDS = ['id', 'kind', 'title', 'calculation', 'value', 'due_date', 'milestone_id'];

    private function __construct(private string $bytes, private array $quote, private array $allocations) {}

    public static function fromArray(array $data): self
    {
        self::shape($data, self::FIELDS);
        if ($data['format'] !== 1 || $data['currency'] !== 'USD') {
            throw new \InvalidArgumentException('Choose the supported schedule format and USD currency.');
        }
        self::id($data['id']);
        self::integer($data['client_id'], 1, 2147483647);
        self::integer($data['project_id'], 1, 2147483647);
        self::shape($data['proposal'], self::PROPOSAL_FIELDS);
        self::integer($data['proposal']['id'], 1, 2147483647);
        self::integer($data['proposal']['version'], 1, 2147483647);
        if (!is_string($data['proposal']['content_sha256']) || preg_match('/\A[a-f0-9]{64}\z/', $data['proposal']['content_sha256']) !== 1) {
            throw new \InvalidArgumentException('Bind the exact proposal content.');
        }
        self::text($data['title'], 160, false);
        self::text($data['terms'], 30000, true);
        self::boundedList($data['items'], 50);
        self::boundedList($data['stages'], 32);

        $quote = ['subtotal_cents' => 0, 'discount_cents' => 0, 'tax_cents' => 0, 'total_cents' => 0, 'items' => []];
        $seen = [];
        foreach ($data['items'] as $item) {
            self::shape($item, self::ITEM_FIELDS);
            self::uniqueId($item['id'], $seen);
            self::text($item['description'], 2000, true);
            self::integer($item['quantity'], 1, 100);
            self::integer($item['unit_cents'], 1, self::MAX_CENTS);
            if ($item['unit_cents'] > intdiv(self::MAX_CENTS, $item['quantity'])) {
                throw new \InvalidArgumentException('The item subtotal exceeds the supported amount.');
            }
            $subtotal = $item['quantity'] * $item['unit_cents'];
            self::integer($item['discount_cents'], 0, $subtotal);
            self::integer($item['tax_cents'], 0, self::MAX_CENTS);
            $net = $subtotal - $item['discount_cents'];
            if ($item['tax_cents'] > self::MAX_CENTS - $net || $net + $item['tax_cents'] < 1) {
                throw new \InvalidArgumentException('Each quoted item needs a positive supported total.');
            }
            $total = $net + $item['tax_cents'];
            // Bound all displayed aggregate amounts before adding, including gross discounts.
            foreach (['subtotal_cents' => $subtotal, 'discount_cents' => $item['discount_cents'], 'tax_cents' => $item['tax_cents'], 'total_cents' => $total] as $field => $amount) {
                if ($quote[$field] > self::MAX_CENTS - $amount) {
                    throw new \InvalidArgumentException('The quoted amounts exceed the supported total.');
                }
                $quote[$field] += $amount;
            }
            $quote['items'][] = $item + ['subtotal_cents' => $subtotal, 'total_cents' => $total];
        }

        $remaining = $quote['total_cents'];
        $seen = [];
        $allocations = [];
        $last = count($data['stages']) - 1;
        foreach ($data['stages'] as $index => $stage) {
            self::shape($stage, self::STAGE_FIELDS);
            self::uniqueId($stage['id'], $seen);
            self::text($stage['title'], 160, false);
            self::date($stage['due_date']);
            if ($stage['kind'] === 'milestone') {
                self::id($stage['milestone_id']);
            } elseif ($stage['milestone_id'] !== '') {
                throw new \InvalidArgumentException('Only milestone installments may reference a milestone.');
            }
            if ($index === $last) {
                if ($stage['kind'] !== ($last === 0 ? 'full' : 'final') || $stage['calculation'] !== 'remainder' || $stage['value'] !== 0) {
                    throw new \InvalidArgumentException('End with the exact full payment or final remainder.');
                }
                $amount = $remaining;
            } else {
                if (!in_array($stage['kind'], ['deposit', 'milestone'], true) || ($stage['kind'] === 'deposit' && $index !== 0)) {
                    throw new \InvalidArgumentException('Use an optional first deposit, milestones and a final balance.');
                }
                if ($stage['calculation'] === 'fixed') {
                    self::integer($stage['value'], 1, self::MAX_CENTS);
                    $amount = $stage['value'];
                } elseif ($stage['calculation'] === 'percent') {
                    self::integer($stage['value'], 1, 10000);
                    // Split the product to preserve exact half-up rounding even on 32-bit PHP.
                    $amount = intdiv($quote['total_cents'], 10000) * $stage['value']
                        + intdiv(($quote['total_cents'] % 10000) * $stage['value'] + 5000, 10000);
                } else {
                    throw new \InvalidArgumentException('Installments require fixed cents or basis points of the quote.');
                }
                if ($amount >= $remaining) {
                    throw new \InvalidArgumentException('Installments must leave a positive final balance.');
                }
            }
            if ($amount < 1) {
                throw new \InvalidArgumentException('Every scheduled payment must be at least one cent.');
            }
            $remaining -= $amount;
            $allocations[] = $stage + ['amount_cents' => $amount, 'remaining_scheduled_cents' => $remaining];
        }
        $bytes = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('The payment schedule exceeds the supported record size.');
        }
        return new self($bytes, $quote, $allocations);
    }

    public function data(): array { return json_decode($this->bytes, true, 16, JSON_THROW_ON_ERROR); }
    public function json(): string { return $this->bytes; }
    public function fingerprint(): string { return hash('sha256', $this->bytes); }
    public function quote(): array { return $this->quote; }
    public function stages(): array { return $this->allocations; }
    public function stage(string $id): array
    {
        self::id($id);
        foreach ($this->allocations as $stage) {
            if ($stage['id'] === $id) { return $stage; }
        }
        throw new \InvalidArgumentException('Payment stage unavailable.');
    }

    private static function shape(mixed $data, array $fields): void
    {
        if (!is_array($data) || array_keys($data) !== $fields) {
            throw new \InvalidArgumentException('Invalid payment schedule fields or order.');
        }
    }
    private static function integer(mixed $value, int $min, int $max): void
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new \InvalidArgumentException('Use an integer within the supported range.');
        }
    }
    private static function id(mixed $id): void
    {
        if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/', $id) !== 1) {
            throw new \InvalidArgumentException('Invalid financial record identity.');
        }
    }
    private static function uniqueId(mixed $id, array &$seen): void
    {
        self::id($id);
        if (isset($seen[$id])) { throw new \InvalidArgumentException('Financial record identities must be unique.'); }
        $seen[$id] = true;
    }
    private static function boundedList(mixed $values, int $max): void
    {
        if (!is_array($values) || !array_is_list($values) || count($values) < 1 || count($values) > $max) {
            throw new \InvalidArgumentException('Use a nonempty ordered list within the supported bound.');
        }
    }
    private static function text(mixed $text, int $max, bool $multiline): void
    {
        $control = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        if (!is_string($text) || strlen($text) > $max || preg_match('//u', $text) !== 1 || preg_match($control, $text) || preg_match('/\S/u', $text) !== 1) {
            throw new \InvalidArgumentException('Use visible, bounded UTF-8 text without control characters.');
        }
    }
    private static function date(mixed $value): void
    {
        if ($value === '') { return; }
        if (!is_string($value) || preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $value, $parts) !== 1 || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
            throw new \InvalidArgumentException('Use a real date in YYYY-MM-DD form or leave it blank.');
        }
    }
}
