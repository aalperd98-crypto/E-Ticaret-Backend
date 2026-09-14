<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'token'])]
class Cart extends Model
{
    use HasFactory;

    public const MAX_QUANTITY_PER_ITEM = 99;

    protected static function booted(): void
    {
        static::creating(function (self $cart): void {
            if (blank($cart->token)) {
                $cart->token = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /**
     * Ziyaretçinin elindeki sepet kimliğiyle sepeti bulur.
     *
     * Bir kullanıcıya bağlanmış sepetler token ile erişilemez; aksi hâlde
     * eski token'ı elinde tutan bir ziyaretçi o kullanıcının sepetini görebilirdi.
     */
    public static function findByToken(?string $token): ?self
    {
        if (blank($token)) {
            return null;
        }

        return static::query()->whereNull('user_id')->where('token', $token)->first();
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function subtotal(): string
    {
        $total = $this->items->reduce(
            fn (float $carry, CartItem $item) => $carry + (float) $item->lineTotal(),
            0.0,
        );

        return number_format($total, 2, '.', '');
    }
}
