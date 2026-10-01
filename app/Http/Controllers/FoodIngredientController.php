<?php

namespace App\Http\Controllers;

use App\Models\FoodIngredient;
use App\Models\FoodStockMovement;
use App\Models\Outlet;
use App\Support\CurrentOutlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FoodIngredientController extends Controller
{
    private const BASE_UNITS = ['g', 'ml', 'piece'];

    public function __construct(private readonly CurrentOutlet $currentOutlet) {}

    public function index(Request $request)
    {
        $outlet = $this->currentOutlet->id();
        $ingredients = FoodIngredient::where('outlet_id', $outlet)->orderBy('name')->get();
        $editing = $request->filled('edit') ? FoodIngredient::where('outlet_id', $outlet)->findOrFail($request->integer('edit')) : null;
        $locked = $editing && ($editing->movements()->exists() || DB::table('food_recipe_ingredients')->where('food_ingredient_id', $editing->id)->exists());
        $baseUnits = self::BASE_UNITS;

        return view('food.ingredients.index', compact('ingredients', 'editing', 'locked', 'baseUnits'));
    }

    public function save(Request $request, ?int $id = null)
    {
        $outlet = $this->currentOutlet->id();
        $ingredient = $id ? FoodIngredient::where('outlet_id', $outlet)->findOrFail($id) : new FoodIngredient(['outlet_id' => $outlet]);
        $request->merge([
            'name' => trim(preg_replace('/\s+/u', ' ', (string) $request->input('name'))),
            'category' => trim(preg_replace('/\s+/u', ' ', (string) $request->input('category'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('food_ingredients')->where('outlet_id', $outlet)->ignore($id)],
            'category' => ['nullable', 'string', 'max:80'],
            'base_unit' => ['required', Rule::in(self::BASE_UNITS)],
            'purchase_unit' => ['required', 'string', 'max:32'],
            'purchase_to_base' => ['required', 'numeric', 'min:0.001', 'max:100000000', 'decimal:0,4'],
            'opening_quantity' => [$id ? 'prohibited' : 'nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,3'],
            'opening_purchase_rate' => [$id ? 'prohibited' : Rule::requiredIf(fn () => (float) $request->input('opening_quantity', 0) > 0), 'nullable', 'numeric', 'min:0', 'max:1000000000', 'decimal:0,2'],
            'opening_date' => [$id ? 'prohibited' : Rule::requiredIf(fn () => (float) $request->input('opening_quantity', 0) > 0), 'nullable', 'date_format:Y-m-d'],
        ]);
        $locked = $ingredient->exists && ($ingredient->movements()->exists()
            || DB::table('food_recipe_ingredients')->where('food_ingredient_id', $ingredient->id)->exists());
        if ($locked && ($ingredient->base_unit !== $data['base_unit'] || $ingredient->purchase_unit !== $data['purchase_unit']
            || (float) $ingredient->purchase_to_base !== (float) $data['purchase_to_base'])) {
            throw ValidationException::withMessages(['purchase_unit' => 'Units cannot be changed after this ingredient is used. Create a separate ingredient if its purchase format changed.']);
        }
        DB::transaction(function () use ($ingredient, $data, $outlet, $id) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            $ingredient->fill([
                'name' => $data['name'], 'category' => $data['category'] ?: null,
                'base_unit' => $data['base_unit'], 'purchase_unit' => trim($data['purchase_unit']),
                'purchase_to_base' => $data['purchase_to_base'], 'is_active' => true,
            ])->save();

            $openingQuantity = (float) ($data['opening_quantity'] ?? 0);
            if ($id || $openingQuantity <= 0) {
                return;
            }

            $quantityBase = round($openingQuantity * (float) $ingredient->purchase_to_base, 3);
            $openingValue = round($openingQuantity * (float) $data['opening_purchase_rate'], 2);
            FoodStockMovement::create([
                'outlet_id' => $outlet,
                'food_ingredient_id' => $ingredient->id,
                'entered_by' => auth()->id(),
                'effective_date' => $data['opening_date'],
                'movement_type' => 'opening',
                'quantity_base' => $quantityBase,
                'unit_cost_per_base' => $openingValue / $quantityBase,
                'value_change' => $openingValue,
                'reference' => 'Opening food stock setup',
            ]);
        });

        return redirect()->route('food.ingredients.index')->with('status', 'Ingredient saved.');
    }

    public function delete(int $id)
    {
        $outlet = $this->currentOutlet->id();

        return DB::transaction(function () use ($outlet, $id) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            $ingredient = FoodIngredient::where('outlet_id', $outlet)->findOrFail($id);
            if ($ingredient->movements()->exists() || DB::table('food_recipe_ingredients')->where('food_ingredient_id', $id)->exists()) {
                return back()->withErrors(['delete' => 'This ingredient has stock or recipe history and cannot be deleted. Marking ingredients inactive will be added with lifecycle controls.']);
            }
            $ingredient->delete();

            return redirect()->route('food.ingredients.index')->with('status', 'Ingredient deleted.');
        });
    }
}
