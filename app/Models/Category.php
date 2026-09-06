<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'title', 'slug'])]
#[RouteKey('slug')]
class Category extends Model
{
    use HasFactory, HasSlug;

    public function slugSource(): string
    {
        return 'title';
    }

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return Collection<int, static>
     */
    public static function tree(): Collection
    {
        $all = static::query()->orderBy('title')->get();
        $byParent = $all->groupBy('parent_id');

        $attach = function (Collection $nodes) use (&$attach, $byParent): Collection {
            foreach ($nodes as $node) {
                $children = $byParent->get($node->getKey(), new Collection);
                $node->setRelation('children', $attach($children));
            }

            return $nodes;
        };

        return $attach($all->whereNull('parent_id')->values());
    }

    /**
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $byParent = [];

        foreach (static::query()->get(['id', 'parent_id']) as $row) {
            $byParent[$row->parent_id][] = $row->id;
        }

        $ids = [];
        $seen = [$this->getKey() => true];
        $stack = [$this->getKey()];

        while ($stack !== []) {
            foreach ($byParent[array_pop($stack)] ?? [] as $childId) {
                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        return [$this->getKey(), ...$this->descendantIds()];
    }
}
