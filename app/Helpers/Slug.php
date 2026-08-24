<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

readonly class Slug
{
    public const string fallback = 'untitled';

    /** @var list<string> */
    public const array reserved = ['new', 'p', 'settings', 'connections', 'members', 'invitations'];

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    public static function unique(string $model, string $column, string $name, array $scope = []): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? self::fallback : $base;
        $candidate = $base;
        $suffix = 1;

        while (self::taken($model, $column, $candidate, $scope)) {
            $candidate = $base.'-'.++$suffix;
        }

        return $candidate;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    private static function taken(string $model, string $column, string $candidate, array $scope): bool
    {
        if (in_array($candidate, self::reserved, true)) {
            return true;
        }

        $Builder = $model::query()->where($column, $candidate);

        foreach ($scope as $key => $value) {
            $Builder->where($key, $value);
        }

        return $Builder->exists();
    }
}
