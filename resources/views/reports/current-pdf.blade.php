<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @font-face{font-family:Content;src:url("{{ public_path('fonts/Content-Regular.ttf') }}") format("truetype");font-weight:400}
        @font-face{font-family:Currency;src:url("{{ public_path('fonts/NotoSansDevanagari-Regular.ttf') }}") format("truetype");font-weight:400}
        @page{size:A4 landscape;margin:20px 22px}body{font-family:Content,sans-serif;font-size:9px;color:#1d2939;margin:0}.rupee{font-family:Currency}h1{font-size:19px;margin:0 0 4px}h2{font-size:12px;margin:13px 0 5px;color:#9a3412;text-transform:uppercase}p{margin:0 0 5px;color:#667085}.meta{margin-bottom:13px;padding-bottom:8px;border-bottom:1px solid #d0d5dd}.notice{padding:6px 8px;background:#fff7ed;color:#9a3412}.category{margin-bottom:11px}table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}tr{page-break-inside:avoid}th,td{border:1px solid #d0d5dd;padding:6px 5px;text-align:right;vertical-align:middle;overflow-wrap:break-word}th{background:#eef4ff;font-weight:bold}th:first-child,td:first-child{text-align:left;width:34%}.total{font-weight:bold;background:#f8fafc}.footer-total{margin-top:12px;padding:8px;border:1px solid #d0d5dd;background:#fff7ed;font-weight:bold;text-align:right}
    </style>
</head>
<body>
    <h1>{{ $outlet->name }} · Current expected stock</h1>
    <div class="meta"><p>Stock position as at the end of {{ $asAt->format('j M Y') }}</p><p>Quantities are displayed as whole bottles plus remaining millilitres. Landing price is the most recent receipt price for the bottle size.</p></div>
    @if($periodNotice ?? null)<p class="notice">{{ $periodNotice }}</p>@endif
    @php
        $categoryOrder = array_flip(['Beer', 'Alcopop', 'Wine', 'Vodka', 'Gin', 'Rum', 'Tequila', 'Whisky', 'Brandy', 'Liqueur', 'Other']);
        $groups = $rows->groupBy(fn($row) => $row['product']->spirit_type ?: 'Other')->sortBy(fn($items, $category) => $categoryOrder[$category] ?? 999);
    @endphp
    @forelse($groups as $category => $categoryRows)
        <section class="category">
            <h2>{{ $category }} · {{ $categoryRows->count() }} {{ \Illuminate\Support\Str::plural('brand', $categoryRows->count()) }}</h2>
            <table>
                <thead><tr><th>Item</th><th>Bottle size</th><th>Bottles + ml</th><th>Landing price</th><th>Cost of available stock</th></tr></thead>
                <tbody>
                    @foreach($categoryRows as $row)
                        <tr><td>{{ $row['product']->name }}</td><td>{{ number_format((float) $row['product']->bottle_size_ml, 0) }} ml</td><td>{{ \App\Support\StockFormatter::bottlesAndMl((float) $row['expected_ml'], (float) $row['product']->bottle_size_ml, true) }}</td><td>{!! $row['latest_bottle_price'] === null ? '—' : \App\Support\StockFormatter::pdfMoney((float) $row['latest_bottle_price']) !!}</td><td>{!! \App\Support\StockFormatter::pdfMoney($row['stock_value']) !!}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="total"><td colspan="4">{{ $categoryRows->contains(fn($r) => $r['stock_value'] === null) ? 'Known category subtotal — incomplete costs' : $category.' stock value' }}</td><td>{!! \App\Support\StockFormatter::pdfMoney((float) $categoryRows->sum('stock_value')) !!}</td></tr></tfoot>
            </table>
        </section>
    @empty
        <p>No products have been added yet.</p>
    @endforelse
    @if($rows->isNotEmpty())<div class="footer-total">{{ $rows->contains(fn($r) => $r['stock_value'] === null) ? 'Known stock value subtotal — incomplete costs:' : 'Total stock value:' }} {!! \App\Support\StockFormatter::pdfMoney((float) $rows->sum('stock_value')) !!}</div>@endif
</body>
</html>
