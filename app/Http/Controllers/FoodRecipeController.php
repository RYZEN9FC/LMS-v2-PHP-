<?php

namespace App\Http\Controllers;

use App\Models\FoodIngredient;
use App\Models\Outlet;
use App\Support\CurrentOutlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FoodRecipeController extends Controller
{
    public function __construct(private readonly CurrentOutlet $currentOutlet) {}

    public function index(Request $request)
    {
        $outlet = $this->currentOutlet->id();
        $recipes = DB::table('food_recipes')->where('outlet_id', $outlet)->where('is_active', true)->orderBy('name')->get();
        $versions = DB::table('food_recipe_versions as v')->joinSub(
            DB::table('food_recipe_versions')->selectRaw('food_recipe_id, MAX(version) as version')->groupBy('food_recipe_id'),
            'latest', fn ($join) => $join->on('latest.food_recipe_id', '=', 'v.food_recipe_id')->on('latest.version', '=', 'v.version')
        )->select('v.*')->get()->keyBy('food_recipe_id');
        $versionIds = $versions->pluck('id');
        $recipeIngredients = DB::table('food_recipe_ingredients as ri')->join('food_ingredients as i', 'i.id', '=', 'ri.food_ingredient_id')
            ->whereIn('ri.food_recipe_version_id', $versionIds)->select('ri.*', 'i.name', 'i.base_unit')->orderBy('i.name')->get()->groupBy('food_recipe_version_id');
        $ingredients = FoodIngredient::where('outlet_id', $outlet)->where('is_active', true)->orderBy('name')->get();
        $editing = $request->filled('edit') ? $recipes->firstWhere('id', $request->integer('edit')) : null;
        abort_if($request->filled('edit') && ! $editing, 404);

        return view('food.recipes.index', compact('recipes', 'versions', 'recipeIngredients', 'ingredients', 'editing'));
    }

    public function save(Request $request, ?int $id = null)
    {
        $outlet = $this->currentOutlet->id();
        $request->merge(['name' => trim(preg_replace('/\s+/u', ' ', (string) $request->input('name')))]);
        if ($id) {
            abort_unless(DB::table('food_recipes')->where('outlet_id', $outlet)->where('id', $id)->where('is_active', true)->exists(), 404);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('food_recipes')->where('outlet_id', $outlet)->ignore($id)],
            'yield_quantity' => ['required', 'numeric', 'min:0.001', 'max:100000', 'decimal:0,3'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.ingredient_id' => ['required', 'integer', 'distinct', Rule::exists('food_ingredients', 'id')->where('outlet_id', $outlet)->where('is_active', true)],
            'ingredients.*.quantity_base' => ['required', 'numeric', 'min:0.001', 'max:100000000', 'decimal:0,3'],
        ]);

        DB::transaction(function () use ($data, $outlet, &$id) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            if ($id) {
                DB::table('food_recipes')->where('id', $id)->update(['name' => trim($data['name']), 'updated_at' => now()]);
                $version = (int) DB::table('food_recipe_versions')->where('food_recipe_id', $id)->max('version') + 1;
            } else {
                $id = DB::table('food_recipes')->insertGetId([
                    'outlet_id' => $outlet, 'name' => trim($data['name']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $version = 1;
            }
            $versionId = DB::table('food_recipe_versions')->insertGetId([
                'food_recipe_id' => $id, 'version' => $version, 'yield_quantity' => $data['yield_quantity'],
                'effective_from' => $data['effective_from'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($data['ingredients'] as $ingredient) {
                DB::table('food_recipe_ingredients')->insert([
                    'food_recipe_version_id' => $versionId, 'food_ingredient_id' => $ingredient['ingredient_id'],
                    'quantity_base' => $ingredient['quantity_base'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('food.recipes.index')->with('status', 'Dish recipe saved as a new version.');
    }

    public function delete(int $id)
    {
        $outlet = $this->currentOutlet->id();
        $query = DB::table('food_recipes')->where('outlet_id', $outlet)->where('id', $id);
        abort_unless($query->exists(), 404);
        $query->update(['is_active' => false, 'updated_at' => now()]);

        return redirect()->route('food.recipes.index')->with('status', 'Dish archived. Historical recipe versions are preserved.');
    }
}
