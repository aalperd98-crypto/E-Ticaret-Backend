<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class JsonBodyValidationTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_bozuk_json_govdesi_400_donuyor(): void
    {
        Sanctum::actingAs($this->adminUser());
        $category = Category::factory()->create();

        $response = $this->call(
            'PATCH',
            "/api/categories/{$category->slug}",
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: '{bozuk json}',
        );

        $response->assertStatus(400)
            ->assertJsonStructure(['message']);
    }

    public function test_bom_ile_baslayan_govde_400_donuyor(): void
    {
        Sanctum::actingAs($this->adminUser());
        $category = Category::factory()->create();

        $response = $this->call(
            'PATCH',
            "/api/categories/{$category->slug}",
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: "\xEF\xBB\xBF".json_encode(['title' => 'Yeni Başlık']),
        );

        $response->assertStatus(400);
        $this->assertNotSame('Yeni Başlık', $category->fresh()->title);
    }

    public function test_gecerli_json_govdesi_calisiyor(): void
    {
        Sanctum::actingAs($this->adminUser());
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->slug}", ['title' => 'Yeni Başlık'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Yeni Başlık');
    }

    public function test_bos_govde_hala_kabul_ediliyor(): void
    {
        Sanctum::actingAs($this->adminUser());
        $category = Category::factory()->create();

        $this->call(
            'PATCH',
            "/api/categories/{$category->slug}",
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: '',
        )->assertOk();
    }
}
