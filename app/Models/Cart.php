<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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

    /**
     * Ziyaretçi sepetini kullanıcıya devreder ve kullanıcının güncel sepetini döner.
     *
     * Devredilecek bir şey yoksa (token yok ve kullanıcının sepeti de yoksa)
     * boş yere satır oluşturmamak için null döner.
     */
    public static function claim(?string $token, User $user): ?self
    {
        return DB::transaction(function () use ($token, $user): ?self {
            $guestCart = static::findByToken($token);
            $userCart = static::query()->where('user_id', $user->getKey())->first();

            if (! $guestCart) {
                return $userCart;
            }

            if (! $userCart) {
                $guestCart->update(['user_id' => $user->getKey()]);

                return $guestCart;
            }

            $userCart->mergeFrom($guestCart);

            return $userCart;
        });
    }

    /**
     * Verilen sepetin ürünlerini bu sepete taşır ve kaynağı siler.
     *
     * Aynı üründen iki sepette de varsa adetler toplanır. Stok kontrolü
     * burada yapılmaz; ekleme/güncelleme uçları ve sipariş adımı bunu zaten doğrular.
     */
    public function mergeFrom(self $source): void
    {
        DB::transaction(function () use ($source): void {
            $current = $this->items()->pluck('quantity', 'product_id');

            foreach ($source->items()->get() as $item) {
                $this->items()->updateOrCreate(
                    ['product_id' => $item->product_id],
                    ['quantity' => min(
                        ($current[$item->product_id] ?? 0) + $item->quantity,
                        self::MAX_QUANTITY_PER_ITEM,
                    )],
                );
            }

            $source->delete();
        });
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
