<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cart */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'user_id' => $this->user_id,
            'items' => CartItemResource::collection($this->items),
            'items_count' => $this->items->count(),
            'total_quantity' => $this->totalQuantity(),
            'subtotal' => $this->subtotal(),
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Sepeti hiç olmayan (ya da ziyaretçi kimliği göndermeyen) istemcinin
     * de aynı gövdeyi alması için boş sepet temsili.
     *
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'id' => null,
            'token' => null,
            'user_id' => null,
            'items' => [],
            'items_count' => 0,
            'total_quantity' => 0,
            'subtotal' => '0.00',
            'updated_at' => null,
        ];
    }
}
