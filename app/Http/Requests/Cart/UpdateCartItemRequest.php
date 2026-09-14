<?php

namespace App\Http\Requests\Cart;

use App\Models\Cart;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY_PER_ITEM],
            'cart_token' => ['sometimes', 'nullable', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.min' => 'Adet en az :min olmalıdır; ürünü çıkarmak için silme ucunu kullanın.',
            'quantity.max' => 'Bir üründen sepette en fazla :max adet bulunabilir.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity' => 'adet',
            'cart_token' => 'sepet kimliği',
        ];
    }
}
