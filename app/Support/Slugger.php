<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Slugger
{
    /**
     * Build a URL slug from a name, adding a numeric suffix until it is unused.
     *
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (self::taken($model, $slug, $ignoreId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private static function taken(string $model, string $slug, ?int $ignoreId): bool
    {
        $query = $model::query()->where('slug', $slug);

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withTrashed();
        }

        return $query->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
    }
}
