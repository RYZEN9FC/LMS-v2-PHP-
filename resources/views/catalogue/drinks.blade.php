<x-layouts.app title="Drinks and cocktails">
    <div class="page-head"><div><h1>Drinks & cocktails</h1><p class="sub">Set the alcohol ingredients consumed for one serving.</p></div></div>
    @include('catalogue.feedback')
    <section class="card"><div class="pad"><h2>{{ $editing ? 'Edit drink' : 'Add drink' }}</h2></div>
    @if($brands->isEmpty())<div class="pad">Add a brand before creating a drink. <a href="{{ route('brands.index') }}">Open Brands</a></div>
    @else
    <form class="pad catalogue-form" method="post" action="{{ $editing ? route('drinks.update', $editing->id) : route('drinks.store') }}">
        @csrf @if($editing) @method('PUT') @endif
        <label>Drink name<input name="name" maxlength="255" required value="{{ old('name', $editing?->name) }}"></label>
        <div class="recipe-editor"><h2>Ingredients per serving</h2>
            <div id="ingredients">
            @php($rows = old('ingredients', $editing ? $ingredients->get($editing->id, collect())->map(fn($i) => ['product_id' => $i->product_id, 'volume_ml' => $i->volume_ml])->all() : [['product_id' => '', 'volume_ml' => 30]]))
            @foreach($rows as $index => $ingredient)
                <div class="ingredient-row">
                    <label>Brand<select name="ingredients[{{ $index }}][product_id]" required><option value="">Select brand</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected($ingredient['product_id'] == $brand->id)>{{ $brand->name }}</option>@endforeach</select></label>
                    <label>Quantity (ml)<input name="ingredients[{{ $index }}][volume_ml]" type="number" min="0.01" max="100000" step="0.01" required value="{{ $ingredient['volume_ml'] }}"></label>
                    <button type="button" class="btn secondary" onclick="removeIngredient(this)">Remove</button>
                </div>
            @endforeach
            </div>
            <button type="button" class="btn secondary" onclick="addIngredient()">Add ingredient</button>
            <p class="note">Combine repeated uses of the same brand into one ingredient. Recipe edits affect future imports; recorded consumption remains unchanged.</p>
        </div>
        <div class="actions"><button class="btn">Save drink</button>@if($editing)<a class="btn secondary" href="{{ route('drinks.index') }}">Cancel</a>@endif</div>
    </form>
    @endif
    </section>
    <section class="card" style="margin-top:24px"><div class="pad"><h2>{{ $drinks->count() }} drinks</h2><input type="search" placeholder="Search drinks" aria-label="Search drinks" oninput="filterCatalogue(this.value)"></div>
    <div class="table-wrap"><table><thead><tr><th>Drink</th><th>Recipe per serving</th><th>Actions</th></tr></thead><tbody>
    @forelse($drinks as $drink)
        <tr data-catalogue-row><td>{{ $drink->name }}</td><td>@foreach($ingredients->get($drink->id, collect()) as $ingredient)<div>{{ $ingredient->name }} — {{ (float) $ingredient->volume_ml }} ml</div>@endforeach</td><td><div class="actions">
            <a class="btn secondary" href="{{ route('drinks.index', ['edit' => $drink->id]) }}">Edit</a>
            <form method="post" action="{{ route('drinks.destroy', $drink->id) }}" onsubmit="return confirm('Delete this drink and its recipe?')">@csrf @method('DELETE')<button class="btn secondary">Delete</button></form>
        </div></td></tr>
    @empty <tr><td colspan="3">Add your first drink above.</td></tr> @endforelse
    </tbody></table></div></section>
    <script>
    let ingredientIndex = {{ count($rows ?? []) }};
    function addIngredient() {
        const row = document.querySelector('.ingredient-row').cloneNode(true);
        row.querySelectorAll('input,select').forEach(input => { input.name = input.name.replace(/\[\d+\]/, '[' + ingredientIndex + ']'); input.value = ''; });
        ingredientIndex++;
        document.getElementById('ingredients').append(row);
    }
    function removeIngredient(button) {
        if (document.querySelectorAll('.ingredient-row').length > 1) button.closest('.ingredient-row').remove();
    }
    </script>
</x-layouts.app>
