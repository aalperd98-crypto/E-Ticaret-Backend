<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartItem */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->product->price,
            'line_total' => $this->lineTotal(),
            'stock' => $this->product->stock,
            'is_available' => $this->product->is_active && $this->product->stock >= $this->quantity,
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
