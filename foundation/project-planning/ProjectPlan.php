<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\ProjectPlanning;

/** Exact public planning facts; persistence and current access belong to the adapter. */
final readonly class ProjectPlan
{
    public const MAX_BYTES = 262144;
    public const FIELDS = ['scope', 'assigned_contact', 'target_date', 'client_action', 'blockers', 'milestones'];
    public const MILESTONE_FIELDS = ['id', 'title', 'details', 'state', 'owner', 'due_date', 'depends_on'];
    public const STATES = ['planned', 'in_progress', 'awaiting_client', 'blocked', 'completed', 'cancelled'];

    private function __construct(private string $bytes) {}

    public static function fromArray(array $data): self
    {
        if (array_keys($data) !== self::FIELDS) {
            throw new \InvalidArgumentException('Invalid project plan fields or order.');
        }
        foreach (['scope' => 10000, 'client_action' => 4000, 'blockers' => 4000] as $field => $limit) {
            self::text($data[$field], $limit, true);
        }
        self::text($data['assigned_contact'], 160, false);
        if ($data['assigned_contact'] !== '' && preg_match('/\S/u', $data['assigned_contact']) !== 1) {
            throw new \InvalidArgumentException('An assigned contact needs a visible label.');
        }
        self::date($data['target_date']);
        if (!is_array($data['milestones']) || !array_is_list($data['milestones']) || count($data['milestones']) > 100) {
            throw new \InvalidArgumentException('Choose at most 100 retained milestones.');
        }
        $byId = [];
        foreach ($data['milestones'] as $milestone) {
            if (!is_array($milestone) || array_keys($milestone) !== self::MILESTONE_FIELDS) {
                throw new \InvalidArgumentException('Invalid milestone fields or order.');
            }
            self::id($milestone['id']);
            if (isset($byId[$milestone['id']])) {
                throw new \InvalidArgumentException('Milestone identities must be unique.');
            }
            self::text($milestone['title'], 160, false);
            if (preg_match('/\S/u', $milestone['title']) !== 1) {
                throw new \InvalidArgumentException('A milestone needs a title.');
            }
            self::text($milestone['details'], 2000, true);
            if (!in_array($milestone['state'], self::STATES, true) || !in_array($milestone['owner'], ['studio', 'client'], true)) {
                throw new \InvalidArgumentException('Choose a milestone state and responsible party.');
            }
            self::date($milestone['due_date']);
            $dependencies = $milestone['depends_on'];
            if (!is_array($dependencies) || !array_is_list($dependencies) || count($dependencies) > 8) {
                throw new \InvalidArgumentException('Choose at most eight milestone dependencies.');
            }
            foreach ($dependencies as $dependency) {
                self::id($dependency);
                if ($dependency === $milestone['id']) {
                    throw new \InvalidArgumentException('A milestone cannot depend on itself.');
                }
            }
            $ordered = $dependencies;
            sort($ordered, SORT_STRING);
            if ($ordered !== $dependencies || count(array_unique($dependencies)) !== count($dependencies)) {
                throw new \InvalidArgumentException('Dependencies must be distinct and ordered.');
            }
            $byId[$milestone['id']] = $milestone;
        }
        foreach ($byId as $milestone) {
            foreach ($milestone['depends_on'] as $dependency) {
                if (!isset($byId[$dependency])) {
                    throw new \InvalidArgumentException('A dependency must belong to this project plan.');
                }
                if ($milestone['state'] === 'completed' && $byId[$dependency]['state'] !== 'completed') {
                    throw new \InvalidArgumentException('Complete the dependencies before completing this milestone.');
                }
            }
        }
        $visiting = [];
        $visited = [];
        $visit = function (string $id) use (&$visit, &$visiting, &$visited, $byId): void {
            if (isset($visiting[$id])) {
                throw new \InvalidArgumentException('Milestone dependencies cannot form a cycle.');
            }
            if (isset($visited[$id])) {
                return;
            }
            $visiting[$id] = true;
            foreach ($byId[$id]['depends_on'] as $dependency) {
                $visit($dependency);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach (array_keys($byId) as $id) {
            $visit($id);
        }
        $bytes = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Project plan is too large.');
        }
        return new self($bytes);
    }

    public static function fromJson(string $bytes): self
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Project plan is too large.');
        }
        $data = json_decode($bytes, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid project plan.');
        }
        $plan = self::fromArray($data);
        if ($plan->json() !== $bytes) {
            throw new \InvalidArgumentException('Project plan must use exact canonical JSON.');
        }
        return $plan;
    }

    private static function text(mixed $value, int $limit, bool $multiline): void
    {
        $control = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        if (!is_string($value) || strlen($value) > $limit || preg_match('//u', $value) !== 1 || preg_match($control, $value)) {
            throw new \InvalidArgumentException('Invalid or oversized planning text.');
        }
    }

    private static function date(mixed $value): void
    {
        if ($value === null) {
            return;
        }
        if (!is_string($value) || preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $value, $parts) !== 1
            || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
            throw new \InvalidArgumentException('Choose a valid calendar date.');
        }
    }

    private static function id(mixed $value): void
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid milestone identity.');
        }
    }

    public function json(): string { return $this->bytes; }
    public function data(): array { return json_decode($this->bytes, true, 16, JSON_THROW_ON_ERROR); }
    public function fingerprint(): string { return hash('sha256', $this->bytes); }

    public function changesFrom(self $before): array
    {
        $after = $this->data();
        $prior = $before->data();
        return array_values(array_filter(self::FIELDS, static fn(string $field): bool => $after[$field] !== $prior[$field]));
    }

    public function assertRevisionOf(self $before): void
    {
        $retained = array_column($this->data()['milestones'], 'id');
        foreach ($before->data()['milestones'] as $milestone) {
            if (!in_array($milestone['id'], $retained, true)) {
                throw new \LogicException('Retain earlier milestones; cancel them explicitly instead.');
            }
        }
    }
}
