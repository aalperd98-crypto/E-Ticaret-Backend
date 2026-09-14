<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cart_id', 'product_id', 'quantity'])]
class CartItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Satır tutarı, ürünün o anki fiyatı üzerinden hesaplanır;
     * fiyat sepette dondurulmaz, sipariş adımında sabitlenir.
     */
    public function lineTotal(): string
    {
        return number_format((float) $this->product->price * $this->quantity, 2, '.', '');
    }
}
