<x-layouts.app title="Upload history">
<div class="page-head"><div><h1>Upload history</h1><p class="sub">Applied imports are recorded once. Resume a saved preview to submit its remaining rows.</p></div></div>
<section class="card"><div class="pad"><h2>Stock submissions</h2></div><div class="table-wrap"><table><thead><tr><th>Import</th><th>Source / file</th><th>Indent number</th><th>Period</th><th>Rows</th><th>Status</th><th>Details</th></tr></thead><tbody>
@foreach($imports as $import)
@php($meta = json_decode($import->metadata ?? '{}', true))
@php($indentNumber = $meta['indent_number'] ?? data_get($documentMetadata->get($meta['document_id'] ?? ''), 'indent_number'))
<tr><td>#{{ $import->id }}</td><td>{{ $import->source_type }} · {{ $import->file_name ?? 'Legacy import' }}</td><td>{{ $import->source_type === 'excise' ? ($indentNumber ?: 'Not recorded') : '—' }}</td><td>{{ $import->effective_from }} — {{ $import->effective_to }}</td><td>{{ $import->row_count }}</td><td>{{ $import->status }}</td><td>@foreach($meta['review'] ?? [] as $change)<div>{{ $change['name'] }}: {{ \App\Support\StockFormatter::ml($change['change_ml']) }}</div>@endforeach @if(!isset($meta['document_id']))Legacy submission: no saved source preview.@endif</td></tr>
@endforeach
</tbody></table></div><div class="pad">{{ $imports->links() }}</div></section>
<section class="card" style="margin-top:20px"><div class="pad"><h2>Saved documents</h2></div><div class="table-wrap"><table><thead><tr><th>File</th><th>Indent number</th><th>Period</th><th></th></tr></thead><tbody>@foreach($documents as $document) @php($docMeta = $documentMetadata->get($document->id, [])) <tr><td>{{ $document->file_name }}</td><td>{{ $document->source === 'excise' ? (data_get($docMeta, 'indent_number') ?: 'Not recorded') : '—' }}</td><td>{{ $document->effective_from }} — {{ $document->effective_to }}</td><td><a class="btn secondary" href="{{ route('uploads.'.$document->source, ['document'=>$document->id]) }}">Resume preview</a></td></tr>@endforeach</tbody></table></div></section>
</x-layouts.app>
