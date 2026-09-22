<x-layouts.app title="Food dashboard">
    <div class="page-head"><div><h1>Food dashboard</h1><p class="sub">Sales, ingredient cost, margin and wastage for the selected period.</p></div>
        <form class="filter" method="get"><label>From<input type="date" name="from" value="{{ $from }}"></label><label>To<input type="date" name="to" value="{{ $to }}"></label><button class="btn">Refresh</button></form>
    </div>
    <section class="metric-grid food-metrics">
        <article class="metric"><div class="label">Total gross food sales</div><div class="number">₹{{ number_format($metrics['gross_sales'], 2) }}</div><div class="sub">Chargeable completed food sales, excluding tax</div></article>
        <article class="metric"><div class="label">Total food cost</div><div class="number">₹{{ number_format($metrics['total_food_cost'], 2) }}</div><div class="sub">{{ number_format($metrics['food_cost_percent'], 1) }}% of gross food sales</div></article>
        <article class="metric"><div class="label">Food margin</div><div class="number">₹{{ number_format($metrics['food_margin'], 2) }}</div><div class="sub">{{ number_format($metrics['food_margin_percent'], 1) }}% after wastage</div></article>
        <article class="metric"><div class="label">Food margin without wastage</div><div class="number">₹{{ number_format($metrics['margin_without_wastage'], 2) }}</div><div class="sub">{{ number_format($metrics['margin_without_wastage_percent'], 1) }}% theoretical margin</div></article>
        <article class="metric"><div class="label">Wastage</div><div class="number">{{ number_format($metrics['wastage_percent'], 1) }}%</div><div class="sub">₹{{ number_format($metrics['wastage_cost'], 2) }} recorded wastage</div></article>
    </section>
    <section class="card" style="margin-top:24px"><div class="pad"><h2>Low-stock ingredients</h2><p class="sub">Ingredients at or below their configured threshold.</p></div>
        <div class="table-wrap"><table><thead><tr><th>Ingredient</th><th>Available</th><th>Threshold</th></tr></thead><tbody>
        @forelse($lowStock as $ingredient)<tr><td>{{ $ingredient->name }}</td><td>{{ \App\Support\FoodStockFormatter::quantity((float) ($ingredient->stock_base ?? 0), $ingredient->base_unit) }}</td><td>{{ \App\Support\FoodStockFormatter::quantity((float) $ingredient->low_stock_base, $ingredient->base_unit) }}</td></tr>
        @empty<tr><td colspan="3">No low-stock ingredients. Add ingredients and thresholds to begin monitoring.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <p class="note" style="margin-top:14px">Food sales and recipe costs will populate after the food POS import stage is connected. Manual stock receipts are available now.</p>
    <style>.food-metrics{grid-template-columns:repeat(5,minmax(0,1fr))}@media(max-width:1250px){.food-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.food-metrics{grid-template-columns:1fr}}</style>
</x-layouts.app>
