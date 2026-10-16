<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\ProjectPlanning;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once __DIR__.'/ProjectPlan.php';

/** Immutable public planning history. The native adapter must validate current access and the retained head. */
final readonly class ProjectPlanEvent
{
    public const MAX_BYTES = 786432;
    private const FIELDS = ['format', 'id', 'project_id', 'client_id', 'revision', 'kind', 'staff_id', 'occurred_at', 'before_sha256', 'before', 'after', 'changed_fields'];
    private function __construct(private string $bytes) {}
    private static function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    private static function number(mixed $value, bool $zero = false): void
    {
        if (!is_int($value) || $value < ($zero ? 0 : 1) || $value > 2147483647) {
            throw new \InvalidArgumentException('Invalid planning account or revision.');
        }
    }
    private static function id(mixed $value): void
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid plan event identity.');
        }
    }
    private static function digest(mixed $value): void
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid plan fingerprint.');
        }
    }
    public static function start(string $id, int $project, int $client, int $staff, int $at, ProjectPlan $plan): self
    {
        return self::fromJson(self::encode(['format' => 1, 'id' => $id, 'project_id' => $project, 'client_id' => $client, 'revision' => 0, 'kind' => 'plan_started', 'staff_id' => $staff, 'occurred_at' => $at, 'before_sha256' => null, 'before' => null, 'after' => $plan->data(), 'changed_fields' => []]));
    }
    public function update(string $id, int $staff, int $at, string $expected, ProjectPlan $after): self
    {
        self::digest($expected);
        if (!hash_equals($this->fingerprint(), $expected)) {
            throw new \LogicException('The project plan changed. Review its current version before saving.');
        }
        $before = $this->plan();
        $prior = $this->data();
        $event = self::fromJson(self::encode(['format' => 1, 'id' => $id, 'project_id' => $prior['project_id'], 'client_id' => $prior['client_id'], 'revision' => $prior['revision'] + 1, 'kind' => 'plan_updated', 'staff_id' => $staff, 'occurred_at' => $at, 'before_sha256' => $expected, 'before' => $before->data(), 'after' => $after->data(), 'changed_fields' => $after->changesFrom($before)]));
        $event->assertFollows($this);
        return $event;
    }
    public static function fromJson(string $bytes): self
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Plan event is too large.');
        }
        $data = json_decode($bytes, true, 20, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_keys($data) !== self::FIELDS || $data['format'] !== 1 || self::encode($data) !== $bytes) {
            throw new \InvalidArgumentException('Invalid or noncanonical plan event.');
        }
        self::id($data['id']);
        foreach (['project_id', 'client_id', 'staff_id'] as $field) {
            self::number($data[$field]);
        }
        self::number($data['revision'], true);
        if (!is_int($data['occurred_at']) || $data['occurred_at'] < 0 || $data['occurred_at'] > 253402300799 || !is_array($data['after'])) {
            throw new \InvalidArgumentException('Invalid plan time or snapshot.');
        }
        $after = ProjectPlan::fromArray($data['after']);
        if ($data['kind'] === 'plan_started') {
            if ($data['revision'] !== 0 || $data['before_sha256'] !== null || $data['before'] !== null || $data['changed_fields'] !== []) {
                throw new \InvalidArgumentException('A new plan cannot invent earlier changes.');
            }
        } elseif ($data['kind'] === 'plan_updated') {
            if ($data['revision'] < 1 || !is_array($data['before'])) {
                throw new \InvalidArgumentException('Invalid plan update.');
            }
            self::digest($data['before_sha256']);
            $before = ProjectPlan::fromArray($data['before']);
            $after->assertRevisionOf($before);
            $changes = $after->changesFrom($before);
            if (!$changes || $data['changed_fields'] !== $changes) {
                throw new \InvalidArgumentException('Record exactly the changed planning fields.');
            }
        } else {
            throw new \InvalidArgumentException('Unknown plan event kind.');
        }
        return new self($bytes);
    }
    public function assertFollows(self $previous): void
    {
        $data = $this->data();
        $prior = $previous->data();
        if ($data['kind'] !== 'plan_updated' || $data['id'] === $prior['id'] || $data['project_id'] !== $prior['project_id'] || $data['client_id'] !== $prior['client_id'] || $data['revision'] !== $prior['revision'] + 1 || $data['occurred_at'] < $prior['occurred_at'] || $data['before'] !== $prior['after'] || !hash_equals($previous->fingerprint(), $data['before_sha256'])) {
            throw new \LogicException('Saved planning history is inconsistent.');
        }
    }
    public static function verifyChain(array $events, ?self $preceding = null): void
    {
        if (!array_is_list($events) || count($events) < 1 || count($events) > 100) {
            throw new \InvalidArgumentException('Choose a bounded nonempty planning page.');
        }
        $seen = $preceding === null ? [] : [$preceding->data()['id'] => true];
        $previous = $preceding;
        foreach ($events as $event) {
            if (!$event instanceof self) {
                throw new \InvalidArgumentException('Invalid plan event.');
            }
            $data = $event->data();
            if (isset($seen[$data['id']])) {
                throw new \LogicException('Duplicate plan event identity.');
            }
            $seen[$data['id']] = true;
            if ($previous === null) {
                if ($data['kind'] !== 'plan_started') {
                    throw new \LogicException('Planning history needs its initial event or predecessor.');
                }
            } else {
                $event->assertFollows($previous);
            }
            $previous = $event;
        }
    }
    public function json(): string { return $this->bytes; }
    public function data(): array { return json_decode($this->bytes, true, 20, JSON_THROW_ON_ERROR); }
    public function fingerprint(): string { return hash('sha256', $this->bytes); }
    public function plan(): ProjectPlan { return ProjectPlan::fromArray($this->data()['after']); }
    public function forClient(int $client): array
    {
        $data = $this->data();
        if ($client !== $data['client_id']) {
            throw new \InvalidArgumentException('Project plan unavailable.');
        }
        return ['id' => $data['id'], 'revision' => $data['revision'], 'kind' => $data['kind'], 'occurred_at' => $data['occurred_at'], 'before' => $data['before'], 'after' => $data['after'], 'changed_fields' => $data['changed_fields']];
    }
}
