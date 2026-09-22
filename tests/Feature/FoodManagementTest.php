<?php

namespace Tests\Feature;

use App\Models\FoodIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoodManagementTest extends TestCase
{
    use RefreshDatabase;

    private function ingredient(string $name = 'Chicken Breast'): FoodIngredient
    {
        return FoodIngredient::create([
            'outlet_id' => 1, 'name' => $name, 'category' => 'Meat', 'base_unit' => 'g',
            'purchase_unit' => 'kg', 'purchase_to_base' => 1000,
            'low_stock_base' => 500, 'is_active' => true,
        ]);
    }

    public function test_login_requires_module_choice_and_food_can_be_selected(): void
    {
        $this->seed();
        $this->post('/login', ['email' => 'demo@example.com', 'password' => 'password'])->assertRedirect('/modules');
        $this->get('/modules')->assertOk()->assertSee('Liquor management')->assertSee('Food management');
        $this->post('/modules', ['module' => 'food'])->assertRedirect('/food')->assertSessionHas('active_module', 'food');
        $this->get('/food')->assertOk()->assertSee('Total gross food sales')->assertSee('Switch module');
    }

    public function test_manager_can_create_an_ingredient_and_quick_add_stock(): void
    {
        $this->seedAndSignIn('manager');
        $this->post('/food/ingredients', [
            'name' => 'Chicken Breast', 'category' => 'Meat', 'base_unit' => 'g',
            'purchase_unit' => 'kg', 'purchase_to_base' => 1000,
            'low_stock_base' => 500,
        ])->assertRedirect('/food/ingredients');
        $ingredient = FoodIngredient::where('name', 'Chicken Breast')->firstOrFail();

        $this->postJson('/food/stock/add', [
            'ingredient_name' => 'chicken breast', 'quantity' => 2, 'purchase_rate' => 500, 'effective_date' => '2026-09-22',
        ])->assertOk()->assertJsonPath('movement_id', 1);
        $this->assertDatabaseHas('food_stock_movements', [
            'food_ingredient_id' => $ingredient->id, 'quantity_base' => 2000, 'value_change' => 1000,
        ]);
        $this->get('/food/stock?as_at=2026-09-22')->assertOk()->assertSee('2 kg')->assertSee('₹1,000.00');
    }

    public function test_quick_stock_rejects_negative_zero_unknown_and_cross_outlet_ingredients(): void
    {
        $this->seedAndSignIn('operator');
        $ingredient = $this->ingredient();
        $this->post('/food/stock/add', ['ingredient_name' => $ingredient->name, 'quantity' => -1, 'purchase_rate' => 100, 'effective_date' => '2026-09-22'])
            ->assertSessionHasErrors('quantity');
        $this->post('/food/stock/add', ['ingredient_name' => $ingredient->name, 'quantity' => 0, 'purchase_rate' => 100, 'effective_date' => '2026-09-22'])
            ->assertSessionHasErrors('quantity');
        $this->post('/food/stock/add', ['ingredient_name' => 'Unknown', 'quantity' => 1, 'purchase_rate' => 100, 'effective_date' => '2026-09-22'])
            ->assertSessionHasErrors('ingredient_name');
        $this->assertDatabaseCount('food_stock_movements', 0);
    }

    public function test_recipe_edits_create_immutable_versions(): void
    {
        $this->seedAndSignIn();
        $chicken = $this->ingredient();
        $rice = $this->ingredient('Rice');
        $payload = [
            'name' => 'Chicken Biryani', 'yield_quantity' => 1, 'effective_from' => '2026-09-22',
            'ingredients' => [
                ['ingredient_id' => $chicken->id, 'quantity_base' => 250],
                ['ingredient_id' => $rice->id, 'quantity_base' => 180],
            ],
        ];
        $this->post('/food/dishes', $payload)->assertRedirect('/food/dishes');
        $recipe = DB::table('food_recipes')->first();
        $this->put('/food/dishes/'.$recipe->id, $payload + ['name' => 'Ignored by array union'])->assertRedirect('/food/dishes');
        $this->assertSame(2, DB::table('food_recipe_versions')->where('food_recipe_id', $recipe->id)->count());
        $this->assertSame(4, DB::table('food_recipe_ingredients')->count());
    }

    public function test_dashboard_calculates_requested_food_margin_metrics(): void
    {
        $this->seedAndSignIn();
        $ingredient = $this->ingredient();
        DB::table('food_sales')->insert([
            'outlet_id' => 1, 'effective_date' => '2026-09-22', 'source_name' => 'Chicken Biryani',
            'channel' => 'sold', 'quantity' => 5, 'gross_sales' => 1000, 'discount' => 0,
            'net_sales' => 1000, 'recipe_cost' => 300, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('food_stock_movements')->insert([
            'outlet_id' => 1, 'food_ingredient_id' => $ingredient->id, 'effective_date' => '2026-09-22',
            'movement_type' => 'wastage', 'quantity_base' => -100, 'unit_cost_per_base' => 0.5,
            'value_change' => -50, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/food?from=2026-09-22&to=2026-09-22')->assertOk()
            ->assertSee('₹1,000.00')->assertSee('₹350.00')->assertSee('₹650.00')
            ->assertSee('₹700.00')->assertSee('5.0%');
    }

    public function test_wastage_uses_available_stock_cost_and_cannot_make_stock_negative(): void
    {
        $this->seedAndSignIn('operator');
        $ingredient = $this->ingredient();
        DB::table('food_stock_movements')->insert([
            'outlet_id' => 1, 'food_ingredient_id' => $ingredient->id, 'entered_by' => auth()->id(),
            'effective_date' => '2026-09-22', 'movement_type' => 'receipt', 'quantity_base' => 2000,
            'unit_cost_per_base' => 0.5, 'value_change' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post('/food/wastage', [
            'ingredient_name' => 'Chicken Breast', 'quantity_base' => 100, 'effective_date' => '2026-09-22', 'note' => 'Preparation waste',
        ])->assertRedirect('/food/wastage');
        $this->assertDatabaseHas('food_stock_movements', [
            'food_ingredient_id' => $ingredient->id, 'movement_type' => 'wastage',
            'quantity_base' => -100, 'value_change' => -50,
        ]);
        $this->post('/food/wastage', [
            'ingredient_name' => 'Chicken Breast', 'quantity_base' => 2000, 'effective_date' => '2026-09-22',
        ])->assertSessionHasErrors('quantity_base');
        $this->assertEquals(1900, DB::table('food_stock_movements')->where('food_ingredient_id', $ingredient->id)->sum('quantity_base'));
    }

    public function test_viewer_can_read_food_but_cannot_change_stock_or_catalogue(): void
    {
        $this->seedAndSignIn('viewer');
        $this->get('/food')->assertOk();
        $this->get('/food/stock')->assertOk();
        $this->get('/food/stock/add')->assertForbidden();
        $this->post('/food/ingredients', [])->assertForbidden();
    }
}
