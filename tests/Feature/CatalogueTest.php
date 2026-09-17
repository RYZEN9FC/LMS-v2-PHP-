<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ProductNameMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_stock_precision_and_distinct_bottle_sizes(): void
    {
        $this->seedAndSignIn();
        $data = ['name' => 'One brand', 'spirit_type' => 'Beer', 'bottle_size_ml' => 330, 'opening_date' => '2026-07-01', 'opening_bottles' => 0, 'opening_ml' => 0];
        $this->postJson('/brands', [...$data, 'opening_bottles' => -1])->assertUnprocessable();
        $this->postJson('/brands', [...$data, 'opening_ml' => -0.1])->assertUnprocessable();
        $this->postJson('/brands', [...$data, 'opening_ml' => 0.001])->assertUnprocessable();
        $this->postJson('/brands', [...$data, 'opening_ml' => 330])->assertUnprocessable();
        $this->postJson('/brands', [...$data, 'opening_bottle_price' => -1])->assertUnprocessable();
        $this->postJson('/brands', $data)->assertOk();
        $this->postJson('/brands', $data)->assertUnprocessable();
        $this->postJson('/brands', [...$data, 'bottle_size_ml' => 650])->assertOk();
        $id = Product::where('name', 'One brand')->firstOrFail()->id;
        $this->postJson('/drinks', ['name' => 'Overprecise', 'ingredients' => [['product_id' => $id, 'volume_ml' => 0.015]]])->assertUnprocessable();
    }

    public function test_brand_and_drink_crud_and_recipe_protection(): void
    {
        $this->seedAndSignIn();
        $this->get('/brands')->assertOk();
        $this->get('/drinks')->assertOk();
        $brand = ['name' => 'Test Gin', 'bottle_size_ml' => 750, 'excise_code' => 'TEST', 'opening_date' => '2026-09-01', 'opening_bottles' => 0, 'opening_ml' => 0];
        $this->post('/brands', $brand)->assertRedirect('/brands');
        $id = Product::where('name', 'Test Gin')->firstOrFail()->id;
        $this->assertNull((new ProductNameMatcher)->match(1, 'pos', 'Test Gin (750ml)'));
        $this->post('/mappings', ['product_id' => $id, 'source' => 'pos', 'source_name' => 'TG Gin (750ml)'])->assertRedirect('/mappings');
        $this->assertDatabaseHas('product_aliases', ['product_id' => $id, 'source' => 'pos', 'normalised_name' => 'TG GIN (750ML)']);
        $this->assertSame('Test Gin', (new ProductNameMatcher)->match(1, 'pos', 'TG Gin (750ml)')->name);
        $this->assertNull((new ProductNameMatcher)->match(1, 'pos', 'TG Gin (330ml)'));
        $this->get('/brands?edit='.$id)->assertOk()->assertSee('Test Gin')->assertSee('value="750"', false)->assertDontSee('value="750.00"', false);
        $this->put('/brands/'.$id, [...$brand, 'name' => 'Updated Gin'])->assertRedirect('/brands');
        $this->assertDatabaseHas('products', ['id' => $id, 'name' => 'Updated Gin']);
        $drink = ['name' => 'Test Martini', 'ingredients' => [['product_id' => $id, 'volume_ml' => 45]]];
        $this->post('/drinks', $drink)->assertRedirect('/drinks');
        $recipeId = DB::table('recipes')->where('name', 'Test Martini')->value('id');
        $this->get('/drinks?edit='.$recipeId)->assertOk()->assertSee('Test Martini');
        $this->put('/drinks/'.$recipeId, [...$drink, 'ingredients' => [['product_id' => $id, 'volume_ml' => 60]]])->assertRedirect('/drinks');
        $this->assertDatabaseHas('recipe_ingredients', ['recipe_id' => $recipeId, 'volume_ml' => 60]);
        $this->delete('/brands/'.$id)->assertSessionHasErrors();
        $this->assertDatabaseHas('products', ['id' => $id]);
        $this->delete('/drinks/'.$recipeId)->assertRedirect('/drinks');
        $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $recipeId]);
        $this->delete('/brands/'.$id)->assertRedirect('/brands');
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }

    public function test_opening_stock_and_invalid_recipe(): void
    {
        $this->seedAndSignIn();
        $data = ['name' => 'Opening Gin', 'bottle_size_ml' => 750, 'opening_date' => '2026-09-01', 'opening_bottles' => 2, 'opening_ml' => 100];
        $this->post('/brands', $data)->assertRedirect('/brands');
        $id = Product::where('name', 'Opening Gin')->firstOrFail()->id;
        $this->assertDatabaseHas('stock_movements', ['product_id' => $id, 'volume_ml' => 1600]);
        $this->put('/brands/'.$id, [...$data, 'opening_bottles' => 3])->assertRedirect('/brands');
        $this->assertDatabaseHas('stock_movements', ['product_id' => $id, 'volume_ml' => 2350]);
        $this->post('/drinks', ['name' => 'Invalid', 'ingredients' => [['product_id' => 99999, 'volume_ml' => -1]]])->assertSessionHasErrors();
        $this->assertDatabaseMissing('recipes', ['name' => 'Invalid']);
        $this->delete('/brands/'.$id)->assertSessionHasErrors();
    }
}
