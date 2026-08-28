<?php

namespace App\View\DataModels;

use Illuminate\Support\Carbon;
use ZeroToProd\DbModel\ColumnType;

trait FormatsTableCell
{
    private function formattedCell(mixed $value, string $columnType): string
    {
        if (! is_string($value) || $value === '') {
            return '—';
        }

        return $columnType === ColumnType::timestamp->value
            ? Carbon::parse($value)->toFormattedDateString()
            : $value;
    }
}
