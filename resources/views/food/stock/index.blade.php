<x-layouts.app title="Food stock">
    <div class="page-head"><div><h1>Current food stock</h1><p class="sub">Expected ingredient quantities at the end of {{ \Carbon\Carbon::parse($to)->format('j M Y') }}.</p></div>
        <form class="filter" method="get"><label>As at<input type="date" name="as_at" value="{{ $to }}"></label><button class="btn">Refresh</button>@if(app(\App\Support\CurrentOutlet::class)->allows('food.stock.manage'))<a class="btn secondary" href="{{ route('food.stock.create') }}">Add stock</a>@endif</form>
    </div>
    <section class="card"><div class="table-wrap"><table><thead><tr><th>Ingredient</th><th>Category</th><th>Available quantity</th><th>Current unit cost</th><th>Expected value</th></tr></thead><tbody>
        @forelse($ingredients as $ingredient)
        @php($stock = (float) ($ingredient->stock_base ?? 0))
        @php($stockValue = (float) ($ingredient->stock_value ?? 0))
        @php($averagePurchaseCost = $stock > 0 ? ($stockValue / $stock) * (float) $ingredient->purchase_to_base : 0)
        <tr><td><strong>{{ $ingredient->name }}</strong></td><td>{{ $ingredient->category ?? 'Other' }}</td><td>{{ \App\Support\FoodStockFormatter::quantity($stock, $ingredient->base_unit) }}</td><td>{{ $stock > 0 ? '₹'.number_format($averagePurchaseCost, 2).' / '.$ingredient->purchase_unit : '—' }}</td><td>₹{{ number_format($stockValue, 2) }}</td></tr>
        @empty<tr><td colspan="5">No ingredients yet. Create ingredients before entering stock.</td></tr>@endforelse
    </tbody></table></div></section>
</x-layouts.app>
