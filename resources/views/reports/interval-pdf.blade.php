<!doctype html>
<html><head><meta charset="utf-8"><style>
@font-face{font-family:Content;src:url("{{ public_path('fonts/Content-Regular.ttf') }}") format("truetype");font-weight:400;font-style:normal}
@font-face{font-family:Currency;src:url("{{ public_path('fonts/NotoSansDevanagari-Regular.ttf') }}") format("truetype");font-weight:400;font-style:normal}
@page{margin:24px}body{font-family:Content,sans-serif;font-size:9px;color:#1d2939}.rupee{font-family:Currency;font-weight:400}h1{font-size:18px;margin:0 0 5px}h2{font-size:13px;margin:12px 0 5px}p{margin:0 0 10px;color:#667085}table{border-collapse:collapse;width:100%;table-layout:fixed}thead{display:table-header-group}tr{page-break-inside:avoid}th,td{border:1px solid #d0d5dd;padding:6px 4px;text-align:right;white-space:normal;overflow-wrap:break-word}th{background:#fff3e8}th:first-child,td:first-child{text-align:left;width:130px}.final{background:#f8fafc;font-weight:bold}.segment{page-break-before:always}.muted{color:#667085}small{font-size:8px}
</style></head><body>
@foreach($report['days'] as $day)
<section class="{{ $loop->first ? '' : 'segment' }}">
    <h1>{{ $outlet->name }} · Daily stock movement</h1>
    <p>Report interval: {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}. Opening and aggregate columns cover the full interval on every page.</p>
    <h2>Daily detail: {{ $day->format('j M Y') }} · Segment {{ $loop->iteration }} of {{ $loop->count }}</h2>
    @include('reports.partials.interval-table', ['pdfCurrency' => true, 'report' => array_replace($report, ['days' => collect([$day])])])
</section>
@endforeach
</body></html>
