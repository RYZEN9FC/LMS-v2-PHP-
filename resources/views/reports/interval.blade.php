<x-layouts.app title="Stock interval report">
    <div class="page-head">
        <div><h1>Daily stock movement and expected closing stock</h1><p class="sub">Brands are alphabetical. Sale counts are separated by POS sale type; stock use is calculated in ml.</p></div>
        <form class="filter" method="get">
            <label>From <input type="date" name="from" value="{{ $from->toDateString() }}"></label>
            <label>To <input type="date" name="to" value="{{ $to->toDateString() }}"></label>
            <button class="btn secondary" type="submit">Generate</button>
            <a class="btn secondary" href="{{ route('reports.interval.excel', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Download Excel</a>
            <a class="btn" href="{{ route('reports.interval.pdf', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Download PDF</a>
        </form>
    </div>
    <section class="card">
        <div class="pad"><h2>{{ $outlet->name }} · {{ $from->format('j M') }} – {{ $to->format('j M Y') }}</h2><p class="sub">Expected stock is the closing stock at the end of {{ $to->format('j M Y') }}.</p></div>
        @include('reports.partials.interval-table')
    </section>
    <p class="note">30 ml pegs, 60 ml pegs, full bottles and cocktails are POS quantities. “Total stock use” converts all of those sales to actual ingredient consumption.</p>
</x-layouts.app>
