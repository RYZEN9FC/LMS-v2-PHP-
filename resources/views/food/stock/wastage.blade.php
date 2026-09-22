<x-layouts.app title="Record food wastage">
    <div class="page-head"><div><h1>Record wastage</h1><p class="sub">Record spoiled or discarded ingredients using their base measurement.</p></div></div>
    <section class="card"><div class="pad"><h2>Wastage entry</h2><p class="sub">The ingredient cost is calculated automatically from available stock.</p></div>
    @if($ingredients->isEmpty())<div class="pad">No ingredients are configured. <a href="{{ route('food.ingredients.index') }}">Add ingredients first</a>.</div>
    @else<form class="pad quick-stock-form" method="post" action="{{ route('food.wastage.store') }}">@csrf
        <label>Wastage date<input name="effective_date" type="date" required value="{{ old('effective_date',now()->toDateString()) }}"></label>
        <label class="wide">Ingredient<input name="ingredient_name" list="wastage-ingredient-list" required autocomplete="off" value="{{ old('ingredient_name') }}" placeholder="Start typing ingredient name"><datalist id="wastage-ingredient-list">@foreach($ingredients as $ingredient)<option value="{{ $ingredient->name }}">{{ $ingredient->base_unit }}</option>@endforeach</datalist></label>
        <label>Quantity<div class="quantity-input"><input name="quantity_base" type="number" min="0.001" max="100000000" step="0.001" required inputmode="decimal" value="{{ old('quantity_base') }}"><span data-waste-unit>unit</span></div></label>
        <label class="wide">Reason or note<input name="note" maxlength="1000" value="{{ old('note') }}" placeholder="Optional"></label>
        <button class="btn">Record wastage</button>
    </form>@endif</section>
    <section class="card" style="margin-top:24px"><div class="pad"><h2>Recent wastage</h2></div><div class="table-wrap"><table><thead><tr><th>Ingredient</th><th>Quantity</th><th>Cost</th><th>Date</th><th>Note</th></tr></thead><tbody>
    @forelse($recent as $movement)<tr><td>{{ $movement->ingredient->name }}</td><td>{{ \App\Support\FoodStockFormatter::quantity(abs((float)$movement->quantity_base),$movement->ingredient->base_unit) }}</td><td>₹{{ number_format(abs((float)$movement->value_change),2) }}</td><td>{{ $movement->effective_date->format('d M Y') }}</td><td>{{ $movement->note ?: '—' }}</td></tr>@empty<tr><td colspan="5">No wastage recorded.</td></tr>@endforelse
    </tbody></table></div></section>
    <style>.quick-stock-form{display:grid;grid-template-columns:160px minmax(240px,1fr) 210px;gap:14px;align-items:end}.quick-stock-form label{display:grid;gap:6px;color:#52525b;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.quantity-input{display:flex}.quantity-input input{min-width:0;flex:1}.quantity-input span{min-width:62px;display:grid;place-items:center;border:1px solid #d4d4d8;border-left:0;background:#fafafa;font-size:12px;text-transform:none}.quick-stock-form .wide{grid-column:span 2}@media(max-width:720px){.quick-stock-form{grid-template-columns:1fr}.quick-stock-form .wide{grid-column:auto}}</style>
    <script>(()=>{const input=document.querySelector('[name=ingredient_name]'),unit=document.querySelector('[data-waste-unit]');if(!input||!unit)return;const units=@json($ingredients->mapWithKeys(fn($ingredient)=>[$ingredient->name=>$ingredient->base_unit]));const update=()=>unit.textContent=units[input.value]||'unit';input.addEventListener('input',update);update();})();</script>
</x-layouts.app>
