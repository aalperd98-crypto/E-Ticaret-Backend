<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'title', 'slug', 'description', 'price', 'stock', 'is_active', 'thumbnail_path'])]
#[RouteKey('slug')]
class Product extends Model
{
    use HasFactory, HasSlug;

    public const MAX_GALLERY_IMAGES = 8;

    public function slugSource(): string
    {
        return 'title';
    }

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }
}
