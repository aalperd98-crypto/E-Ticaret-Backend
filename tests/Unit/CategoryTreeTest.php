<?php

namespace Tests\Unit;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_descendant_ids_tum_alt_agaci_veriyor(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->childOf($a)->create();
        $c = Category::factory()->childOf($b)->create();
        $d = Category::factory()->childOf($a)->create();

        $ids = $a->descendantIds();

        sort($ids);
        $beklenen = [$b->id, $c->id, $d->id];
        sort($beklenen);

        $this->assertSame($beklenen, $ids);
        $this->assertNotContains($a->id, $ids, 'Kendisi listede olmamalı.');
    }

    public function test_yaprak_kategoride_bos_donuyor(): void
    {
        $this->assertSame([], Category::factory()->create()->descendantIds());
    }

    public function test_veride_dongu_varsa_askida_kalmiyor(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->childOf($a)->create();

        DB::table('categories')->where('id', $a->id)->update(['parent_id' => $b->id]);

        $ids = $a->fresh()->descendantIds();

        $this->assertContains($b->id, $ids);
    }

    public function test_agac_tek_sorguda_kuruluyor(): void
    {
        $root = Category::factory()->create();
        $mid = Category::factory()->childOf($root)->create();
        Category::factory()->childOf($mid)->create();

        DB::enableQueryLog();
        $tree = Category::tree();
        $sorgular = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $sorgular);
        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree->first()->children);
        $this->assertCount(1, $tree->first()->children->first()->children);
    }
}
