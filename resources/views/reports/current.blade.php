<x-layouts.app title="Current expected stock">
    <div class="page-head">
        <div><h1>Current expected stock</h1><p class="sub">Expected closing stock at the end of the selected date.</p></div>
        <form class="filter" method="get">
            <label>As at <input type="date" name="as_at" value="{{ $asAt->toDateString() }}"></label>
            <button class="btn secondary" type="submit">Refresh</button>
            <a class="btn secondary" data-file-download href="{{ route('reports.current.excel', ['as_at' => $asAt->toDateString()]) }}">Download Excel</a>
            <a class="btn" data-file-download href="{{ route('reports.current.pdf', ['as_at' => $asAt->toDateString()]) }}">Download PDF</a>
        </form>
    </div>
    @if($periodNotice)<p class="note" style="margin-bottom:16px">{{ $periodNotice }}</p>@endif
    <section class="card"><div class="pad"><h2>{{ $outlet->name }}</h2><p class="sub">As at end of {{ $asAt->format('j M Y') }}</p></div><x-current-stock-table :rows="$rows" /></section>
    <p class="note">Every stock quantity is shown as whole bottles plus remaining ml; decimal bottle equivalents are never used.</p>
</x-layouts.app>
