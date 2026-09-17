<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ReviewedImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewedImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAndSignIn();
    }

    private function brand(string $name = 'Test Beer', int $size = 330): Product
    {
        return Product::create(['outlet_id' => 1, 'name' => $name, 'spirit_type' => 'Beer', 'bottle_size_ml' => $size]);
    }

    private function indent(array $brands, string $number = 'TEST-001'): array
    {
        $lines = [];
        foreach ($brands as $index => $brand) {
            $lines[] = ['number' => $index + 1, 'code' => 'CODE'.$brand->id, 'name' => $brand->name, 'size_ml' => (float) $brand->bottle_size_ml,
                'bottles' => 10, 'volume_ml' => 10 * $brand->bottle_size_ml, 'amount' => 1000];
        }
        $preview = ['indent_number' => $number, 'date' => '01-JUL-26', 'invoice_value' => count($lines) * 1000, 'net_value' => count($lines) * 1000, 'lines' => $lines];
        $id = app(ReviewedImportService::class)->store(1, 'excise', $preview, 'original.pdf');

        return [$id, DB::table('upload_rows')->where('document_id', $id)->pluck('id')->all(), $preview];
    }

    private function pos(string $name, array $figures = [], string $from = '2026-07-01', string $to = '2026-07-02'): array
    {
        $figures += ['sold' => 2, 'comp' => 1, 'nc' => 1, 'sold_amount' => 400, 'comp_amount' => 200, 'nc_amount' => 200];
        $line = ['name' => $name, 'category' => 'Beer', ...$figures, 'daily' => [$to => $figures]];
        $id = app(ReviewedImportService::class)->store(1, 'pos', ['from' => $from, 'to' => $to, 'granularity' => 'period', 'lines' => [$line]], 'sales.xlsx');

        return [$id, DB::table('upload_rows')->where('document_id', $id)->pluck('id')->all()];
    }

    private function map(string $id, int $row, Product $brand, array $extra = []): void
    {
        $this->postJson('/uploads/mapping', ['document_id' => $id, 'row_id' => $row, 'product_id' => $brand->id, 'kind' => 'bottle', 'bottles_per_sale' => 1, ...$extra])->assertOk();
    }

    private function apply(string $id, array $rows, string $source): array
    {
        $review = $this->postJson('/uploads/review', ['document_id' => $id, 'source' => $source, 'row_ids' => $rows])->assertOk()->json();
        $this->postJson('/uploads/'.$source.'/apply', ['document_id' => $id, 'row_ids' => $rows, 'review_key' => $review['review_key']])->assertOk();

        return $review;
    }

    public function test_partial_indent_cannot_double_count_on_reupload_or_reordered_selection(): void
    {
        $a = $this->brand();
        $b = $this->brand('Other Beer');
        [$id, $rows, $preview] = $this->indent([$a, $b]);
        $this->map($id, $rows[0], $a);
        $this->map($id, $rows[1], $b);
        $this->apply($id, [$rows[0]], 'excise');
        $sameId = app(ReviewedImportService::class)->store(1, 'excise', $preview, 'renamed.pdf');
        $this->assertSame($id, $sameId);
        $this->postJson('/uploads/review', ['document_id' => $id, 'source' => 'excise', 'row_ids' => array_reverse($rows)])->assertUnprocessable();
        $this->apply($id, [$rows[1]], 'excise');
        $this->assertEquals(3300, $a->movements()->sum('volume_ml'));
        $this->assertEquals(3300, $b->movements()->sum('volume_ml'));
        $this->postJson('/uploads/mapping', ['document_id' => $id, 'row_id' => $rows[0], 'product_id' => $b->id, 'kind' => 'bottle', 'bottles_per_sale' => 1, 'confirm_change' => true])->assertUnprocessable();
    }

    public function test_client_cannot_forge_stock_or_apply_unsaved_rows(): void
    {
        $brand = $this->brand();
        [$id, $rows] = $this->indent([$brand]);
        $this->postJson('/uploads/excise/apply', ['indent_date' => '2026-07-01', 'indent_number' => 'fake', 'lines' => [['product_id' => $brand->id, 'volume_ml' => 999999]]])->assertUnprocessable();
        $this->postJson('/uploads/review', ['document_id' => $id, 'source' => 'excise', 'row_ids' => $rows])->assertUnprocessable();
        $this->map($id, $rows[0], $brand);
        $this->postJson('/uploads/review', ['document_id' => $id, 'source' => 'excise', 'row_ids' => [$rows[0], $rows[0]]])->assertUnprocessable();
        $review = $this->postJson('/uploads/review', ['document_id' => $id, 'source' => 'excise', 'row_ids' => $rows])->assertOk()->json();
        $this->postJson('/uploads/excise/apply', ['document_id' => $id, 'row_ids' => $rows, 'review_key' => $review['review_key'], 'lines' => [['volume_ml' => 999999, 'amount' => 1]]])->assertOk();
        $this->assertEquals(3300, $brand->movements()->sum('volume_ml'));
        $this->assertEquals(1000, $brand->movements()->sum('value_change'));
    }

    public function test_all_pos_channels_deduct_and_keep_revenue_once(): void
    {
        $brand = $this->brand();
        [$indent, $indentRows] = $this->indent([$brand]);
        $this->map($indent, $indentRows[0], $brand);
        $this->apply($indent, $indentRows, 'excise');
        [$pos, $posRows] = $this->pos('Captain code');
        $this->map($pos, $posRows[0], $brand);
        $review = $this->apply($pos, $posRows, 'pos');
        $this->assertEquals(660, $review['summary'][0]['sold_ml']);
        $this->assertEquals(330, $review['summary'][0]['comp_ml']);
        $this->assertEquals(330, $review['summary'][0]['nc_ml']);
        $this->assertEquals(1980, $brand->movements()->sum('volume_ml'));
        $this->assertDatabaseHas('import_lines', ['product_id' => $brand->id, 'sale_type' => 'full_bottle', 'quantity' => 4, 'line_value' => 400]);
        $this->get('/uploads/pos')->assertOk()->assertDontSee('POS preview');
        $this->get('/uploads/history')->assertOk()->assertSee('brands', false);
    }

    public function test_bucket_measured_and_cocktail_rules_are_used_instead_of_names(): void
    {
        $brand = $this->brand('Gin', 750);
        [$id, $rows] = $this->indent([$brand]);
        $this->map($id, $rows[0], $brand);
        $this->apply($id, $rows, 'excise');
        [$bucket, $bucketRows] = $this->pos('A secret bucket', ['sold' => 1, 'comp' => 0, 'nc' => 0]);
        $this->map($bucket, $bucketRows[0], $brand, ['bottles_per_sale' => 6]);
        $this->assertEquals(-4500, $this->apply($bucket, $bucketRows, 'pos')['summary'][0]['change_ml']);
        [$peg, $pegRows] = $this->pos('Unknown peg', ['sold' => 2, 'comp' => 0, 'nc' => 0]);
        $this->map($peg, $pegRows[0], $brand, ['kind' => 'measured', 'serving_ml' => 45]);
        $this->assertEquals(-90, $this->apply($peg, $pegRows, 'pos')['summary'][0]['change_ml']);
        $recipe = DB::table('recipes')->insertGetId(['outlet_id' => 1, 'name' => 'Gin Drink', 'is_active' => true]);
        DB::table('recipe_ingredients')->insert(['recipe_id' => $recipe, 'product_id' => $brand->id, 'volume_ml' => 45]);
        [$cocktail, $cocktailRows] = $this->pos('Captain cocktail', ['sold' => 2, 'comp' => 0, 'nc' => 0]);
        $this->map($cocktail, $cocktailRows[0], $brand, ['kind' => 'recipe', 'recipe_id' => $recipe]);
        $this->assertEquals(-90, $this->apply($cocktail, $cocktailRows, 'pos')['summary'][0]['change_ml']);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $brand->id, 'sale_type' => 'cocktail', 'volume_ml' => -90]);
    }

    public function test_same_brand_can_have_multiple_pos_servings_but_different_excise_codes_cannot_collide(): void
    {
        $a = $this->brand();
        $b = $this->brand('Other');
        [$id, $rows] = $this->indent([$a, $b]);
        $this->map($id, $rows[0], $a);
        $this->postJson('/uploads/mapping', ['document_id' => $id, 'row_id' => $rows[1], 'kind' => 'bottle', 'bottles_per_sale' => 1, 'product_id' => $a->id])->assertUnprocessable();
        [$p1, $r1] = $this->pos('Beer 30ML');
        [$p2, $r2] = $this->pos('Beer 60ML');
        $this->map($p1, $r1[0], $a, ['kind' => 'measured', 'serving_ml' => 30]);
        $this->map($p2, $r2[0], $a, ['kind' => 'measured', 'serving_ml' => 60]);
        $this->assertNotSame(ReviewedImportService::sourceKey('pos', ['name' => 'Beer 30ML']), ReviewedImportService::sourceKey('pos', ['name' => 'Beer 60ML']));
    }

    public function test_mapping_correction_requires_confirmation_and_stale_reviews_fail(): void
    {
        $a = $this->brand();
        $b = $this->brand('Corrected');
        [$id, $rows] = $this->indent([$a]);
        $this->map($id, $rows[0], $a);
        $review = $this->postJson('/uploads/review', ['document_id' => $id, 'source' => 'excise', 'row_ids' => $rows])->assertOk()->json();
        $payload = ['document_id' => $id, 'row_id' => $rows[0], 'kind' => 'bottle', 'product_id' => $b->id, 'bottles_per_sale' => 1];
        $this->postJson('/uploads/mapping', $payload)->assertUnprocessable();
        $this->postJson('/uploads/mapping', $payload + ['confirm_change' => true])->assertOk();
        $this->postJson('/uploads/excise/apply', ['document_id' => $id, 'row_ids' => $rows, 'review_key' => $review['review_key']])->assertUnprocessable();
        $this->assertEquals(0, $a->movements()->count());
        $this->assertDatabaseCount('mapping_changes', 2);
    }

    public function test_mysql_json_key_order_does_not_turn_same_mapping_into_a_correction(): void
    {
        $brand = $this->brand();
        [$id, $rows] = $this->indent([$brand]);
        $this->map($id, $rows[0], $brand);
        $mapping = DB::table('source_mappings')->first();
        $rules = json_decode($mapping->rules, true);
        ksort($rules);
        DB::table('source_mappings')->where('id', $mapping->id)->update(['rules' => json_encode($rules)]);
        $this->map($id, $rows[0], $brand);
    }

    public function test_multi_ingredient_recipe_is_atomic_and_does_not_duplicate_revenue(): void
    {
        $gin = $this->brand('Gin', 750);
        $vermouth = $this->brand('Vermouth', 750);
        [$id, $rows] = $this->indent([$gin, $vermouth]);
        $this->map($id, $rows[0], $gin);
        $this->map($id, $rows[1], $vermouth);
        $this->apply($id, $rows, 'excise');
        $recipe = DB::table('recipes')->insertGetId(['outlet_id' => 1, 'name' => 'Martini', 'is_active' => true]);
        DB::table('recipe_ingredients')->insert([
            ['recipe_id' => $recipe, 'product_id' => $gin->id, 'volume_ml' => 60],
            ['recipe_id' => $recipe, 'product_id' => $vermouth->id, 'volume_ml' => 15],
        ]);
        [$pos, $posRows] = $this->pos('Martini');
        $this->map($pos, $posRows[0], $gin, ['kind' => 'recipe', 'recipe_id' => $recipe]);
        $review = $this->postJson('/uploads/review', ['document_id' => $pos, 'source' => 'pos', 'row_ids' => $posRows])->assertOk()->json();
        DB::table('recipe_ingredients')->where('recipe_id', $recipe)->where('product_id', $gin->id)->update(['volume_ml' => 75]);
        $this->postJson('/uploads/pos/apply', ['document_id' => $pos, 'row_ids' => $posRows, 'review_key' => $review['review_key']])->assertUnprocessable();
        $this->assertEquals(7500, $gin->movements()->sum('volume_ml'));
        $this->apply($pos, $posRows, 'pos');
        $this->assertEquals(7200, $gin->movements()->sum('volume_ml'));
        $this->assertEquals(7440, $vermouth->movements()->sum('volume_ml'));
        $this->assertEquals(400, DB::table('import_lines')->where('recipe_id', $recipe)->sum('line_value'));
        $this->assertSame(1, DB::table('import_lines')->where('recipe_id', $recipe)->count());
    }

    public function test_insufficient_stock_rejects_entire_batch_without_mutations(): void
    {
        $brand = $this->brand();
        [$id, $rows] = $this->pos('No stock');
        $this->map($id, $rows[0], $brand);
        $before = DB::table('imports')->count();
        $this->postJson('/uploads/review', ['document_id' => $id, 'source' => 'pos', 'row_ids' => $rows])->assertUnprocessable()->assertJsonValidationErrors('stock');
        $this->assertSame($before, DB::table('imports')->count());
        $this->assertEquals(0, $brand->movements()->count());
    }

    public function test_fractional_bottle_channels_cannot_hide_inside_a_whole_total(): void
    {
        $brand = $this->brand();
        [$id, $rows] = $this->pos('Half channels', ['sold' => 0.5, 'comp' => 0.5, 'nc' => 0]);
        $this->map($id, $rows[0], $brand);
        $this->postJson('/uploads/review', ['document_id' => $id, 'source' => 'pos', 'row_ids' => $rows])->assertUnprocessable()->assertJsonValidationErrors('stock');
    }

    public function test_overlapping_summary_and_customer_periods_are_blocked(): void
    {
        $brand = $this->brand();
        [$id, $rows] = $this->indent([$brand]);
        $this->map($id, $rows[0], $brand);
        $this->apply($id, $rows, 'excise');
        [$first, $r1] = $this->pos('Same item');
        $this->map($first, $r1[0], $brand);
        $this->apply($first, $r1, 'pos');
        [$second, $r2] = $this->pos('Same item', [], '2026-07-02', '2026-07-03');
        $this->postJson('/uploads/review', ['document_id' => $second, 'source' => 'pos', 'row_ids' => $r2])->assertUnprocessable()->assertJsonValidationErrors('stock');
    }

    public function test_no_stock_success_for_zero_rows_and_explicit_ignore_is_distinguished(): void
    {
        $brand = $this->brand();
        [$id, $rows] = $this->pos('Zero item', ['sold' => 0, 'comp' => 0, 'nc' => 0, 'sold_amount' => 0]);
        $this->map($id, $rows[0], $brand);
        $this->postJson('/uploads/review', ['document_id' => $id, 'source' => 'pos', 'row_ids' => $rows])->assertUnprocessable();
        [$ignore, $ignoreRows] = $this->pos('French fries');
        $this->map($ignore, $ignoreRows[0], $brand, ['kind' => 'ignore']);
        $result = $this->apply($ignore, $ignoreRows, 'pos');
        $this->assertSame(1, $result['ignored']);
        $this->assertSame([], $result['summary']);
    }
}
