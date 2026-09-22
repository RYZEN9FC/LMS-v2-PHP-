@if($report['periodNotice'] ?? null)<p class="note">{{ $report['periodNotice'] }}</p>@endif
<p class="note">Closing = opening + initial stock introduced during the interval + indent refills - all POS consumption. Same-day costing uses opening, receipts, then sales.</p>
@php($indentDays = $report['days']->mapWithKeys(fn($day) => [$day->toDateString() => $report['rows']->contains(fn($row) => (float) ($row['days'][$day->toDateString()]['indent_ml'] ?? 0) > 0)]))
@if(empty($pdfCurrency))
<div class="interval-navigator" aria-label="Daily interval selector">
    <div class="interval-navigator-head">
        <div class="interval-navigator-heading"><strong>Daily intervals</strong><span>Select one 24-hour period</span></div>
        <span class="indent-key"><i aria-hidden="true"></i>Indent delivery</span>
    </div>
    <div class="interval-navigator-row" role="tablist" aria-label="Daily sub-intervals">
        <button type="button" class="interval-scroll interval-scroll-prev is-unavailable" aria-label="Show earlier intervals" aria-disabled="true"><span aria-hidden="true">←</span></button>
        <div class="interval-viewport">
            <div class="interval-track">
                @foreach($report['days'] as $day)
                    @php($nextDay = $day->copy()->addDay())
                    <button type="button" class="interval-tab {{ ($indentDays[$day->toDateString()] ?? false) ? 'has-indent' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-day-tab="{{ $day->toDateString() }}">
                        <span>{{ $day->month === $nextDay->month ? $day->format('j').'–'.$nextDay->format('j M') : $day->format('j M').'–'.$nextDay->format('j M') }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        <button type="button" class="interval-scroll interval-scroll-next" aria-label="Show later intervals"><span aria-hidden="true">→</span></button>
    </div>
</div>
@endif
<div class="table-wrap">
<table class="interval-table" data-spirit-filter>
    <thead>
        <tr>
            <th rowspan="3">Brand</th>
            <th rowspan="3">Opening stock<br><span class="muted">{{ $from->format('j M') }}</span></th>
            @foreach($report['days'] as $day)
                <th colspan="8" class="group" data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ $day->format('j M') }}</th>
            @endforeach
            <th rowspan="3" class="group">Total indent refill<br>{{ $from->format('j M') }} – {{ $to->format('j M') }}</th>
            <th colspan="2" rowspan="2" class="group">Total POS stock sale</th>
            <th colspan="2" rowspan="2" class="group">Expected stock<br>end {{ $to->format('j M') }}</th>
            <th rowspan="3">Landing price</th>
            <th rowspan="3">Stock cost</th>
        </tr>
        <tr>
            @foreach($report['days'] as $day)
                <th rowspan="2" data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">Indent refill</th>
                <th colspan="7" class="soft" data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">POS sale</th>
            @endforeach
        </tr>
        <tr>
            @foreach($report['days'] as $day)
                <th data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">30 ml<br>pegs</th><th data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">60 ml<br>pegs</th><th data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">Full<br>bottles</th><th data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">Cocktails</th><th data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">Other ml<br>servings</th><th data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">Total stock use<br>Bottles + ml</th><th data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">Total stock use<br>Total ml</th>
            @endforeach
            <th>Bottles + ml</th><th>Total ml</th>
            <th>Bottles + ml</th><th>Total ml</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['rows'] as $row)
            <tr data-spirit-type="{{ $row['product']->spirit_type }}">
                <td><strong>{{ $row['product']->name }}</strong><br><span class="muted">{{ number_format((float) $row['product']->bottle_size_ml, 0) }} ml</span></td>
                <td class="final">{{ \App\Support\StockFormatter::bottlesAndMl((float) $row['opening_ml'], (float) $row['product']->bottle_size_ml, true) }}@if($row['total_opening_ml'] > 0)<br><small>Initial stock added in period: {{ \App\Support\StockFormatter::ml($row['total_opening_ml']) }}</small>@endif</td>
                @foreach($report['days'] as $day)
                    @php($daily = $row['days'][$day->toDateString()])
                    <td data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ \App\Support\StockFormatter::bottlesAndMl((float) $daily['indent_ml'], (float) $row['product']->bottle_size_ml) }}@if($daily['opening_ml'] > 0)<br><small>Initial stock: {{ \App\Support\StockFormatter::ml($daily['opening_ml']) }}</small>@endif</td>
                    <td data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ rtrim(rtrim(number_format($daily['sales']['peg_30'], 3), '0'), '.') ?: '0' }}</td>
                    <td data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ rtrim(rtrim(number_format($daily['sales']['peg_60'], 3), '0'), '.') ?: '0' }}</td>
                    <td data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ rtrim(rtrim(number_format($daily['sales']['full_bottle'], 3), '0'), '.') ?: '0' }}</td>
                    <td data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ rtrim(rtrim(number_format($daily['sales']['cocktail'], 3), '0'), '.') ?: '0' }}</td>
                    <td data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ $daily['sales']['measured'] }}</td>
                    <td data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ \App\Support\StockFormatter::bottlesAndMl((float) $daily['sale_ml'], (float) $row['product']->bottle_size_ml) }}</td>
                    <td data-day="{{ $day->toDateString() }}" data-indent-day="{{ ($indentDays[$day->toDateString()] ?? false) ? '1' : '0' }}">{{ \App\Support\StockFormatter::ml((float) $daily['sale_ml']) }}</td>
                @endforeach
                <td class="final">{{ \App\Support\StockFormatter::bottlesAndMl((float) $row['total_indent_ml'], (float) $row['product']->bottle_size_ml, true) }}</td>
                <td class="final">{{ \App\Support\StockFormatter::bottlesAndMl((float) $row['total_sale_ml'], (float) $row['product']->bottle_size_ml, true) }}</td>
                <td class="final">{{ \App\Support\StockFormatter::ml((float) $row['total_sale_ml']) }}</td>
                <td class="final">{{ \App\Support\StockFormatter::bottlesAndMl((float) $row['ending_ml'], (float) $row['product']->bottle_size_ml, true) }}</td>
                <td class="final">{{ \App\Support\StockFormatter::ml((float) $row['ending_ml']) }}</td>
                <td>@if(!empty($pdfCurrency)){!! $row['latest_bottle_price'] === null ? '—' : \App\Support\StockFormatter::pdfMoney((float) $row['latest_bottle_price']) !!}@else{{ $row['latest_bottle_price'] === null ? '—' : \App\Support\StockFormatter::money((float) $row['latest_bottle_price']) }}@endif</td>
                <td>@if(!empty($pdfCurrency)){!! \App\Support\StockFormatter::pdfMoney($row['stock_value']) !!}@else{{ \App\Support\StockFormatter::money($row['stock_value']) }}@endif</td>
            </tr>
        @empty
            <tr><td colspan="{{ 9 + $report['days']->count() * 8 }}" class="empty">No products have been added yet.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
@if(empty($pdfCurrency))
<style>
    .interval-navigator{padding:18px 24px 20px;background:#fafafa;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
    .interval-navigator-head{display:grid;grid-template-columns:44px minmax(0,1fr) 44px;gap:10px;align-items:center;margin-bottom:12px}.interval-navigator-heading{grid-column:2;display:flex;align-items:baseline;gap:10px;min-width:0}.interval-navigator-head strong{font-size:15px;line-height:1.3;letter-spacing:-.025em}.interval-navigator-heading span{color:#71717a;font-size:12px;line-height:1.3}.indent-key{grid-column:2;grid-row:1;justify-self:end;display:flex;align-items:center;gap:7px;color:#52525b;font-size:12px;line-height:1.3}.indent-key i{flex:0 0 10px;width:10px;height:10px;background:#22c55e}
    .interval-navigator-row{display:grid;grid-template-columns:44px minmax(0,1fr) 44px;gap:10px;align-items:stretch}.interval-viewport{min-width:0;overflow:hidden;border-bottom:1px solid #d4d4d8}.interval-track{display:flex;gap:5px;min-width:0;overflow-x:auto;scrollbar-width:none;scroll-snap-type:x mandatory}.interval-track::-webkit-scrollbar{display:none}
    .interval-scroll{width:44px;height:50px;border:1px solid #d4d4d8;background:#fff;color:#27272a;font:700 19px/1 Inter,sans-serif;cursor:pointer}.interval-scroll:hover{background:#fff7ed;border-color:#fb923c;color:#c2410c}.interval-scroll:focus-visible,.interval-tab:focus-visible{outline:2px solid #f97316;outline-offset:2px}.interval-scroll.is-unavailable{visibility:hidden;pointer-events:none}
    .interval-tab{scroll-snap-align:start;flex:0 0 112px;height:50px;display:flex;align-items:center;justify-content:center;border:1px solid #e4e4e7;border-bottom:3px solid #fb923c;background:#fff;color:#3f3f46;padding:0 10px;font-family:Inter,sans-serif;cursor:pointer}.interval-tab span{font-size:14px;line-height:1;font-weight:650;letter-spacing:-.02em;white-space:nowrap}.interval-tab:hover{background:#fff7ed;border-color:#fdba74}.interval-tab[aria-selected=true]{background:#ea580c;border-color:#ea580c;color:#fff}
    .interval-tab.has-indent:not([aria-selected=true]){background:#ecfdf3;border-color:#86efac;border-bottom-color:#22c55e;color:#166534}.interval-tab.has-indent[aria-selected=true]{box-shadow:inset 4px 0 #22c55e}
    .interval-table [data-indent-day="1"]{background:#f0fdf4}
    .interval-table [data-day][hidden]{display:none}
    .interval-table td{font-size:14px;line-height:1.35}.interval-table td:first-child{font-size:14px}
    @media (max-width:760px){.interval-navigator{padding:14px 12px}.interval-navigator-head{grid-template-columns:40px minmax(0,1fr) 40px;gap:7px}.interval-navigator-heading span{display:none}.indent-key{font-size:11px}.interval-navigator-row{grid-template-columns:40px minmax(0,1fr) 40px;gap:7px}.interval-scroll{width:40px}.interval-tab{flex-basis:104px}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{const table=document.querySelector('.interval-table'),navigator=document.querySelector('.interval-navigator'),track=navigator?.querySelector('.interval-track'),tabs=[...navigator?.querySelectorAll('.interval-tab')||[]],prev=navigator?.querySelector('.interval-scroll-prev'),next=navigator?.querySelector('.interval-scroll-next');if(!table||!navigator||!track||!tabs.length)return;let frame;const setUnavailable=(button,value)=>{if(!button)return;button.classList.toggle('is-unavailable',value);button.setAttribute('aria-disabled',String(value));};const syncArrows=()=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(()=>{setUnavailable(prev,track.scrollLeft<=2);setUnavailable(next,track.scrollLeft+track.clientWidth>=track.scrollWidth-2);});};const show=(day,focus=false)=>{table.querySelectorAll('[data-day]').forEach(cell=>{cell.hidden=cell.dataset.day!==day;});tabs.forEach(tab=>{const active=tab.dataset.dayTab===day;tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;if(active){tab.scrollIntoView({behavior:'smooth',block:'nearest',inline:'nearest'});if(focus)tab.focus();}});syncArrows();};tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>show(tab.dataset.dayTab));tab.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();const target=event.key==='Home'?0:event.key==='End'?tabs.length-1:Math.max(0,Math.min(tabs.length-1,index+(event.key==='ArrowRight'?1:-1)));show(tabs[target].dataset.dayTab,true);});});const scrollTrack=direction=>track.scrollBy({left:direction*Math.max(330,track.clientWidth*.7),behavior:'smooth'});prev?.addEventListener('click',()=>scrollTrack(-1));next?.addEventListener('click',()=>scrollTrack(1));track.addEventListener('scroll',syncArrows,{passive:true});window.addEventListener('resize',syncArrows);show(tabs[0].dataset.dayTab);syncArrows();});
</script>
@endif
