<?php

namespace App\Modules\PageBuilder\Services;

/**
 * Line-based diff between two builder HTML strings for the revision history
 * panel. HTML is split on tag boundaries so each line is a readable unit; a
 * common prefix/suffix trim keeps the O(n*m) LCS inside a bounded budget, and
 * oversized inputs are reported as truncated instead of stalling the request.
 */
class HtmlLineDiff
{
    private const MAX_LINES = 600;

    /**
     * @return array{lines: list<array{t: string, s: string}>, truncated: bool}
     */
    public function compare(?string $old, ?string $new): array
    {
        $a = $this->lines($old);
        $b = $this->lines($new);

        $truncated = count($a) >= self::MAX_LINES || count($b) >= self::MAX_LINES;

        // Drop the identical head and tail so the quadratic DP only sees the
        // changed region in the common "one small edit in a big page" case.
        $prefix = 0;
        $max = min(count($a), count($b));
        while ($prefix < $max && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }

        $suffix = 0;
        while ($suffix < ($max - $prefix)
            && $a[count($a) - 1 - $suffix] === $b[count($b) - 1 - $suffix]) {
            $suffix++;
        }

        $lines = [];

        for ($i = 0; $i < $prefix; $i++) {
            $lines[] = ['t' => 'same', 's' => $a[$i]];
        }

        foreach ($this->lcs(
            array_slice($a, $prefix, count($a) - $prefix - $suffix),
            array_slice($b, $prefix, count($b) - $prefix - $suffix),
        ) as $op) {
            $lines[] = $op;
        }

        for ($i = count($b) - $suffix; $i < count($b); $i++) {
            $lines[] = ['t' => 'same', 's' => $b[$i]];
        }

        return ['lines' => $lines, 'truncated' => $truncated];
    }

    /**
     * @return list<string>
     */
    private function lines(?string $html): array
    {
        $html = trim((string) $html);

        if ($html === '') {
            return [];
        }

        $parts = preg_split('/>\s*</', $html) ?: [];
        $lines = [];

        foreach ($parts as $index => $part) {
            $line = trim($part);

            if ($index > 0) {
                $line = '<'.$line;
            }

            if ($index < count($parts) - 1) {
                $line .= '>';
            }

            $line = trim($line);

            if ($line !== '') {
                $lines[] = $line;
            }

            if (count($lines) >= self::MAX_LINES) {
                break;
            }
        }

        return $lines;
    }

    /**
     * Classic LCS producing add/del/same operations over the middle slice.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{t: string, s: string}>
     */
    private function lcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if ($n === 0 && $m === 0) {
            return [];
        }

        if ($n === 0) {
            return array_map(fn (string $s): array => ['t' => 'add', 's' => $s], $b);
        }

        if ($m === 0) {
            return array_map(fn (string $s): array => ['t' => 'del', 's' => $s], $a);
        }

        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] === $b[$j]
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['t' => 'same', 's' => $a[$i]];
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $ops[] = ['t' => 'del', 's' => $a[$i]];
                $i++;
            } else {
                $ops[] = ['t' => 'add', 's' => $b[$j]];
                $j++;
            }
        }

        while ($i < $n) {
            $ops[] = ['t' => 'del', 's' => $a[$i]];
            $i++;
        }

        while ($j < $m) {
            $ops[] = ['t' => 'add', 's' => $b[$j]];
            $j++;
        }

        return $ops;
    }
}
