@props(['rows'])
<style>.current-stock-table .numeric { text-align:right !important; }</style>
<div class="table-wrap">
    <table class="current-stock-table" data-spirit-filter>
        <thead>
            <tr>
                <th rowspan="2">Item</th>
                <th rowspan="2">UON</th>
                <th colspan="2" class="group">Total</th>
                <th rowspan="2">Latest bottle price</th>
                <th rowspan="2">Cost of available stock</th>
            </tr>
            <tr>
                <th class="numeric">Total ml</th>
                <th>Bottles + ml</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr data-spirit-type="{{ $row['product']->spirit_type }}">
                    <td><strong>{{ $row['product']->name }}</strong></td>
                    <td>{{ number_format((float) $row['product']->bottle_size_ml, 0) }} ml</td>
                    <td class="numeric">{{ \App\Support\StockFormatter::ml((float) $row['expected_ml']) }}</td>
                    <td>{{ \App\Support\StockFormatter::bottlesAndMl((float) $row['expected_ml'], (float) $row['product']->bottle_size_ml, true) }}</td>
                    <td>{{ $row['latest_bottle_price'] === null ? '—' : \App\Support\StockFormatter::money((float) $row['latest_bottle_price']) }}</td>
                    <td>{{ \App\Support\StockFormatter::money($row['stock_value']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No products have been added yet.</td></tr>
            @endforelse
        </tbody>
        @if($rows->isNotEmpty())
            <tfoot>
                <tr class="final">
                    <td colspan="5">{{ $rows->contains(fn($r) => $r['stock_value'] === null) ? 'Known stock cost subtotal — incomplete costs' : 'Total cost of available expected stock' }}</td>
                    <td>{{ \App\Support\StockFormatter::money((float) $rows->sum('stock_value')) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
