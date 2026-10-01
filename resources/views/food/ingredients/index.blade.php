<x-layouts.app title="Food ingredients">
    <div class="page-head"><div><h1>Ingredients</h1><p class="sub">Configure measurement units, purchase conversions and opening stock.</p></div></div>
    @include('catalogue.feedback')
    @if(app(\App\Support\CurrentOutlet::class)->allows('food.catalogue.manage'))
    <section class="card"><div class="pad"><h2>{{ $editing ? 'Edit ingredient' : 'Add ingredient' }}</h2></div>
        <form class="pad catalogue-form" method="post" action="{{ $editing ? route('food.ingredients.update', $editing->id) : route('food.ingredients.store') }}">@csrf @if($editing)@method('PUT')@endif
            <label>Ingredient name<input name="name" required maxlength="255" value="{{ old('name', $editing?->name) }}" placeholder="Example: Chicken breast"></label>
            <label>Category<input name="category" maxlength="80" value="{{ old('category', $editing?->category) }}" placeholder="Meat, vegetables, dairy…"></label>
            <label>Base measurement<select name="base_unit" required @disabled($locked)>@foreach($baseUnits as $unit)<option value="{{ $unit }}" @selected(old('base_unit', $editing?->base_unit ?? 'g') === $unit)>{{ ['g'=>'Grams','ml'=>'Millilitres','piece'=>'Pieces'][$unit] }}</option>@endforeach</select>@if($locked)<input type="hidden" name="base_unit" value="{{ $editing->base_unit }}">@endif</label>
            <label>Purchase unit<input name="purchase_unit" required maxlength="32" value="{{ old('purchase_unit', $editing?->purchase_unit ?? 'kg') }}" placeholder="kg, litre, tray" @readonly($locked)></label>
            <label>Base quantity in one purchase unit<input name="purchase_to_base" type="number" min="0.001" max="100000000" step="0.0001" required value="{{ old('purchase_to_base', $editing?->purchase_to_base ?? 1000) }}" @readonly($locked)></label>
            @if(!$editing)
                <label>Opening stock date<input name="opening_date" type="date" value="{{ old('opening_date', now()->toDateString()) }}"></label>
                <label>Opening stock quantity<input name="opening_quantity" type="number" min="0" max="100000000" step="0.001" value="{{ old('opening_quantity', 0) }}"><span class="muted">Enter this in the purchase unit above.</span></label>
                <label>Opening stock rate (₹ per purchase unit)<input name="opening_purchase_rate" type="number" min="0" max="1000000000" step="0.01" value="{{ old('opening_purchase_rate') }}" placeholder="Required when opening stock is above zero"></label>
            @endif
            <div class="actions"><button class="btn">Save ingredient</button>@if($editing)<a class="btn secondary" href="{{ route('food.ingredients.index') }}">Cancel</a>@endif</div>
            <p class="note">Example: base unit g, purchase unit kg, conversion 1000. Opening stock and later receipts are entered in the purchase unit. @if($locked)Units are locked because this ingredient already has stock or recipe history.@endif</p>
        </form>
    </section>
    @endif
    <section class="card" style="margin-top:24px"><div class="pad"><h2>{{ $ingredients->count() }} ingredients</h2><input type="search" placeholder="Search ingredients" aria-label="Search ingredients" oninput="filterCatalogue(this.value)"></div>
        <div class="table-wrap"><table><thead><tr><th>Ingredient</th><th>Category</th><th>Purchase unit</th><th>Conversion</th><th>Actions</th></tr></thead><tbody>
        @forelse($ingredients as $ingredient)<tr data-catalogue-row><td><strong>{{ $ingredient->name }}</strong></td><td>{{ $ingredient->category ?? 'Other' }}</td><td>{{ $ingredient->purchase_unit }}</td><td>1 {{ $ingredient->purchase_unit }} = {{ (float)$ingredient->purchase_to_base }} {{ $ingredient->base_unit }}</td><td>@if(app(\App\Support\CurrentOutlet::class)->allows('food.catalogue.manage'))<div class="actions"><a class="btn secondary" href="{{ route('food.ingredients.index',['edit'=>$ingredient->id]) }}">Edit</a><form method="post" action="{{ route('food.ingredients.destroy',$ingredient->id) }}" onsubmit="return confirm('Delete this ingredient? Ingredients with history cannot be deleted.')">@csrf @method('DELETE')<button class="btn secondary">Delete</button></form></div>@else—@endif</td></tr>
        @empty<tr><td colspan="5">No ingredients have been created.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</x-layouts.app>
