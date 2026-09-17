<x-layouts.app title="Brands">
    <div class="page-head"><div><h1>Brands</h1><p class="sub">Manage bottle sizes, excise codes and starting quantities.</p></div></div>
    @include('catalogue.feedback')
    <section class="card">
        <div class="pad"><h2>{{ $editing ? 'Edit brand' : 'Add brand' }}</h2></div>
        <form class="pad catalogue-form" method="post" action="{{ $editing ? route('brands.update', $editing->id) : route('brands.store') }}">
            @csrf @if($editing) @method('PUT') @endif
            <label>Brand name<input name="name" required maxlength="255" value="{{ old('name', $editing?->name) }}"></label>
            <label>Spirit type<select name="spirit_type" required>@foreach($spiritTypes as $type)<option value="{{ $type }}" @selected(old('spirit_type', $editing?->spirit_type ?? 'Other') === $type)>{{ $type }}</option>@endforeach</select></label>
            <label>Bottle size (ml)<input name="bottle_size_ml" type="number" step="1" inputmode="numeric" min="1" max="100000" required value="{{ old('bottle_size_ml', $editing ? (int) round((float) $editing->bottle_size_ml) : 750) }}"></label>
            <label>Excise code<input name="excise_code" maxlength="60" value="{{ old('excise_code', $editing?->excise_code) }}"></label>
            @if(!$locked)
                <label>Opening bottle cost (optional)<input name="opening_bottle_price" type="number" min="0" step=".01" value="{{ old('opening_bottle_price', $opening?->receipt_price_per_ml !== null ? round($opening->value_change / $opening->volume_ml * $editing->bottle_size_ml, 2) : '') }}" placeholder="Unknown if blank"></label>
                <label>Opening date<input name="opening_date" type="date" required value="{{ old('opening_date', $opening?->effective_date?->format('Y-m-d') ?? now()->toDateString()) }}"></label>
                <label>Opening whole bottles<input name="opening_bottles" type="number" min="0" required value="{{ old('opening_bottles', $opening ? floor($opening->volume_ml / $editing->bottle_size_ml) : 0) }}"></label>
                <label>Additional ml<input name="opening_ml" type="number" step="0.01" min="0" required value="{{ old('opening_ml', $opening ? fmod($opening->volume_ml, $editing->bottle_size_ml) : 0) }}"></label>
            @endif
            <div class="actions"><button class="btn">Save brand</button>@if($editing)<a class="btn secondary" href="{{ route('brands.index') }}">Cancel</a>@endif</div>
            <p class="note">{{ $locked ? 'Opening stock is locked because this brand has subsequent receipts or sales.' : 'Opening stock is the quantity at the start of the selected day. Additional ml must be less than one bottle.' }} Purchase prices come from indents.</p>
        </form>
    </section>
    <section class="card" style="margin-top:24px"><div class="pad"><h2>{{ $brands->count() }} brands</h2><input type="search" placeholder="Search brands" aria-label="Search brands" oninput="filterCatalogue(this.value)"></div>
        <div class="table-wrap"><table class="brand-table" data-spirit-filter><colgroup><col class="brand-name-column"><col class="brand-type-column"><col class="brand-size-column"><col class="brand-code-column"><col class="brand-actions-column"></colgroup><thead><tr><th>Brand</th><th>Spirit type</th><th>Bottle size</th><th>Excise code</th><th>Actions</th></tr></thead><tbody>
        @forelse($brands as $brand)
            <tr data-catalogue-row data-spirit-type="{{ $brand->spirit_type }}"><td>{{ $brand->name }}</td><td>{{ $brand->spirit_type }}</td><td>{{ number_format($brand->bottle_size_ml) }} ml</td><td>{{ $brand->excise_code ?? '—' }}</td><td><div class="actions">
                <a class="btn secondary" href="{{ route('brands.index', ['edit' => $brand->id]) }}">Edit</a>
                <form method="post" action="{{ route('brands.destroy', $brand->id) }}" onsubmit="return confirm('Delete this brand? Brands with history or recipes cannot be deleted.')">@csrf @method('DELETE')<button class="btn secondary">Delete</button></form>
            </div></td></tr>
        @empty <tr><td colspan="5">Add your first brand above.</td></tr> @endforelse
        </tbody></table></div>
    </section>
</x-layouts.app>
