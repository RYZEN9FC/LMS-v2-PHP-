<div class="mapping-control" data-row-id="{{ $item['row_id'] }}" data-source="{{ $source }}" data-source-name="{{ $item['name'] }}" data-bottle-size="{{ $item['size_ml'] ?? '' }}" data-excise-code="{{ $item['code'] ?? '' }}" data-rules="{{ json_encode($item['rules']) }}" data-applied="{{ $item['applied'] ? '1' : '0' }}">
    <div class="mapping-line">
        <select aria-label="Spirit type for {{ $item['name'] }}" class="mapping-type"><option value="">Choose type</option>@foreach($brands->pluck('spirit_type')->unique()->sort() as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select>
        <select aria-label="Common brand for {{ $item['name'] }}" class="mapping-select"></select>
    </div>
    @if($source === 'pos')
    <div class="mapping-line serving-line">
        <select class="mapping-kind" aria-label="Sale conversion"><option value="">Choose sale conversion</option><option value="bottle">Bottle / bucket</option><option value="measured">Measured ml / peg</option><option value="recipe">Cocktail recipe</option><option value="ignore">Ignore — no alcohol stock</option></select>
        <label class="bottle-rule">Bottles per sale <input class="mapping-multiplier" type="number" min="1" max="100" step="1" value="1"></label>
        <label class="measured-rule">ml per sale <input class="mapping-ml" type="number" min=".01" max="100000" step=".01"></label>
        <select class="mapping-recipe" aria-label="Saved cocktail"><option value="">Choose a drink</option>@foreach($recipes as $recipe)<option value="{{ $recipe->id }}">{{ $recipe->name }}</option>@endforeach</select>
    </div>
    @endif
    <div class="mapping-line"><button type="button" class="btn secondary save-mapping">Save mapping</button><span class="mapping-status" role="status"></span></div>
</div>
