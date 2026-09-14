<?php

namespace App\Http\Requests\Cart;

use App\Models\Cart;
use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY_PER_ITEM],
            'cart_token' => ['sometimes', 'nullable', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'Seçilen ürün bulunamadı.',
            'quantity.max' => 'Bir üründen sepete en fazla :max adet eklenebilir.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'ürün',
            'quantity' => 'adet',
            'cart_token' => 'sepet kimliği',
        ];
    }
}
