<x-layouts.app title="Expected Stock">
    <div class="page-head">
        <div>
            <h1>{{ $outlet->name }} · expected stock</h1>
            <p class="sub">As at end of {{ $asAt->format('j M Y') }} · calculated only from opening stock, excise indents and POS consumption.</p>
        </div>
        <div class="actions"><a class="btn" href="{{ route('reports.current') }}">Open full report</a></div>
    </div>
    <section class="metric-grid">
        <div class="metric"><div class="label">Total cost with tax</div><div class="number">{{ \App\Support\StockFormatter::money($totalCostWithTax) }}</div></div>
        <div class="metric"><div class="label">{{ $rows->contains(fn($r) => $r['stock_value'] === null) ? 'Known cost subtotal (incomplete)' : 'Cost of expected stock' }}</div><div class="number">{{ \App\Support\StockFormatter::money((float) $rows->sum('stock_value')) }}</div></div>
        <div class="metric"><div class="label">Data shown through</div><div class="number">{{ $asAt->format('j M') }}</div></div>
    </section>
    <section class="metric-grid">
        <div class="metric"><div class="label">POS revenue · excludes comp / NC</div><div class="number">{{ \App\Support\StockFormatter::money($financials['revenue']) }}</div></div>
        <div class="metric"><div class="label">Potential value · includes comp / NC</div><div class="number">{{ \App\Support\StockFormatter::money($financials['potential_revenue']) }}</div></div>
        <div class="metric"><div class="label">Alcohol gross profit · after all consumption</div><div class="number">{{ \App\Support\StockFormatter::money($financials['gross_profit']) }}</div></div>
    </section>
    <p class="note" style="margin-bottom:16px">Figures cover submitted alcohol items only. Customer-report revenue is after discounts and before tax; summary-report amounts are preserved as reported. Complimentary face value: {{ \App\Support\StockFormatter::money($financials['complimentary_value']) }}; NC face value: {{ \App\Support\StockFormatter::money($financials['nc_value']) }}. Potential value is not earned revenue. Gross profit excludes non-alcohol ingredients and operating costs; unknown historical costs are never treated as zero.</p>
    <section class="card"><div class="pad"><h2>Current expected stock</h2><p class="sub">Calculated from submitted opening stock, indent receipts and all POS consumption.</p></div><x-current-stock-table :rows="$rows" /></section>
</x-layouts.app>
