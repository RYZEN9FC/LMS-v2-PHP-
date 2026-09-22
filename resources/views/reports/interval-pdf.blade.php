<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @font-face{font-family:Content;src:url("{{ public_path('fonts/Content-Regular.ttf') }}") format("truetype");font-weight:400}
        @font-face{font-family:Currency;src:url("{{ public_path('fonts/NotoSansDevanagari-Regular.ttf') }}") format("truetype");font-weight:400}
        @page{size:A4 landscape;margin:17px 18px}body{font-family:Content,sans-serif;font-size:6.8px;color:#1d2939;margin:0}.rupee{font-family:Currency}h1{font-size:17px;margin:0 0 3px}h2{font-size:11px;margin:10px 0 4px;color:#9a3412;text-transform:uppercase}.meta{margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #d0d5dd;color:#667085}.notice{margin:5px 0;padding:5px 7px;background:#fff7ed;color:#9a3412}.day{page-break-before:always}.day.first{page-break-before:auto}.category{margin-bottom:8px}table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}tr{page-break-inside:avoid}th,td{border:1px solid #d0d5dd;padding:3.5px 2.5px;text-align:right;vertical-align:middle;overflow-wrap:break-word}th{background:#eef4ff;font-weight:bold}th:first-child,td:first-child{text-align:left;width:18%}.received{background:#ecfdf3}.closing{background:#fff7ed;font-weight:bold}
    </style>
</head>
<body>
@php
    $categoryOrder = array_flip(['Beer', 'Alcopop', 'Wine', 'Vodka', 'Gin', 'Rum', 'Tequila', 'Whisky', 'Brandy', 'Liqueur', 'Other']);
    $groups = $report['rows']->groupBy(fn($row) => $row['product']->spirit_type ?: 'Other')->sortBy(fn($items, $category) => $categoryOrder[$category] ?? 999);
    $runningStock = $report['rows']->mapWithKeys(fn($row) => [$row['product']->id => (float) $row['opening_ml']])->all();
@endphp
@foreach($report['days'] as $day)
    <section class="day {{ $loop->first ? 'first' : '' }}">
        <h1>{{ $outlet->name }} · Daily stock movement</h1>
        <div class="meta">Report range: {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }} · Daily interval: {{ $day->format('j M Y') }} – {{ $day->copy()->addDay()->format('j M Y') }} · Quantities use bottle-plus-ml format.</div>
        @if($report['periodNotice'] ?? null)<div class="notice">{{ $report['periodNotice'] }}</div>@endif
        @foreach($groups as $category => $categoryRows)
            <section class="category">
                <h2>{{ $category }} · {{ $categoryRows->count() }} {{ \Illuminate\Support\Str::plural('brand', $categoryRows->count()) }}</h2>
                <table>
                    <thead><tr><th>Brand</th><th>Bottle size</th><th>Opening stock</th><th>Stock received</th><th>30 ml pegs</th><th>60 ml pegs</th><th>Full bottles</th><th>Cocktails</th><th>Other ml servings</th><th>Total stock use</th><th>Closing stock</th><th>Landing price</th><th>Stock cost</th></tr></thead>
                    <tbody>
                    @foreach($categoryRows as $row)
                        @php
                            $product = $row['product'];
                            $daily = $row['days'][$day->toDateString()];
                            $openingMl = $runningStock[$product->id];
                            $receivedMl = (float) $daily['indent_ml'] + (float) $daily['opening_ml'];
                            $closingMl = $openingMl + $receivedMl - (float) $daily['sale_ml'];
                            $runningStock[$product->id] = $closingMl;
                        @endphp
                        <tr><td>{{ $product->name }}</td><td>{{ number_format((float) $product->bottle_size_ml, 0) }} ml</td><td>{{ \App\Support\StockFormatter::bottlesAndMl($openingMl, (float) $product->bottle_size_ml, true) }}</td><td class="received">{{ \App\Support\StockFormatter::bottlesAndMl($receivedMl, (float) $product->bottle_size_ml) }}</td><td>{{ $daily['sales']['peg_30'] }}</td><td>{{ $daily['sales']['peg_60'] }}</td><td>{{ $daily['sales']['full_bottle'] }}</td><td>{{ $daily['sales']['cocktail'] }}</td><td>{{ $daily['sales']['measured'] }}</td><td>{{ \App\Support\StockFormatter::bottlesAndMl((float) $daily['sale_ml'], (float) $product->bottle_size_ml) }}</td><td class="closing">{{ \App\Support\StockFormatter::bottlesAndMl($closingMl, (float) $product->bottle_size_ml, true) }}</td><td>{!! $row['latest_bottle_price'] === null ? '—' : \App\Support\StockFormatter::pdfMoney((float) $row['latest_bottle_price']) !!}</td><td>{!! \App\Support\StockFormatter::pdfMoney($row['stock_value']) !!}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    </section>
@endforeach
</body>
</html>
