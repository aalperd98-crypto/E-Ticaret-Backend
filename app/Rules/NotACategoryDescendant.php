<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotACategoryDescendant implements ValidationRule
{
    public function __construct(private readonly Category $category) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ((int) $value === (int) $this->category->getKey()) {
            $fail('Bir kategori kendisinin üst kategorisi olamaz.');

            return;
        }

        if (in_array((int) $value, $this->category->descendantIds(), true)) {
            $fail('Bir kategori, kendi alt kategorilerinden birinin altına taşınamaz.');
        }
    }
}
