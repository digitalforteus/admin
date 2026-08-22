<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

readonly class Slug
{
    public const string fallback = 'untitled';

    /** @param  class-string<Model>  $model */
    public static function unique(string $model, string $column, string $name): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? self::fallback : $base;
        $candidate = $base;
        $suffix = 1;

        while ($model::query()->where($column, $candidate)->exists()) {
            $candidate = $base.'-'.++$suffix;
        }

        return $candidate;
    }
}
