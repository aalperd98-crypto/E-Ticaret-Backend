<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            if (blank($model->slug)) {
                $model->slug = static::generateUniqueSlug(
                    (string) $model->{$model->slugSource()},
                    $model->getKey(),
                );
            }
        });
    }

    public static function generateUniqueSlug(string $source, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($source, '-', 'tr') ?: 'kayit';

        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    abstract public function slugSource(): string;
}
