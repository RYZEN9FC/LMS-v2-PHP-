<x-layouts.app title="Food dishes and recipes">
    <div class="page-head"><div><h1>Dishes & recipes</h1><p class="sub">Define the ingredient quantity consumed by one dish or recipe yield.</p></div></div>
    @include('catalogue.feedback')
    @if(app(\App\Support\CurrentOutlet::class)->allows('food.catalogue.manage'))
    <section class="card"><div class="pad"><h2>{{ $editing ? 'New version of '.$editing->name : 'Add dish' }}</h2></div>
    @if($ingredients->isEmpty())<div class="pad">Add ingredients before creating a recipe. <a href="{{ route('food.ingredients.index') }}">Open Ingredients</a></div>
    @else
    @php($editingVersion = $editing ? $versions->get($editing->id) : null)
    @php($defaultRows = $editingVersion ? $recipeIngredients->get($editingVersion->id, collect())->map(fn($item)=>['ingredient_id'=>$item->food_ingredient_id,'quantity_base'=>$item->quantity_base])->all() : [['ingredient_id'=>'','quantity_base'=>'']])
    @php($rows = old('ingredients', $defaultRows))
    <form class="pad catalogue-form" method="post" action="{{ $editing ? route('food.recipes.update',$editing->id) : route('food.recipes.store') }}">@csrf @if($editing)@method('PUT')@endif
        <label>Dish name<input name="name" maxlength="255" required value="{{ old('name',$editing?->name) }}"></label>
        <label>Recipe yield<input name="yield_quantity" type="number" min="0.001" max="100000" step="0.001" required value="{{ old('yield_quantity',$editingVersion?->yield_quantity ?? 1) }}"></label>
        <label>Effective from<input name="effective_from" type="date" required value="{{ old('effective_from',now()->toDateString()) }}"></label>
        <div class="recipe-editor"><h2>Ingredients for this yield</h2><div id="food-recipe-ingredients">
        @foreach($rows as $index=>$row)<div class="ingredient-row food-ingredient-row"><label>Ingredient<select name="ingredients[{{ $index }}][ingredient_id]" required><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" data-unit="{{ $ingredient->base_unit }}" @selected(($row['ingredient_id']??'')==$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->base_unit }})</option>@endforeach</select></label><label>Quantity in base unit<input name="ingredients[{{ $index }}][quantity_base]" type="number" min="0.001" max="100000000" step="0.001" required value="{{ $row['quantity_base']??'' }}"></label><button type="button" class="btn secondary" onclick="removeFoodIngredient(this)">Remove</button></div>@endforeach
        </div><button type="button" class="btn secondary" onclick="addFoodIngredient()">Add ingredient</button><p class="note">Editing creates a new recipe version. Earlier POS consumption will retain the historical recipe and cost.</p></div>
        <div class="actions"><button class="btn">Save recipe version</button>@if($editing)<a class="btn secondary" href="{{ route('food.recipes.index') }}">Cancel</a>@endif</div>
    </form>
    @endif</section>
    @endif
    <section class="card" style="margin-top:24px"><div class="pad"><h2>{{ $recipes->count() }} dishes</h2><input type="search" placeholder="Search dishes" aria-label="Search dishes" oninput="filterCatalogue(this.value)"></div><div class="table-wrap"><table><thead><tr><th>Dish</th><th>Current recipe</th><th>Version</th><th>Actions</th></tr></thead><tbody>
    @forelse($recipes as $recipe)@php($version=$versions->get($recipe->id))<tr data-catalogue-row><td><strong>{{ $recipe->name }}</strong></td><td>@foreach($recipeIngredients->get($version?->id,collect()) as $ingredient)<div>{{ $ingredient->name }} — {{ (float)$ingredient->quantity_base }} {{ $ingredient->base_unit }}</div>@endforeach</td><td>{{ $version ? 'v'.$version->version : '—' }}</td><td>@if(app(\App\Support\CurrentOutlet::class)->allows('food.catalogue.manage'))<div class="actions"><a class="btn secondary" href="{{ route('food.recipes.index',['edit'=>$recipe->id]) }}">Edit</a><form method="post" action="{{ route('food.recipes.destroy',$recipe->id) }}" onsubmit="return confirm('Archive this dish? Historical recipes will remain.')">@csrf @method('DELETE')<button class="btn secondary">Archive</button></form></div>@else—@endif</td></tr>
    @empty<tr><td colspan="4">No dish recipes have been created.</td></tr>@endforelse
    </tbody></table></div></section>
    @if(isset($rows))<script>let foodIngredientIndex={{ count($rows) }};function addFoodIngredient(){const source=document.querySelector('.food-ingredient-row'),row=source.cloneNode(true);row.querySelectorAll('input,select').forEach(input=>{input.name=input.name.replace(/\[\d+\]/,'['+foodIngredientIndex+']');input.value='';});foodIngredientIndex++;document.getElementById('food-recipe-ingredients').append(row);}function removeFoodIngredient(button){if(document.querySelectorAll('.food-ingredient-row').length>1)button.closest('.food-ingredient-row').remove();}</script>@endif
</x-layouts.app>
