<?php

namespace App\Http\Requests\Category;

use App\Models\Category;
use App\Rules\NotACategoryDescendant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255', 'lowercase', 'alpha_dash:ascii',
                Rule::unique('categories', 'slug')->ignore($category->getKey()),
            ],
            'parent_id' => [
                'sometimes', 'nullable', 'integer', 'exists:categories,id',
                new NotACategoryDescendant($category),
            ],
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
            'parent_id.exists' => 'Seçilen üst kategori bulunamadı.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'kategori adı',
            'slug' => 'kısa ad',
            'parent_id' => 'üst kategori',
        ];
    }
}
