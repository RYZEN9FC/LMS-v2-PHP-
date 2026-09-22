<?php

namespace App\Http\Controllers;

use App\Models\FoodIngredient;
use App\Models\FoodStockMovement;
use App\Models\Outlet;
use App\Support\CurrentOutlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FoodStockController extends Controller
{
    public function __construct(private readonly CurrentOutlet $currentOutlet) {}

    public function create()
    {
        $outlet = $this->currentOutlet->id();
        $ingredients = FoodIngredient::where('outlet_id', $outlet)->where('is_active', true)->orderBy('name')->get();
        $recent = FoodStockMovement::with('ingredient')->where('outlet_id', $outlet)->where('movement_type', 'receipt')
            ->latest()->limit(20)->get();

        return view('food.stock.create', compact('ingredients', 'recent'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ingredient_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:100000000', 'decimal:0,3'],
            'purchase_rate' => ['required', 'numeric', 'min:0', 'max:1000000000', 'decimal:0,2'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
        ]);
        $outlet = $this->currentOutlet->id();
        $ingredient = $this->ingredientByName($outlet, $data['ingredient_name']);

        $movement = DB::transaction(function () use ($ingredient, $data, $outlet) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            $ingredient->refresh();
            $quantityBase = round((float) $data['quantity'] * (float) $ingredient->purchase_to_base, 3);
            $purchaseCost = round((float) $data['quantity'] * (float) $data['purchase_rate'], 2);
            $unitCost = $quantityBase > 0 ? $purchaseCost / $quantityBase : 0;

            return FoodStockMovement::create([
                'outlet_id' => $outlet, 'food_ingredient_id' => $ingredient->id, 'entered_by' => auth()->id(),
                'effective_date' => $data['effective_date'], 'movement_type' => 'receipt',
                'quantity_base' => $quantityBase, 'unit_cost_per_base' => $unitCost,
                'value_change' => $purchaseCost,
                'reference' => 'Manual kitchen stock entry',
            ]);
        });

        $message = $ingredient->name.' — '.((float) $data['quantity']).' '.$ingredient->purchase_unit.' added at ₹'.number_format((float) $data['purchase_rate'], 2).' per '.$ingredient->purchase_unit.' (₹'.number_format((float) $data['quantity'] * (float) $data['purchase_rate'], 2).' total).';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'movement_id' => $movement->id]);
        }

        return redirect()->route('food.stock.create')->with('status', $message);
    }

    public function wastage()
    {
        $outlet = $this->currentOutlet->id();
        $ingredients = FoodIngredient::where('outlet_id', $outlet)->where('is_active', true)->orderBy('name')->get();
        $recent = FoodStockMovement::with('ingredient')->where('outlet_id', $outlet)->where('movement_type', 'wastage')
            ->latest()->limit(20)->get();

        return view('food.stock.wastage', compact('ingredients', 'recent'));
    }

    public function storeWastage(Request $request)
    {
        $data = $request->validate([
            'ingredient_name' => ['required', 'string', 'max:255'],
            'quantity_base' => ['required', 'numeric', 'min:0.001', 'max:100000000', 'decimal:0,3'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $outlet = $this->currentOutlet->id();
        $ingredient = $this->ingredientByName($outlet, $data['ingredient_name']);

        DB::transaction(function () use ($ingredient, $data, $outlet) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            $ingredient->refresh();
            $available = (float) FoodStockMovement::where('outlet_id', $outlet)->where('food_ingredient_id', $ingredient->id)
                ->whereDate('effective_date', '<=', $data['effective_date'])->sum('quantity_base');
            $quantity = (float) $data['quantity_base'];
            if ($quantity > $available + 0.0001) {
                throw ValidationException::withMessages(['quantity_base' => 'Wastage exceeds the expected stock available on this date. Available: '.round($available, 3).' '.$ingredient->base_unit.'.']);
            }
            $stockValue = (float) FoodStockMovement::where('outlet_id', $outlet)->where('food_ingredient_id', $ingredient->id)
                ->whereDate('effective_date', '<=', $data['effective_date'])->sum('value_change');
            $unitCost = $available > 0 ? max(0, $stockValue / $available) : 0;
            FoodStockMovement::create([
                'outlet_id' => $outlet, 'food_ingredient_id' => $ingredient->id, 'entered_by' => auth()->id(),
                'effective_date' => $data['effective_date'], 'movement_type' => 'wastage',
                'quantity_base' => -$quantity, 'unit_cost_per_base' => $unitCost,
                'value_change' => -round($quantity * $unitCost, 2), 'reference' => 'Kitchen wastage',
                'note' => $data['note'] ?? null,
            ]);
        });

        $message = $ingredient->name.' — '.((float) $data['quantity_base']).' '.$ingredient->base_unit.' recorded as wastage.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('food.wastage.create')->with('status', $message);
    }

    private function ingredientByName(int $outlet, string $name): FoodIngredient
    {
        $normalised = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        $ingredient = FoodIngredient::where('outlet_id', $outlet)->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [$normalised])->first();
        if (! $ingredient) {
            throw ValidationException::withMessages(['ingredient_name' => 'Choose an existing ingredient from the list.']);
        }

        return $ingredient;
    }
}
