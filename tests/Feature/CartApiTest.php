<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes + ['price' => 100, 'stock' => 10]);
    }

    private function addAsGuest(Product $product, int $quantity = 1, ?string $token = null): TestResponse
    {
        return $this->withHeaders($token ? ['X-Cart-Token' => $token] : [])
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $quantity]);
    }

    public function test_ziyaretci_sepete_ekleyince_sepet_kimligi_doner(): void
    {
        $product = $this->product();

        $response = $this->addAsGuest($product, 2)
            ->assertStatus(201)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.total_quantity', 2)
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.user_id', null);

        $this->assertNotNull($response->json('data.token'));
    }

    public function test_ziyaretci_kimligiyle_sepetini_gorebilir(): void
    {
        $token = $this->addAsGuest($this->product())->json('data.token');

        $this->withHeader('X-Cart-Token', $token)->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.token', $token)
            ->assertJsonPath('data.items_count', 1);
    }

    public function test_kimlik_gondermeyen_ziyaretci_bos_sepet_gorur(): void
    {
        $this->addAsGuest($this->product());

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.token', null)
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.subtotal', '0.00');
    }

    public function test_baska_sepetin_satirina_erisilemez(): void
    {
        $itemId = $this->addAsGuest($this->product())->json('data.items.0.id');
        $otherToken = $this->addAsGuest($this->product())->json('data.token');

        $this->withHeader('X-Cart-Token', $otherToken)
            ->patchJson("/api/cart/items/{$itemId}", ['quantity' => 5])
            ->assertStatus(404);

        $this->patchJson("/api/cart/items/{$itemId}", ['quantity' => 5])
            ->assertStatus(404);
    }

    public function test_ayni_urun_tekrar_eklenince_adet_toplanir(): void
    {
        $product = $this->product();
        $token = $this->addAsGuest($product, 2)->json('data.token');

        $this->addAsGuest($product, 3, $token)
            ->assertStatus(201)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.total_quantity', 5)
            ->assertJsonPath('data.subtotal', '500.00');
    }

    public function test_adet_guncellenebilir(): void
    {
        $response = $this->addAsGuest($this->product(), 2);
        $token = $response->json('data.token');
        $itemId = $response->json('data.items.0.id');

        $this->withHeader('X-Cart-Token', $token)
            ->patchJson("/api/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.total_quantity', 4)
            ->assertJsonPath('data.subtotal', '400.00');
    }

    public function test_satir_silinebilir_ve_sepet_bosaltilabilir(): void
    {
        $response = $this->addAsGuest($this->product(), 2);
        $token = $response->json('data.token');
        $itemId = $response->json('data.items.0.id');

        $this->addAsGuest($this->product(), 1, $token);

        $this->withHeader('X-Cart-Token', $token)
            ->deleteJson("/api/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.items_count', 1);

        $this->withHeader('X-Cart-Token', $token)->deleteJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.items_count', 0);
    }

    public function test_stok_ustunde_adet_reddedilir(): void
    {
        $product = $this->product(['stock' => 3]);

        $this->addAsGuest($product, 4)
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        $token = $this->addAsGuest($product, 2)->json('data.token');

        $this->addAsGuest($product, 2, $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    public function test_pasif_urun_sepete_eklenemez(): void
    {
        $this->addAsGuest($this->product(['is_active' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_gecersiz_urun_reddedilir(): void
    {
        $this->postJson('/api/cart/items', ['product_id' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_kullanici_sepeti_oturuma_bagli_kalir(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertStatus(201);

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.total_quantity', 2);
    }
}
