<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'lowercase', 'alpha_dash:ascii', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
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
