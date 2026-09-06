<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Elektronik' => [
                'Telefon' => ['Akıllı Telefon', 'Tuşlu Telefon'],
                'Bilgisayar' => ['Dizüstü', 'Masaüstü'],
            ],
            'Giyim' => [
                'Kadın Giyim' => ['Elbise', 'Tişört'],
                'Erkek Giyim' => ['Gömlek', 'Pantolon'],
            ],
            'Ev & Yaşam' => [],
        ];

        foreach ($tree as $rootTitle => $branches) {
            $root = $this->category($rootTitle);

            foreach ($branches as $branchTitle => $leaves) {
                $branch = $this->category($branchTitle, $root);

                foreach ($leaves as $leafTitle) {
                    $leaf = $this->category($leafTitle, $branch);

                    Product::factory()->count(3)->create(['category_id' => $leaf->getKey()]);
                }
            }
        }
    }

    private function category(string $title, ?Category $parent = null): Category
    {
        return Category::create([
            'parent_id' => $parent?->getKey(),
            'title' => $title,
            'slug' => Str::slug($title, '-', 'tr'),
        ]);
    }
}
