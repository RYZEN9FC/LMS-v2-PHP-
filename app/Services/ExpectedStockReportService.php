<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpectedStockReportService
{
    public function current(Outlet $outlet, Carbon $asAt): Collection
    {
        $products = $outlet->products()->where('is_active', true)->orderBy('name')->orderBy('bottle_size_ml')->get();
        $movements = $this->movements($outlet->id, $asAt)->groupBy('product_id');

        return $products->map(fn ($product) => $this->calculateBalance($product, $movements->get($product->id, collect())));
    }

    public function productBalance(Product $product, Carbon $asAt): array
    {
        return $this->calculateBalance($product, $this->movements($product->outlet_id, $asAt, $product->id));
    }

    /** Daily convention: opening, then receipts, then consumption, independent of upload order. */
    private function movements(int $outletId, Carbon $asAt, ?int $productId = null): Collection
    {
        return StockMovement::where('outlet_id', $outletId)->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->where('effective_date', '<', $asAt->copy()->addDay()->toDateString())->orderBy('effective_date')
            ->orderByRaw("CASE movement_type WHEN 'opening' THEN 0 WHEN 'indent' THEN 1 ELSE 2 END")->orderBy('id')->get();
    }

    private function calculateBalance(Product $product, Collection $movements): array
    {
        $quantity = 0.0;
        $value = 0.0;
        $latestBottlePrice = null;
        $unknownVolume = 0.0;
        $costOfSales = 0.0;
        $unknownSalesCost = false;
        foreach ($movements as $movement) {
            $volume = (float) $movement->volume_ml;
            if ($volume > 0) {
                $known = $movement->movement_type === 'indent' || $movement->receipt_price_per_ml !== null;
                $amount = (float) $movement->value_change;
                $shortfall = max(0, -$quantity);
                $quantity += $volume;
                $remaining = max(0, $volume - $shortfall);
                if ($known) {
                    $value += $amount * $remaining / $volume;
                    $latestBottlePrice = $amount / $volume * (float) $product->bottle_size_ml;
                } else {
                    $unknownVolume += $remaining;
                }
                if ($quantity <= 0) {
                    $value = $unknownVolume = 0;
                }

                continue;
            }
            $used = -$volume;
            $available = max(0, $quantity);
            if ($used > $available + 0.0001 || $unknownVolume > 0.0001) {
                $unknownSalesCost = true;
            }
            $fraction = $available > 0 ? min(1, $used / $available) : 0;
            $cost = $value * $fraction;
            $value -= $cost;
            $costOfSales += $cost;
            $unknownVolume *= 1 - $fraction;
            $quantity += $volume;
            if ($quantity <= 0.0001) {
                $value = $unknownVolume = 0;
            }
        }
        $costKnown = $unknownVolume < 0.0001 && $quantity >= 0;

        return ['product' => $product, 'expected_ml' => round($quantity, 2), 'stock_value' => $costKnown ? round($value, 2) : null,
            'latest_bottle_price' => $latestBottlePrice, 'average_cost_per_ml' => $costKnown && $quantity > 0 ? $value / $quantity : null,
            'cost_of_sales' => $unknownSalesCost ? null : $costOfSales, 'cost_known' => $costKnown];
    }

    public function interval(Outlet $outlet, Carbon $from, Carbon $to): array
    {
        $days = collect();
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $days->push($date->copy());
        }
        $all = $this->movements($outlet->id, $to)->groupBy('product_id');
        $rows = $outlet->products()->where('is_active', true)->orderBy('name')->orderBy('bottle_size_ml')->get()->map(function ($product) use ($all, $from, $days) {
            $history = $all->get($product->id, collect());
            $openingRows = $history->filter(fn ($m) => $m->effective_date->lt($from) || ($m->effective_date->isSameDay($from) && $m->movement_type === 'opening'));
            $opening = $this->calculateBalance($product, $openingRows);
            $range = $history->filter(fn ($m) => $m->effective_date->gte($from))->groupBy(fn ($m) => $m->effective_date->toDateString());
            $daily = $days->mapWithKeys(function ($day) use ($range, $from) {
                $movements = $range->get($day->toDateString(), collect());
                $sales = ['peg_30' => 0.0, 'peg_60' => 0.0, 'full_bottle' => 0.0, 'cocktail' => 0.0, 'measured' => 0.0];
                foreach ($movements->where('movement_type', 'sale') as $movement) {
                    $sales[$movement->sale_type] = ($sales[$movement->sale_type] ?? 0) + (float) $movement->sale_quantity;
                }

                return [$day->toDateString() => ['indent_ml' => (float) $movements->where('movement_type', 'indent')->sum('volume_ml'),
                    'opening_ml' => $day->isSameDay($from) ? 0 : (float) $movements->where('movement_type', 'opening')->sum('volume_ml'),
                    'sales' => $sales, 'sale_ml' => abs((float) $movements->where('movement_type', 'sale')->sum('volume_ml'))]];
            });
            $ending = $this->calculateBalance($product, $history);

            return ['product' => $product, 'opening_ml' => $opening['expected_ml'], 'days' => $daily,
                'total_opening_ml' => (float) $daily->sum('opening_ml'), 'total_indent_ml' => (float) $daily->sum('indent_ml'),
                'total_sale_ml' => (float) $daily->sum('sale_ml'), 'ending_ml' => $ending['expected_ml'],
                'stock_value' => $ending['stock_value'], 'latest_bottle_price' => $ending['latest_bottle_price']];
        });
        $periodNotice = $this->periodNotice($outlet->id, $from, $to);

        return compact('days', 'rows', 'periodNotice');
    }

    public function periodNotice(int $outletId, Carbon $from, Carbon $to): ?string
    {
        $imports = DB::table('imports')->where('outlet_id', $outletId)->where('source_type', 'pos')->where('status', 'applied')
            ->where('effective_from', '<=', $to->toDateString())->where('effective_to', '>=', $from->toDateString())->get();
        foreach ($imports as $import) {
            $metadata = json_decode($import->metadata ?? '{}', true);
            if (($metadata['granularity'] ?? 'period') === 'period') {
                return 'This period overlaps an aggregated POS import. Its consumption is posted on the source report end date; daily and intermediate closing balances are not complete daily figures. Use non-overlapping customer-level reports for daily accuracy.';
            }
        }

        return null;
    }

    public function financials(Outlet $outlet, Carbon $to, Collection $balances): array
    {
        $revenue = $comp = $nc = 0.0;
        $revenueKnown = true;
        $lines = DB::table('import_lines')->join('imports', 'imports.id', '=', 'import_lines.import_id')
            ->where('imports.outlet_id', $outlet->id)->where('imports.source_type', 'pos')->where('imports.status', 'applied')
            ->where('import_lines.effective_date', '<', $to->copy()->addDay()->toDateString())->select('import_lines.*')->get();
        foreach ($lines as $line) {
            $data = json_decode($line->raw_data ?? '{}', true);
            if (! isset($data['figures'])) {
                $revenueKnown = false;

                continue;
            }
            $revenue += $data['figures']['sold_amount'] ?? 0;
            $comp += $data['figures']['comp_amount'] ?? 0;
            $nc += $data['figures']['nc_amount'] ?? 0;
        }
        $knownCost = $balances->every(fn ($row) => $row['cost_of_sales'] !== null);
        $cogs = $knownCost ? $balances->sum('cost_of_sales') : null;

        return ['revenue' => $revenueKnown ? $revenue : null, 'complimentary_value' => $comp, 'nc_value' => $nc,
            'potential_revenue' => $revenueKnown ? $revenue + $comp + $nc : null, 'cost_of_sales' => $cogs,
            'gross_profit' => $revenueKnown && $knownCost ? $revenue - $cogs : null];
    }

    /** Sum net indent values (including tax/charges) recorded by Excise uploads through the dashboard date. */
    public function totalIndentCostWithTax(int $outletId, Carbon $asAt): float
    {
        return (float) DB::table('upload_documents')->where('outlet_id', $outletId)->where('source', 'excise')
            ->where('effective_to', '<=', $asAt->toDateString())->get(['metadata'])
            ->sum(fn ($document) => (float) (json_decode($document->metadata ?? '{}', true)['net_value'] ?? 0));
    }
}
