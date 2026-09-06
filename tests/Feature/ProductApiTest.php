<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    private function actingAsAdmin(): self
    {
        Sanctum::actingAs($this->adminUser());

        return $this;
    }

    private function fakeImage(string $name = 'gorsel.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 320, 320);
    }

    public function test_anonim_istek_401_alir(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
        $this->postJson('/api/products', [])->assertStatus(401);
    }

    public function test_admin_olmayan_kullanici_403_alir(): void
    {
        Sanctum::actingAs($this->normalUser());

        $this->getJson('/api/products')->assertStatus(403);
    }

    public function test_liste_sayfalaniyor_ve_per_page_sinirlaniyor(): void
    {
        Product::factory()->count(3)->create();

        $this->actingAsAdmin()->getJson('/api/products?per_page=200')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_kategori_filtresi_alt_kategorileri_de_kapsiyor(): void
    {
        $root = Category::factory()->create(['title' => 'Elektronik', 'slug' => 'elektronik']);
        $mid = Category::factory()->childOf($root)->create();
        $leaf = Category::factory()->childOf($mid)->create();

        Product::factory()->create(['category_id' => $leaf->id]);
        Product::factory()->create();

        $this->actingAsAdmin()->getJson('/api/products?category=elektronik')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_descendants_sifir_ile_sadece_kategorinin_kendisi(): void
    {
        $root = Category::factory()->create(['slug' => 'elektronik']);
        $child = Category::factory()->childOf($root)->create();

        Product::factory()->create(['category_id' => $child->id]);

        $this->actingAsAdmin()->getJson('/api/products?category=elektronik&descendants=0')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_arama_kategori_filtresinden_tasmiyor(): void
    {
        $category = Category::factory()->create(['slug' => 'elektronik']);

        Product::factory()->create(['category_id' => $category->id, 'title' => 'Telefon Kılıfı', 'slug' => 'telefon-kilifi']);
        Product::factory()->create(['title' => 'Telefon Standı', 'slug' => 'telefon-standi']);

        $this->actingAsAdmin()->getJson('/api/products?category=elektronik&search=Telefon')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Telefon Kılıfı');
    }

    public function test_is_active_filtresi_calisiyor(): void
    {
        Product::factory()->create();
        Product::factory()->inactive()->create();

        $this->actingAsAdmin();

        $this->getJson('/api/products')->assertJsonCount(2, 'data');
        $this->getJson('/api/products?is_active=1')->assertJsonCount(1, 'data');
        $this->getJson('/api/products?is_active=0')->assertJsonCount(1, 'data');
    }

    public function test_liste_kategoriyi_eager_load_ediyor(): void
    {
        $this->actingAsAdmin();

        Product::factory()->count(11)->create();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/products')->assertOk();
        $sorgular = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $kategoriSorgulari = array_filter($sorgular, fn ($q) => str_contains($q, '"categories"'));

        $this->assertCount(1, $kategoriSorgulari, 'Kategoriler tek whereIn ile yüklenmeli: N+1 var.');
        $this->assertLessThanOrEqual(4, count($sorgular), 'Beklenenden fazla sorgu çalışıyor.');
    }

    public function test_admin_urun_olusturabiliyor(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()->postJson('/api/products', [
            'category_id' => $category->id,
            'title' => 'Kırmızı Tişört',
            'price' => 249.90,
            'stock' => 10,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'kirmizi-tisort')
            ->assertJsonPath('data.price', '249.90')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.stock', 10);
    }

    public function test_kapak_ve_galeri_gorselleri_yukleniyor(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $response = $this->actingAsAdmin()->post('/api/products', [
            'category_id' => $category->id,
            'title' => 'Kırmızı Tişört',
            'price' => '249.90',
            'stock' => 10,
            'thumbnail' => $this->fakeImage('kapak.jpg'),
            'gallery' => [$this->fakeImage('g1.jpg'), $this->fakeImage('g2.jpg')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.is_active', true);

        $product = Product::firstOrFail();

        $this->assertNotNull($product->thumbnail_path);
        Storage::disk('public')->assertExists($product->thumbnail_path);
        $this->assertCount(2, $product->images);

        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }

        $this->assertStringContainsString('/storage/', $response->json('data.thumbnail_url'));
    }

    public function test_galeri_sekiz_gorseli_asamaz(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $gallery = [];

        for ($i = 0; $i < 9; $i++) {
            $gallery[] = $this->fakeImage("g{$i}.jpg");
        }

        $this->actingAsAdmin()->post('/api/products', [
            'category_id' => $category->id,
            'title' => 'Çok Görselli',
            'price' => '10.00',
            'stock' => 1,
            'gallery' => $gallery,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('gallery');
    }

    public function test_method_spoofing_ile_kapak_gorseli_degistiriliyor(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create([
            'thumbnail_path' => $eski = 'products/eski.jpg',
        ]);
        Storage::disk('public')->put($eski, 'icerik');

        $this->actingAsAdmin()->post("/api/products/{$product->slug}", [
            '_method' => 'PUT',
            'thumbnail' => $this->fakeImage('yeni.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $product->refresh();

        $this->assertNotSame($eski, $product->thumbnail_path);
        Storage::disk('public')->assertExists($product->thumbnail_path);
        Storage::disk('public')->assertMissing($eski);
    }

    public function test_remove_thumbnail_kapak_gorselini_temizliyor(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create(['thumbnail_path' => $path = 'products/kapak.jpg']);
        Storage::disk('public')->put($path, 'icerik');

        $this->actingAsAdmin()
            ->patchJson("/api/products/{$product->slug}", ['remove_thumbnail' => true])
            ->assertOk()
            ->assertJsonPath('data.thumbnail_url', null);

        $this->assertNull($product->fresh()->thumbnail_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_galeriye_gorsel_eklenebiliyor_ve_silinebiliyor(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $mevcut = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $path = 'products/gallery/mevcut.jpg',
        ]);
        Storage::disk('public')->put($path, 'icerik');

        $this->actingAsAdmin()->post("/api/products/{$product->slug}", [
            '_method' => 'PUT',
            'gallery' => [$this->fakeImage('yeni.jpg')],
            'remove_image_ids' => [$mevcut->id],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(1, 'data.images');

        $this->assertDatabaseMissing('product_images', ['id' => $mevcut->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_baska_urunun_gorseli_silinemez(): void
    {
        $product = Product::factory()->create();
        $digerinGorseli = ProductImage::factory()->create();

        $this->actingAsAdmin()
            ->patchJson("/api/products/{$product->slug}", ['remove_image_ids' => [$digerinGorseli->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remove_image_ids.0');
    }

    public function test_mevcut_galeri_ile_birlikte_sekiz_siniri_kontrol_ediliyor(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        ProductImage::factory()->count(7)->create(['product_id' => $product->id]);

        $this->actingAsAdmin()->post("/api/products/{$product->slug}", [
            '_method' => 'PUT',
            'gallery' => [$this->fakeImage('a.jpg'), $this->fakeImage('b.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('gallery');
    }

    public function test_urun_silinince_gorselleri_de_siliniyor(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create(['thumbnail_path' => $kapak = 'products/kapak.jpg']);
        $image = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $galeri = 'products/gallery/g.jpg',
        ]);

        Storage::disk('public')->put($kapak, 'icerik');
        Storage::disk('public')->put($galeri, 'icerik');

        $this->actingAsAdmin()->deleteJson("/api/products/{$product->slug}")->assertStatus(204);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($kapak);
        Storage::disk('public')->assertMissing($galeri);
    }

    public function test_gecersiz_girdiler_reddediliyor(): void
    {
        $this->actingAsAdmin()->postJson('/api/products', [
            'title' => 'Test',
            'price' => -5,
            'stock' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'price', 'stock']);
    }

    public function test_fiyat_iki_ondalikdan_fazla_olamaz(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()->postJson('/api/products', [
            'category_id' => $category->id,
            'title' => 'Test',
            'price' => 19.999,
            'stock' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_gorsel_olmayan_dosya_reddediliyor(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()->post('/api/products', [
            'category_id' => $category->id,
            'title' => 'Test',
            'price' => '10.00',
            'stock' => 1,
            'thumbnail' => UploadedFile::fake()->create('belge.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('thumbnail');
    }
}
