<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\QuickOrder\Model;

/**
 * What the quick order page posts, as one list of SKU and quantity: the
 * rows of the form, a pasted list ("SKU, 2" / "SKU 2" / "SKU;2" / just the
 * SKU, one per line) and a CSV file (SKU in the first column, quantity in the
 * second, a header row skipped). The same SKU twice adds up.
 */
class Parser
{
    public const MAX_LINES = 200;

    /**
     * @param array<int, array{sku?: string, qty?: string|int|float}> $rows The form's items[][sku] / items[][qty]
     * @return array<string, float> SKU => quantity
     */
    public static function rows(array $rows): array
    {
        $lines = [];
        foreach ($rows as $row) {
            $sku = $row['sku'] ?? '';
            $qty = $row['qty'] ?? '1';
            // Only text counts: a nested array (items[0][sku][]=x) is skipped, not turned into "Array".
            self::put($lines, is_scalar($sku) ? (string) $sku : '', is_scalar($qty) ? (string) $qty : '1');
        }

        return $lines;
    }

    /**
     * @return array<string, float> SKU => quantity
     */
    public static function text(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $parts = preg_split('/\s*[,;\t|]\s*|\s+/', trim($line), 2) ?: [];
            self::put($lines, (string) ($parts[0] ?? ''), (string) ($parts[1] ?? '1'));
        }

        return $lines;
    }

    /**
     * @param string $contents The CSV file's contents
     * @return array<string, float> SKU => quantity
     */
    public static function csv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $delimiter = ',';
        foreach ([';', "\t"] as $candidate) {
            if (substr_count($contents, $candidate) > substr_count($contents, $delimiter)) {
                $delimiter = $candidate;
            }
        }
        $lines = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $cells = str_getcsv($line, $delimiter, '"', '\\');
            self::put($lines, (string) ($cells[0] ?? ''), (string) ($cells[1] ?? '1'));
        }

        return $lines;
    }

    /**
     * Several lists as one, quantities of the same SKU added up.
     *
     * @param array<string, float> ...$lists
     * @return array<string, float>
     */
    public static function merge(array ...$lists): array
    {
        $lines = [];
        foreach ($lists as $list) {
            foreach ($list as $sku => $qty) {
                $lines[$sku] = ($lines[$sku] ?? 0) + $qty;
            }
        }

        return array_slice($lines, 0, self::MAX_LINES, true);
    }

    /**
     * @param array<string, float> $lines
     */
    private static function put(array &$lines, string $sku, string $qty): void
    {
        $sku = trim($sku);
        if ($sku === '' || strcasecmp($sku, 'sku') === 0 || str_starts_with($sku, '#')) {
            return;
        }
        $qty = (float) str_replace(',', '.', trim($qty));
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $lines[$sku] = ($lines[$sku] ?? 0) + $qty;
    }
}
