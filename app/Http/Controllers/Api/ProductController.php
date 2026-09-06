<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'price_asc' => ['price', 'asc'],
        'price_desc' => ['price', 'desc'],
        'title' => ['title', 'asc'],
    ];

    public function index(Request $request)
    {
        $query = Product::query()->with('category');

        $category = $request->filled('category_id')
            ? Category::find($request->integer('category_id'))
            : ($request->filled('category')
                ? Category::where('slug', $request->string('category'))->first()
                : null);

        if ($category) {
            $query->whereIn('category_id', $request->boolean('descendants', true)
                ? $category->selfAndDescendantIds()
                : [$category->getKey()]);
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';

            $query->where(fn ($q) => $q
                ->where('title', 'like', $term)
                ->orWhere('description', 'like', $term));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        [$column, $direction] = self::SORTS[$request->input('sort')] ?? self::SORTS['newest'];

        $products = $query->orderBy($column, $direction)->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load(['category', 'images']));
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        unset($data['thumbnail'], $data['gallery']);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')->store('products', 'public');
        }

        $galleryPaths = [];

        foreach ($request->file('gallery', []) as $file) {
            $galleryPaths[] = $file->store('products/gallery', 'public');
        }

        $product = DB::transaction(function () use ($data, $galleryPaths) {
            $product = Product::create($data);

            foreach ($galleryPaths as $index => $path) {
                $product->images()->create(['path' => $path, 'sort_order' => $index]);
            }

            return $product;
        });

        return new ProductResource($product->refresh()->load(['category', 'images']));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();
        unset($data['thumbnail'], $data['gallery'], $data['remove_thumbnail'], $data['remove_image_ids']);

        $filesToDelete = [];

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')->store('products', 'public');

            if ($product->thumbnail_path) {
                $filesToDelete[] = $product->thumbnail_path;
            }
        } elseif ($request->boolean('remove_thumbnail') && $product->thumbnail_path) {
            $data['thumbnail_path'] = null;
            $filesToDelete[] = $product->thumbnail_path;
        }

        $newGalleryPaths = [];

        foreach ($request->file('gallery', []) as $file) {
            $newGalleryPaths[] = $file->store('products/gallery', 'public');
        }

        $removeIds = array_filter((array) $request->input('remove_image_ids', []));

        DB::transaction(function () use ($product, $data, $newGalleryPaths, $removeIds, &$filesToDelete) {
            if ($removeIds !== []) {
                $removed = $product->images()->whereIn('id', $removeIds)->get();

                foreach ($removed as $image) {
                    $filesToDelete[] = $image->path;
                }

                $product->images()->whereIn('id', $removed->modelKeys())->delete();
            }

            $product->update($data);

            $nextOrder = (int) $product->images()->max('sort_order') + 1;

            foreach ($newGalleryPaths as $path) {
                $product->images()->create(['path' => $path, 'sort_order' => $nextOrder++]);
            }
        });

        Storage::disk('public')->delete($filesToDelete);

        return new ProductResource($product->fresh()->load(['category', 'images']));
    }

    public function destroy(Product $product)
    {
        $paths = $product->images()->pluck('path')->all();

        if ($product->thumbnail_path) {
            $paths[] = $product->thumbnail_path;
        }

        $product->delete();

        Storage::disk('public')->delete($paths);

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
