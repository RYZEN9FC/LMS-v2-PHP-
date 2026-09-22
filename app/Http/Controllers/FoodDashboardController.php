<?php

namespace App\Http\Controllers;

use App\Models\FoodIngredient;
use App\Support\CurrentOutlet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FoodDashboardController extends Controller
{
    public function __construct(private readonly CurrentOutlet $currentOutlet) {}

    public function index(Request $request)
    {
        $outlet = $this->currentOutlet->id();
        [$from, $to] = $this->dates($request);
        $sales = DB::table('food_sales')->where('outlet_id', $outlet)->whereBetween('effective_date', [$from, $to]);
        $grossSales = (float) (clone $sales)->where('channel', 'sold')->sum('gross_sales');
        $recipeCost = (float) (clone $sales)->sum('recipe_cost');
        $wastageCost = abs((float) DB::table('food_stock_movements')->where('outlet_id', $outlet)
            ->where('movement_type', 'wastage')->whereBetween('effective_date', [$from, $to])->sum('value_change'));
        $totalFoodCost = $recipeCost + $wastageCost;
        $foodMargin = $grossSales - $totalFoodCost;
        $marginWithoutWastage = $grossSales - $recipeCost;
        $percent = fn (float $value): float => $grossSales > 0 ? round($value / $grossSales * 100, 1) : 0;

        $ingredients = $this->stockQuery($outlet, $to)->get();
        $lowStock = $ingredients->filter(fn ($ingredient) => $ingredient->low_stock_base !== null
            && (float) $ingredient->stock_base <= (float) $ingredient->low_stock_base)->values();

        $metrics = [
            'gross_sales' => $grossSales,
            'total_food_cost' => $totalFoodCost,
            'food_cost_percent' => $percent($totalFoodCost),
            'food_margin' => $foodMargin,
            'food_margin_percent' => $percent($foodMargin),
            'margin_without_wastage' => $marginWithoutWastage,
            'margin_without_wastage_percent' => $percent($marginWithoutWastage),
            'wastage_cost' => $wastageCost,
            'wastage_percent' => $percent($wastageCost),
        ];

        return view('food.dashboard', compact('metrics', 'from', 'to', 'lowStock'));
    }

    public function stock(Request $request)
    {
        $request->validate(['as_at' => ['nullable', 'date_format:Y-m-d']]);
        $to = $request->filled('as_at') ? Carbon::createFromFormat('Y-m-d', $request->string('as_at')->toString())->toDateString() : now()->toDateString();
        $ingredients = $this->stockQuery($this->currentOutlet->id(), $to)->get();

        return view('food.stock.index', compact('ingredients', 'to'));
    }

    private function dates(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $to = Carbon::createFromFormat('Y-m-d', $request->string('to')->toString() ?: now()->toDateString())->startOfDay();
        $from = Carbon::createFromFormat('Y-m-d', $request->string('from')->toString() ?: $to->copy()->startOfMonth()->toDateString())->startOfDay();
        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'The start date must be on or before the end date.']);
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    private function stockQuery(int $outlet, string $to)
    {
        return FoodIngredient::query()->where('outlet_id', $outlet)->where('is_active', true)
            ->withSum(['movements as stock_base' => fn ($query) => $query->whereDate('effective_date', '<=', $to)], 'quantity_base')
            ->withSum(['movements as stock_value' => fn ($query) => $query->whereDate('effective_date', '<=', $to)], 'value_change')
            ->orderBy('name');
    }
}
