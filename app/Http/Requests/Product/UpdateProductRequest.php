<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255', 'lowercase', 'alpha_dash:ascii',
                Rule::unique('products', 'slug')->ignore($product->getKey()),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_thumbnail' => ['sometimes', 'boolean'],

            'gallery' => ['nullable', 'array', $this->galleryCapacityRule($product)],
            'gallery.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],

            'remove_image_ids' => ['sometimes', 'array'],
            'remove_image_ids.*' => [
                'integer',
                Rule::exists('product_images', 'id')->where('product_id', $product->getKey()),
            ],
        ];
    }

    private function galleryCapacityRule(Product $product): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($product): void {
            $removing = count(array_filter((array) $this->input('remove_image_ids', [])));
            $total = $product->images()->count() - $removing + count((array) $value);

            if ($total > Product::MAX_GALLERY_IMAGES) {
                $fail('Bir üründe en fazla '.Product::MAX_GALLERY_IMAGES.' galeri görseli olabilir.');
            }
        };
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
            'gallery.*.image' => 'Galerideki her dosya bir resim olmalıdır.',
            'gallery.*.max' => 'Galerideki her görsel en fazla 2 MB olabilir.',
            'remove_image_ids.*.exists' => 'Silinmek istenen görsel bu ürüne ait değil.',
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
            'remove_image_ids' => 'silinecek görseller',
        ];
    }
}
