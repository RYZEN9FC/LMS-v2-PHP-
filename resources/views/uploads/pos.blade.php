<x-layouts.app title="POS upload">
    <div class="page-head"><div><h1>POS sales upload</h1><p class="sub">Upload the Item Wise Sales Report exported from the POS.</p></div></div>
    <section class="card upload-card">
        <div class="pad"><h2>Upload report</h2><p class="sub">Excel (.xlsx or .xls): Item Wise Sales Report or Item Wise Customer Report. Use customer reports for actual daily sales.</p></div>
        <div class="upload-body"><form method="post" enctype="multipart/form-data">@csrf<label class="drop-zone"><input type="file" name="report" accept=".xlsx,.xls" required><span>Select POS Excel file</span><small data-file-name>Excel workbook (.xlsx or .xls)</small></label><button class="btn" type="submit" style="margin-top:16px">Upload and preview</button></form><p class="note">Nothing changes stock at this stage. The report is only read and checked.</p></div>
    </section>
    @if($preview)
    <section class="card" style="margin-top:16px"><div class="pad"><h2>POS preview</h2><p class="sub">{{ $preview['file'] }}</p>
        <p>{{ $preview['from'] }} to {{ $preview['to'] }}</p>
        <p>{{ $preview['items'] }} liquor items · <span id="mapping-count">{{ $preview['mapped_items'] }}</span> saved mappings · Sold: {{ $preview['quantity'] }} · Complimentary: {{ $preview['comp'] }} · Non-chargeable: {{ $preview['nc'] }} @if(($preview['excluded_non_liquor'] ?? 0) > 0) · {{ $preview['excluded_non_liquor'] }} non-liquor rows excluded @endif</p>
        <p class="note">Sheets read: {{ implode(', ', $preview['sheets']) }}.</p>
        <p class="note">{{ $preview['granularity'] === 'daily' ? 'Daily transaction dates are available.' : 'This file contains period totals, posted on its end date—not reconstructed daily sales.' }} Customer reports use the explicit Item Type values liquor-item, nc-liquor-item, and so-liquor; all other item types are excluded. Discounts are not automatically classified as complimentary. Every imported row must be assigned a brand and serving rule, a saved cocktail recipe, or explicitly marked “Ignore — no alcohol stock” before submission.</p>
        <div class="actions"><button class="btn secondary" type="button" id="auto-match">Auto-match suggestions</button><button class="btn" type="button" id="submit-stock" data-report-end="{{ $preview['to'] }}" disabled>Review &amp; submit stock</button></div><p class="note">Choose a spirit type, then a brand. “Add new brand…” is the first option in every brand list. The review shows sold, complimentary and non-chargeable quantities before all three are deducted.</p>
    </div><div class="table-wrap"><table><thead><tr><th>POS item</th><th>Common brand</th><th>Category</th><th>Sold</th><th>Complimentary</th><th>Non-chargeable</th></tr></thead><tbody>
    @foreach($preview['lines'] as $item)
        <tr class="{{ $item['rules'] ? 'mapping-ok' : 'mapping-needs-attention' }}"><td>{{ $item['name'] }}<div class="note">Sold amount: {{ \App\Support\StockFormatter::money($item['sold_amount']) }}<br>Average sold unit price: {{ $item['unit_price'] === null ? 'Unknown' : \App\Support\StockFormatter::money($item['unit_price']) }}</div></td><td>@include('components.upload-mapping', ['source' => 'pos'])</td><td>{{ $item['category'] }}</td><td>{{ $item['sold'] }}</td><td>{{ $item['comp'] }}</td><td>{{ $item['nc'] }}</td></tr>
    @endforeach
    </tbody></table></div></section>
    @include('components.upload-mapping-script')
    @endif
</x-layouts.app>
