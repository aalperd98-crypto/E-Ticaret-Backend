<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'lowercase', 'alpha_dash:ascii', 'unique:products,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:'.Product::MAX_GALLERY_IMAGES],
            'gallery.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Bu kısa ad zaten kullanılıyor.',
            'slug.alpha_dash' => 'Kısa ad yalnızca İngilizce harf, rakam, tire ve alt çizgi içerebilir.',
            'slug.lowercase' => 'Kısa ad tamamen küçük harf olmalıdır.',
            'category_id.exists' => 'Seçilen kategori bulunamadı.',
            'price.decimal' => 'Fiyat en fazla 2 ondalık basamak içerebilir.',
            'thumbnail.image' => 'Kapak görseli bir resim dosyası olmalıdır.',
            'thumbnail.max' => 'Kapak görseli en fazla 2 MB olabilir.',
            'gallery.max' => 'Galeride en fazla :max görsel olabilir.',
            'gallery.*.image' => 'Galerideki her dosya bir resim olmalıdır.',
            'gallery.*.max' => 'Galerideki her görsel en fazla 2 MB olabilir.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'kategori',
            'title' => 'ürün adı',
            'slug' => 'kısa ad',
            'description' => 'açıklama',
            'price' => 'fiyat',
            'stock' => 'stok',
            'is_active' => 'yayın durumu',
            'thumbnail' => 'kapak görseli',
            'gallery' => 'galeri',
        ];
    }
}
