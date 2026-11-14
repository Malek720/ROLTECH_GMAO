<?php

namespace App\Service;

/**
 * Shared CSV cell escaping (AGENTS.md rule 34).
 *
 * Spreadsheet programs treat a cell starting with =, +, - or @ as a formula.
 * {@see self::escape()} prefixes such a cell with a single quote; {@see self::unescape()}
 * removes that quote again, and only when it precedes one of those formula characters,
 * so a legitimate value starting with an apostrophe is preserved.
 */
final class CsvCellEscaper
{
    private const FORMULA_LEADING = ['=', '+', '-', '@'];

    public static function escape(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], self::FORMULA_LEADING, true) ? "'".$value : $value;
    }

    public static function unescape(string $value): string
    {
        return strlen($value) > 1 && $value[0] === "'" && in_array($value[1], self::FORMULA_LEADING, true)
            ? substr($value, 1)
            : $value;
    }
}
