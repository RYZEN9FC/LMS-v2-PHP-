<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\ProductNameMatcher;
use App\Support\CurrentOutlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogueController extends Controller
{
    private const SPIRIT_TYPES = ['Beer', 'Vodka', 'Whisky', 'Gin', 'Rum', 'Tequila', 'Wine', 'Liqueur', 'Alcopop', 'Brandy', 'Other'];

    public function __construct(private readonly CurrentOutlet $currentOutlet) {}

    private function outlet(): int
    {
        return $this->currentOutlet->id();
    }

    public function brands(Request $request)
    {
        $outlet = $this->outlet();
        $brands = Product::where('outlet_id', $outlet)->orderBy('name')->get();
        $editing = $request->filled('edit') ? Product::where('outlet_id', $outlet)->findOrFail($request->input('edit')) : null;
        $opening = $editing?->movements()->where('movement_type', 'opening')->first();
        $locked = $editing && $editing->movements()->where('movement_type', '!=', 'opening')->exists();
        $spiritTypes = self::SPIRIT_TYPES;

        return view('catalogue.brands', compact('brands', 'editing', 'opening', 'locked', 'spiritTypes'));
    }

    public function saveBrand(Request $request, ?int $id = null)
    {
        $request->mergeIfMissing(['spirit_type' => 'Other']);
        // Bottle sizes are always whole millilitres. Convert legacy/display values
        // such as "750.00" so an edit does not fail just because the DB column is decimal.
        $submittedSize = $request->input('bottle_size_ml');
        if (is_numeric($submittedSize) && (float) $submittedSize === floor((float) $submittedSize)) {
            $request->merge(['bottle_size_ml' => (string) (int) $submittedSize]);
        }
        $outlet = $this->outlet();
        $brand = $id ? Product::where('outlet_id', $outlet)->findOrFail($id) : new Product(['outlet_id' => $outlet]);
        $locked = $id && $brand->movements()->where('movement_type', '!=', 'opening')->exists();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('products')->where('outlet_id', $outlet)->where('bottle_size_ml', $request->input('bottle_size_ml'))->ignore($id)],
            'spirit_type' => ['required', Rule::in(self::SPIRIT_TYPES)],
            'excise_code' => ['nullable', 'string', 'max:60', Rule::unique('products')->where('outlet_id', $outlet)->ignore($id)],
            'bottle_size_ml' => ['required', 'integer', 'between:1,100000'],
            'opening_bottles' => [$locked ? 'nullable' : 'required', 'integer', 'between:0,100000'],
            'opening_ml' => [$locked ? 'nullable' : 'required', 'numeric', 'min:0', 'lt:bottle_size_ml', 'decimal:0,2'],
            'opening_date' => [$locked ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'opening_bottle_price' => ['nullable', 'numeric', 'between:0,100000000', 'decimal:0,2'],
        ]);
        if ($id && (float) $data['bottle_size_ml'] !== (float) $brand->bottle_size_ml
            && ($locked || DB::table('recipe_ingredients')->where('product_id', $id)->exists() || DB::table('source_mappings')->where('product_id', $id)->exists())) {
            throw ValidationException::withMessages(['bottle_size_ml' => 'This bottle size is already used in stock history or recipes. Add a separate brand entry for a different size.']);
        }
        DB::transaction(function () use ($data, $brand, $locked) {
            Outlet::whereKey($brand->outlet_id)->lockForUpdate()->firstOrFail();
            if ($brand->exists) {
                $brand->refresh();
                $locked = $brand->movements()->where('movement_type', '!=', 'opening')->exists();
                if ((float) $data['bottle_size_ml'] !== (float) $brand->bottle_size_ml
                    && ($locked || DB::table('recipe_ingredients')->where('product_id', $brand->id)->exists() || DB::table('source_mappings')->where('product_id', $brand->id)->exists())) {
                    throw ValidationException::withMessages(['bottle_size_ml' => 'This bottle size is now in use. Add a separate brand for a different size.']);
                }
            }
            $brand->fill(collect($data)->only(['name', 'spirit_type', 'excise_code', 'bottle_size_ml'])->all())->save();
            if (! $locked) {
                $volume = $data['opening_bottles'] * $data['bottle_size_ml'] + $data['opening_ml'];
                if ($volume == 0) {
                    $brand->movements()->where('movement_type', 'opening')->delete();

                    return;
                }
                $brand->movements()->updateOrCreate(['movement_type' => 'opening'], [
                    'outlet_id' => $brand->outlet_id, 'effective_date' => $data['opening_date'],
                    'volume_ml' => $volume, 'reference' => 'Opening stock setup',
                    'receipt_price_per_ml' => isset($data['opening_bottle_price']) ? $data['opening_bottle_price'] / $data['bottle_size_ml'] : null,
                    'value_change' => isset($data['opening_bottle_price']) ? round($volume * $data['opening_bottle_price'] / $data['bottle_size_ml'], 2) : 0,
                ]);
            }
        });
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Brand saved.', 'brand' => $brand->only(['id', 'name', 'spirit_type', 'bottle_size_ml'])]);
        }

        return redirect()->route('brands.index')->with('status', 'Brand saved.');
    }

    public function deleteBrand(int $id)
    {
        $outlet = $this->outlet();

        return DB::transaction(function () use ($outlet, $id) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            $brand = Product::where('outlet_id', $outlet)->findOrFail($id);
            if ($brand->movements()->exists() || DB::table('recipe_ingredients')->where('product_id', $id)->exists()
                || DB::table('import_lines')->where('product_id', $id)->exists() || DB::table('source_mappings')->where('product_id', $id)->exists()) {
                return back()->withErrors(['delete' => 'This brand has stock history or is used in a recipe/import and cannot be deleted.']);
            }
            $brand->delete();

            return redirect()->route('brands.index')->with('status', 'Brand deleted.');
        });
    }

    public function drinks(Request $request)
    {
        $outlet = $this->outlet();
        $drinks = DB::table('recipes')->where('outlet_id', $outlet)->orderBy('name')->get();
        $brands = Product::where('outlet_id', $outlet)->orderBy('spirit_type')->orderBy('name')->get();
        $ingredients = DB::table('recipe_ingredients')->join('products', 'products.id', '=', 'recipe_ingredients.product_id')
            ->where('products.outlet_id', $outlet)->select('recipe_ingredients.*', 'products.name')->get()->groupBy('recipe_id');
        $editing = $request->filled('edit') ? $drinks->firstWhere('id', (int) $request->input('edit')) : null;
        abort_if($request->filled('edit') && ! $editing, 404);

        return view('catalogue.drinks', compact('drinks', 'brands', 'ingredients', 'editing'));
    }

    public function saveDrink(Request $request, ?int $id = null)
    {
        $outlet = $this->outlet();
        if ($id) {
            abort_unless(DB::table('recipes')->where('outlet_id', $outlet)->where('id', $id)->exists(), 404);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('recipes')->where('outlet_id', $outlet)->ignore($id)],
            'ingredients' => ['required', 'array', 'min:1', 'max:50'],
            'ingredients.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('outlet_id', $outlet)],
            'ingredients.*.volume_ml' => ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
        ]);
        DB::transaction(function () use ($data, $outlet, $id) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            if ($id) {
                DB::table('recipes')->where('id', $id)->update(['name' => $data['name'], 'updated_at' => now()]);
                DB::table('recipe_ingredients')->where('recipe_id', $id)->delete();
            } else {
                $id = DB::table('recipes')->insertGetId(['name' => $data['name'], 'outlet_id' => $outlet, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($data['ingredients'] as $ingredient) {
                DB::table('recipe_ingredients')->insert(['recipe_id' => $id, 'product_id' => $ingredient['product_id'], 'volume_ml' => $ingredient['volume_ml'], 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return redirect()->route('drinks.index')->with('status', 'Drink saved. Recipe changes apply to future imports.');
    }

    public function deleteDrink(int $id)
    {
        $outlet = $this->outlet();

        return DB::transaction(function () use ($outlet, $id) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            $query = DB::table('recipes')->where('outlet_id', $outlet)->where('id', $id);
            abort_unless($query->exists(), 404);
            if (DB::table('import_lines')->where('recipe_id', $id)->exists() || DB::table('source_mappings')->where('recipe_id', $id)->exists()) {
                return back()->withErrors(['delete' => 'This drink is used in import history and cannot be deleted.']);
            }
            $query->delete();

            return redirect()->route('drinks.index')->with('status', 'Drink deleted.');
        });
    }

    public function mappings()
    {
        $outlet = $this->outlet();
        $brands = Product::where('outlet_id', $outlet)->orderBy('name')->get();
        $legacyMappings = ProductAlias::with('product')->where('outlet_id', $outlet)->latest()->get();
        $mappings = DB::table('source_mappings as m')->leftJoin('products as p', 'p.id', '=', 'm.product_id')->leftJoin('recipes as r', 'r.id', '=', 'm.recipe_id')
            ->where('m.outlet_id', $outlet)->where('m.is_active', true)->select('m.*', 'p.name as product_name', 'p.spirit_type', 'p.bottle_size_ml', 'r.name as recipe_name')->orderBy('m.source_name')->get();

        return view('catalogue.mappings', compact('brands', 'mappings', 'legacyMappings'));
    }

    public function saveMapping(Request $request)
    {
        $outlet = $this->outlet();
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('outlet_id', $outlet)],
            'source' => ['required', Rule::in(['pos', 'excise'])],
            'source_name' => ['required', 'string', 'max:255'],
        ]);
        $normalised = ProductNameMatcher::normalise($data['source_name']);
        if ($normalised === '') {
            throw ValidationException::withMessages(['source_name' => 'Enter a usable source product name.']);
        }
        $existing = ProductAlias::where(['outlet_id' => $outlet, 'source' => $data['source'], 'normalised_name' => $normalised])->first();
        if ($existing && $existing->product_id !== (int) $data['product_id']) {
            throw ValidationException::withMessages(['source_name' => 'This source name is already mapped to another common brand.']);
        }
        ProductAlias::updateOrCreate(
            ['outlet_id' => $outlet, 'source' => $data['source'], 'normalised_name' => $normalised],
            ['product_id' => $data['product_id'], 'source_name' => trim($data['source_name'])]
        );
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Product mapping saved.', 'product' => Product::find($data['product_id'])->only(['id', 'name'])]);
        }

        return redirect()->route('mappings.index')->with('status', 'Product mapping saved. Future previews will resolve this name to the selected common brand.');
    }

    public function deleteMapping(int $id)
    {
        ProductAlias::where('outlet_id', $this->outlet())->findOrFail($id)->delete();

        return redirect()->route('mappings.index')->with('status', 'Product mapping deleted.');
    }

    public function deleteReviewedMapping(int $id)
    {
        $outlet = $this->outlet();
        DB::transaction(function () use ($outlet, $id) {
            Outlet::whereKey($outlet)->lockForUpdate()->firstOrFail();
            $mapping = DB::table('source_mappings')->where('outlet_id', $outlet)->find($id);
            abort_unless($mapping, 404);
            DB::table('source_mappings')->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);
            DB::table('mapping_changes')->insert(['source_mapping_id' => $id, 'before_rules' => $mapping->rules, 'after_rules' => json_encode(['disabled' => true]), 'created_at' => now(), 'updated_at' => now()]);
            $documents = DB::table('upload_documents')->where('outlet_id', $outlet)->where('source', $mapping->source)->pluck('id');
            DB::table('upload_rows')->whereIn('document_id', $documents)->where('source_key', $mapping->source_key)->whereNull('applied_import_id')->update(['mapping' => null]);
        });

        return redirect()->route('mappings.index')->with('status', 'Mapping removed from future uploads and unsubmitted previews. Posted history is unchanged.');
    }
}
