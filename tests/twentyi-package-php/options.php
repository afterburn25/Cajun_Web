<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__.'/../../foundation/twentyi-package-php/PackagePhpOptionSet.php';
use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpOptionSet,PackagePhpVersion};

$checks = [];
set_error_handler(static function(int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function option_check(string $name, bool $passed): void {
    global $checks;
    $checks[] = ['name' => $name, 'passed' => $passed];
    if (!$passed) throw new RuntimeException($name);
}
function option_refuses(string $name, callable $work): void {
    try { $work(); } catch (InvalidArgumentException) { option_check($name, true); return; }
    option_check($name, false);
}
function option_row(string $value = 'php82'): array { return ['value' => $value, 'label' => 'PHP 8.2', 'handler' => 'Synthetic handler']; }

try {
    $rows = [option_row('php82'), ['value' => 'php84', 'label' => 'PHP 8.4', 'handler' => 'Another synthetic handler']];
    $set = PackagePhpOptionSet::fromNormalizedRows($rows);
    option_check('Exact normalized rows and order retained', $set->options === $rows);
    option_check('Canonical whole-set identity uses format and every ordered field', $set->json === json_encode(['format' => 1, 'options' => $rows], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) && $set->sha256 === hash('sha256', $set->json));
    option_check('Record key order canonicalizes without changing set identity', PackagePhpOptionSet::fromNormalizedRows(array_map(static fn(array $r): array => array_reverse($r, true), $rows))->sha256 === $set->sha256);
    option_check('Option row reordering changes identity', PackagePhpOptionSet::fromNormalizedRows(array_reverse($rows))->sha256 !== $set->sha256);
    foreach (['value', 'label', 'handler'] as $field) {
        $changed = $rows; $changed[1][$field] .= '-changed';
        option_check('Complete identity includes later option '.$field, PackagePhpOptionSet::fromNormalizedRows($changed)->sha256 !== $set->sha256);
    }
    option_check('Exact selection returns the complete matching record', $set->select('php84') === $rows[1]);
    foreach (['php83', 'PHP84', ' php84', 'php84 ', 'php84/variant'] as $i => $value) {
        option_refuses('No selection normalization or fallback '.$i, static fn() => $set->select($value));
    }
    foreach ([82, 8.2, true, null, ['php82']] as $i => $value) {
        option_refuses('Selection requires scalar string '.$i, static fn() => $set->select($value));
    }
    $empty = PackagePhpOptionSet::fromNormalizedRows([]);
    option_check('Empty internal set is canonical and contains no choices', $empty->options === [] && $empty->json === '{"format":1,"options":[]}');
    option_refuses('Empty set cannot select any value', static fn() => $empty->select('php82'));
    option_refuses('Identical duplicate value refuses the entire set', static fn() => PackagePhpOptionSet::fromNormalizedRows([$rows[0], $rows[0]]));
    foreach (['label', 'handler'] as $field) {
        $other = $rows[0]; $other[$field] .= '-conflicting';
        option_refuses('Duplicate value with conflicting '.$field.' refuses', static fn() => PackagePhpOptionSet::fromNormalizedRows([$rows[0], $other]));
    }
    $numeric = PackagePhpOptionSet::fromNormalizedRows(array_map('option_row', ['0', '00', '1', '01', '1.0', '1e0', '+1']));
    foreach (['0', '00', '1', '01', '1.0', '1e0', '+1'] as $value) {
        option_check('Numeric-looking value remains exact '.$value, $numeric->select($value)['value'] === $value);
    }
    option_refuses('Numeric input cannot select numeric-looking string', static fn() => $numeric->select(1));
    foreach ([['x' => option_row()], [1 => option_row()], [option_row(), 3 => option_row('php84')]] as $i => $bad) {
        option_refuses('Non-list normalized collection refused '.$i, static fn() => PackagePhpOptionSet::fromNormalizedRows($bad));
    }
    foreach ([(object)option_row(), null, 'raw text', [option_row()]] as $i => $bad) {
        option_refuses('Non-record normalized row refused '.$i, static fn() => PackagePhpOptionSet::fromNormalizedRows([$bad]));
    }
    foreach (['value', 'label', 'handler'] as $field) {
        $bad = option_row(); unset($bad[$field]);
        option_refuses('Required normalized field '.$field, static fn() => PackagePhpOptionSet::fromNormalizedRows([$bad]));
    }
    option_refuses('Unknown provider field is not silently projected', static fn() => PackagePhpOptionSet::fromNormalizedRows([option_row() + ['private' => 'not-for-this-layer']]));
    foreach (['value', 'label', 'handler'] as $field) {
        $bad = option_row(); $bad[$field] = null;
        option_refuses('Null is not guessed for '.$field, static fn() => PackagePhpOptionSet::fromNormalizedRows([$bad]));
    }
    foreach (['', '   ', "php82\n", "php82\0", str_repeat('x', 129), "\xff"] as $i => $value) {
        $bad = option_row(); $bad['value'] = $value;
        option_refuses('Existing version-value validation retained '.$i, static fn() => PackagePhpOptionSet::fromNormalizedRows([$bad]));
    }
    foreach (['label', 'handler'] as $field) {
        foreach (['', '  ', "\u{00a0}\u{2003}", "line\nfeed", "tab\t", "nul\0", "del\x7f", "next\u{0085}line", "\u{202e}bidi", "zero\u{200d}width", "\xff"] as $i => $value) {
            $bad = option_row(); $bad[$field] = $value;
            option_refuses('Display '.$field.' control/blank/UTF8 refusal '.$i, static fn() => PackagePhpOptionSet::fromNormalizedRows([$bad]));
        }
    }
    foreach (['label' => 256, 'handler' => 128] as $field => $maximum) {
        $valid = option_row(); $valid[$field] = str_repeat('x', $maximum);
        option_check('Exact display byte boundary accepted '.$field, PackagePhpOptionSet::fromNormalizedRows([$valid])->options[0][$field] === $valid[$field]);
        $valid[$field] .= 'x'; option_refuses('Display byte overflow refused '.$field, static fn() => PackagePhpOptionSet::fromNormalizedRows([$valid]));
        $valid[$field] = str_repeat('é', intdiv($maximum, 2));
        option_check('UTF8 bytes not character count bound '.$field, PackagePhpOptionSet::fromNormalizedRows([$valid])->options[0][$field] === $valid[$field]);
        $valid[$field] .= 'é'; option_refuses('Multibyte display byte overflow refused '.$field, static fn() => PackagePhpOptionSet::fromNormalizedRows([$valid]));
    }
    $maxRows = []; for ($i = 0; $i < 128; $i++) $maxRows[] = option_row('local-test-'.$i);
    option_check('Exact local option count boundary accepted', count(PackagePhpOptionSet::fromNormalizedRows($maxRows)->options) === 128);
    $maxRows[] = option_row('overflow'); option_refuses('Option count overflow refuses whole set', static fn() => PackagePhpOptionSet::fromNormalizedRows($maxRows));
    $large = []; for ($i = 0; $i < 128; $i++) $large[] = ['value' => 'local-'.$i, 'label' => str_repeat('é', 128), 'handler' => str_repeat('é', 64)];
    option_refuses('Whole encoded JSON capacity refuses without truncation', static fn() => PackagePhpOptionSet::fromNormalizedRows($large));
    $literal = ['value' => '  custom/value  ', 'label' => '<script>" & é</script>', 'handler' => '<b>literal & handler</b>'];
    $literalSet = PackagePhpOptionSet::fromNormalizedRows([$literal]);
    option_check('Version strings use existing validator without hardcoded PHP enumeration', $literalSet->select(PackagePhpVersion::value($literal['value'])) === $literal);
    option_check('Display markup remains plain data; renderer must escape it', $literalSet->options[0]['label'] === $literal['label'] && str_contains($literalSet->json, '<script>'));
    option_check('Consumer HTML escaping remains lossless and separate', htmlspecialchars($literalSet->options[0]['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') === '&lt;script&gt;&quot; &amp; é&lt;/script&gt;');
    $rows[0]['label'] = 'caller-mutated'; option_check('Stored options detach from caller arrays', $set->options[0]['label'] === 'PHP 8.2');
    $selected = $set->select('php82'); $selected['label'] = 'selection-mutated'; option_check('Returned selection cannot mutate retained identity', $set->select('php82')['label'] === 'PHP 8.2');
    option_check('Constructor is private and class is final readonly', (new ReflectionClass(PackagePhpOptionSet::class))->getConstructor()->isPrivate() && (new ReflectionClass(PackagePhpOptionSet::class))->isFinal() && (new ReflectionClass(PackagePhpOptionSet::class))->isReadOnly());
    $immutable = false; try { $set->options[0]['label'] = 'changed'; } catch (Error) { $immutable = true; }
    option_check('Public retained rows cannot be modified', $immutable && $set->sha256 === hash('sha256', $set->json));
    $blocked = false; try { unserialize(serialize($set), ['allowed_classes' => [PackagePhpOptionSet::class]]); } catch (LogicException) { $blocked = true; }
    option_check('PHP serialization cannot bypass the normalized factory', $blocked);
    option_check('No raw-provider JSON decoder or transport API exists', !method_exists(PackagePhpOptionSet::class, 'fromJson') && !method_exists(PackagePhpOptionSet::class, 'project') && !method_exists(PackagePhpOptionSet::class, 'read'));
    $wrongInput = false; try { PackagePhpOptionSet::fromNormalizedRows('[{"value":"php82"}]'); } catch (TypeError) { $wrongInput = true; }
    option_check('Raw JSON string cannot enter normalized row factory', $wrongInput);
    echo json_encode(['passed' => true, 'count' => count($checks), 'checks' => $checks, 'scope' => 'Pure normalized application rows only; no provider response/schema/authority or runtime acceptance.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    echo json_encode(['passed' => false, 'checks' => $checks, 'failure' => $error->getMessage()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
