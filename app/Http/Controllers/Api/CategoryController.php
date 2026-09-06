<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->filled('parent_id'), fn ($query) => $query->where('parent_id', $request->integer('parent_id')))
            ->when($request->boolean('roots'), fn ($query) => $query->whereNull('parent_id'))
            ->orderBy('title')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    public function tree()
    {
        return CategoryResource::collection(Category::tree());
    }

    public function show(Category $category)
    {
        $category->loadCount('products')->load(['parent', 'children']);

        return new CategoryResource($category);
    }

    public function store(StoreCategoryRequest $request)
    {
        return new CategoryResource(Category::create($request->validated()));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return new CategoryResource($category->fresh());
    }

    public function destroy(Category $category)
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Alt kategorileri veya ürünleri olan bir kategori silinemez.'],
            ]);
        }

        $category->delete();

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
