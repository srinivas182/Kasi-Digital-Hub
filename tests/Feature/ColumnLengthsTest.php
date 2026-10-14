<?php

declare(strict_types=1);

/*
| Every value documented next to a string column in a migration ("// a | b | c" or ->comment('a | b')) must
| fit the column's length. SQLite ignores lengths; MySQL rejects or cuts the value (S19: order status
| "part_refunded", S14: question kind "truefalse"). Later migrations that widen a column count.
*/
it('has string columns long enough for their documented values', function (): void {
    $widened = [];
    $problems = [];
    $files = [...(glob(base_path('database/migrations/*.php')) ?: []), ...(glob(base_path('modules/*/database/migrations/*.php')) ?: [])];
    usort($files, static fn (string $a, string $b): int => strcmp(basename($a), basename($b)));

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        preg_match_all("/string\\('(\\w+)', (\\d+)\\)[^;]*?->change\\(\\)/", $source, $changes, PREG_SET_ORDER);
        foreach ($changes as [, $column, $length]) {
            $widened[$column] = max($widened[$column] ?? 0, (int) $length);
        }
        preg_match_all("/string\\('(\\w+)', (\\d+)\\)[^;]*?(?:->comment\\('([^']*)'\\))?[^;]*;[ \\t]*(?:\\/\\/[ \\t]*([^\\n]+))?/", $source, $columns, PREG_SET_ORDER);
        foreach ($columns as $match) {
            $comment = preg_replace('/\\(.*?\\)/', '', ($match[3] ?? '').' '.($match[4] ?? '')) ?? '';
            $values = array_filter(array_map('trim', preg_split('/[|,]/', $comment) ?: []), static fn (string $v): bool => preg_match('/^[a-z_]+$/', $v) === 1);
            foreach ($values as $value) {
                if (strlen($value) > (int) $match[2]) {
                    $problems["{$match[1]}:{$value}"] = [basename($file), $match[1], (int) $match[2], $value];
                }
            }
        }
    }
    $remaining = array_filter($problems, static fn (array $p): bool => strlen($p[3]) > ($widened[$p[1]] ?? 0));

    expect(array_values(array_map(static fn (array $p): string => "{$p[0]}: {$p[1]}({$p[2]}) can't hold \"{$p[3]}\"", $remaining)))->toBe([]);
});
