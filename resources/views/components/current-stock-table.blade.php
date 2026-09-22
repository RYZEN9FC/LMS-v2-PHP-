@props(['rows'])
<div class="table-wrap">
    <table class="current-stock-table" data-spirit-filter>
        <thead>
            <tr>
                <th>Item</th>
                <th>Bottle size</th>
                <th>Bottles + ml</th>
                <th>Landing price</th>
                <th>Cost of available stock</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr data-spirit-type="{{ $row['product']->spirit_type }}">
                    <td><strong>{{ $row['product']->name }}</strong></td>
                    <td>{{ number_format((float) $row['product']->bottle_size_ml, 0) }} ml</td>
                    <td>{{ \App\Support\StockFormatter::bottlesAndMl((float) $row['expected_ml'], (float) $row['product']->bottle_size_ml, true) }}</td>
                    <td>{{ $row['latest_bottle_price'] === null ? '—' : \App\Support\StockFormatter::money((float) $row['latest_bottle_price']) }}</td>
                    <td>{{ \App\Support\StockFormatter::money($row['stock_value']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No products have been added yet.</td></tr>
            @endforelse
        </tbody>
        @if($rows->isNotEmpty())
            <tfoot>
                <tr class="final">
                    <td colspan="4">{{ $rows->contains(fn($r) => $r['stock_value'] === null) ? 'Known stock cost subtotal — incomplete costs' : 'Total cost of available expected stock' }}</td>
                    <td>{{ \App\Support\StockFormatter::money((float) $rows->sum('stock_value')) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
