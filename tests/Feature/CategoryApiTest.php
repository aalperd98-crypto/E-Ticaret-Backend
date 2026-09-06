<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    private function actingAsAdmin(): self
    {
        Sanctum::actingAs($this->adminUser());

        return $this;
    }

    public function test_anonim_istek_401_alir(): void
    {
        $this->getJson('/api/categories')->assertStatus(401);
        $this->postJson('/api/categories', ['title' => 'Test'])->assertStatus(401);
    }

    public function test_admin_olmayan_kullanici_403_alir(): void
    {
        Sanctum::actingAs($this->normalUser());

        $this->getJson('/api/categories')->assertStatus(403);
        $this->postJson('/api/categories', ['title' => 'Test'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Bu işlem için yetkiniz yok.']);
    }

    public function test_admin_kategorileri_listeleyebilir(): void
    {
        Category::factory()->count(3)->create();

        $this->actingAsAdmin()->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_agac_endpointi_ic_ice_donuyor(): void
    {
        $root = Category::factory()->create(['title' => 'Elektronik', 'slug' => 'elektronik']);
        $mid = Category::factory()->childOf($root)->create(['title' => 'Telefon', 'slug' => 'telefon']);
        Category::factory()->childOf($mid)->create(['title' => 'Akıllı Telefon', 'slug' => 'akilli-telefon']);

        $this->actingAsAdmin()->getJson('/api/categories/tree')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Elektronik')
            ->assertJsonPath('data.0.children.0.title', 'Telefon')
            ->assertJsonPath('data.0.children.0.children.0.title', 'Akıllı Telefon');
    }

    public function test_agac_endpointi_slug_olarak_yorumlanmiyor(): void
    {
        $this->actingAsAdmin()->getJson('/api/categories/tree')->assertOk();
    }

    public function test_bilinmeyen_slug_404_donuyor(): void
    {
        $this->actingAsAdmin()->getJson('/api/categories/olmayan-kategori')->assertStatus(404);
    }

    public function test_slug_turkce_baslikdan_uretiliyor(): void
    {
        $this->actingAsAdmin()->postJson('/api/categories', ['title' => 'Kadın Giyim'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'kadin-giyim');
    }

    public function test_ayni_baslik_ikinci_kez_eklenince_slug_ekleniyor(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/categories', ['title' => 'Kadın Giyim'])->assertStatus(201);

        $this->postJson('/api/categories', ['title' => 'Kadın Giyim'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'kadin-giyim-2');
    }

    public function test_acikca_verilen_slug_korunuyor(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/categories', ['title' => 'Kadın Giyim', 'slug' => 'ozel-slug'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'ozel-slug');
    }

    public function test_turkce_karakterli_slug_reddediliyor(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/categories', ['title' => 'Test', 'slug' => 'ürünler'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_guncellemede_slug_benzersizligi_kendini_yok_sayiyor(): void
    {
        $category = Category::factory()->create(['title' => 'Elektronik', 'slug' => 'elektronik']);

        $this->actingAsAdmin()
            ->patchJson("/api/categories/{$category->slug}", ['slug' => 'elektronik'])
            ->assertOk();
    }

    public function test_kategori_kendi_ust_kategorisi_olamaz(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()
            ->patchJson("/api/categories/{$category->slug}", ['parent_id' => $category->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_kategori_kendi_alt_kategorisinin_altina_tasinamaz(): void
    {
        $a = Category::factory()->create(['title' => 'A', 'slug' => 'a']);
        $b = Category::factory()->childOf($a)->create(['title' => 'B', 'slug' => 'b']);
        $c = Category::factory()->childOf($b)->create(['title' => 'C', 'slug' => 'c']);

        $this->actingAsAdmin()
            ->patchJson("/api/categories/{$a->slug}", ['parent_id' => $c->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_gecerli_bir_ust_kategoriye_tasinabiliyor(): void
    {
        $a = Category::factory()->create(['title' => 'A', 'slug' => 'a']);
        $b = Category::factory()->create(['title' => 'B', 'slug' => 'b']);

        $this->actingAsAdmin()
            ->patchJson("/api/categories/{$a->slug}", ['parent_id' => $b->id])
            ->assertOk()
            ->assertJsonPath('data.parent_id', $b->id);
    }

    public function test_kok_kategoriye_tasinabiliyor(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        $this->actingAsAdmin()
            ->patchJson("/api/categories/{$child->slug}", ['parent_id' => null])
            ->assertOk()
            ->assertJsonPath('data.parent_id', null);
    }

    public function test_alt_kategorisi_olan_kategori_silinemiyor(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $this->actingAsAdmin()
            ->deleteJson("/api/categories/{$parent->slug}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_urunu_olan_kategori_silinemiyor(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAsAdmin()
            ->deleteJson("/api/categories/{$category->slug}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    public function test_bos_kategori_silinebiliyor(): void
    {
        $category = Category::factory()->create();

        $this->actingAsAdmin()
            ->deleteJson("/api/categories/{$category->slug}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
