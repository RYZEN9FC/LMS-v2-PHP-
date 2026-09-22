<x-layouts.app title="Excise upload">
    <div class="page-head"><div><h1>Excise indent upload</h1><p class="sub">Upload the Telangana Proforma Indent PDF for stock refills.</p></div></div>
    <section class="card upload-card">
        <div class="pad"><h2>Upload Proforma Indent</h2><p class="sub">Supported format: PDF. The indent number, date, bottle quantities and purchase values are read from the document.</p></div>
        <div class="upload-body"><form method="post" enctype="multipart/form-data" data-upload-progress-form data-progress-url="{{ route('uploads.excise.progress') }}">@csrf<label class="drop-zone"><input type="file" name="indent" accept="application/pdf,.pdf" required><span>Select Excise PDF</span><small data-file-name>Proforma Indent PDF</small></label><button class="btn" type="submit" style="margin-top:16px">Upload and preview</button>@include('components.upload-progress')</form><p class="note">Nothing changes stock at this stage. The indent identity is read and checked.</p></div>
    </section>
    @if($preview)
    <section class="card" style="margin-top:16px">
        <div class="pad"><h2>Indent preview</h2><p class="sub">{{ $preview['file'] }}</p>
            <p><strong>{{ $preview['indent_number'] }}</strong> · {{ $preview['date'] }} · {{ count($preview['lines']) }} items · <span id="mapping-count">{{ $preview['mapped_items'] }}</span> saved mappings</p>
            <p>Invoice value: ₹{{ number_format($preview['invoice_value'], 2) }} · Net indent value: ₹{{ number_format($preview['net_value'], 2) }}</p>
            <p class="note">Quantities and line values are extracted from the document. Bottle prices use the line amount divided by received bottles; invoice-level charges are shown separately in the net value. Stock has not been updated.</p><div class="actions"><button class="btn secondary" type="button" id="auto-match">Auto-match suggestions</button><button class="btn" type="button" id="submit-excise" data-indent-date="{{ $preview['date'] }}" data-indent-number="{{ $preview['indent_number'] }}" disabled>Review &amp; submit stock</button></div><p class="note">Every parsed indent row is included. Save a valid brand mapping for every row before submission; no rows are silently skipped.</p>
        </div>
        <div class="table-wrap"><table><thead><tr><th>Document product</th><th>Common brand</th><th>Code</th><th>Received stock</th><th>Bottle price</th><th>Line amount</th></tr></thead><tbody>
        @foreach($preview['lines'] as $item)
            <tr class="{{ !empty($item['common_product_id']) ? 'mapping-ok' : 'mapping-needs-attention' }}"><td>{{ $item['name'] }}</td><td>@include('components.upload-mapping', ['source' => 'excise'])</td><td>{{ $item['code'] }}</td><td>{{ \App\Support\StockFormatter::bottlesAndMl($item['volume_ml'], $item['size_ml'], true) }}</td><td>₹{{ number_format($item['bottle_price'], 2) }}</td><td>₹{{ number_format($item['amount'], 2) }}</td></tr>
        @endforeach
        </tbody></table></div>
    </section>
    @include('components.upload-mapping-script')
    @endif
</x-layouts.app>
