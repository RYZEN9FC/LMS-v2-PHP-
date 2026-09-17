<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ExcisePreviewParser;
use App\Services\PosPreviewParser;
use App\Services\ReviewedImportService;
use App\Support\CurrentOutlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public function __construct(private readonly CurrentOutlet $currentOutlet) {}

    public function pos(Request $request, PosPreviewParser $parser, ReviewedImportService $imports)
    {
        return $this->preview($request, $parser, $imports, 'report', 'xlsx,xls', 'pos');
    }

    public function excise(Request $request, ExcisePreviewParser $parser, ReviewedImportService $imports)
    {
        return $this->preview($request, $parser, $imports, 'indent', 'pdf', 'excise');
    }

    public function review(Request $request, ReviewedImportService $imports)
    {
        $data = $request->validate(['document_id' => ['required', 'uuid'], 'source' => ['required', 'in:pos,excise'],
            'row_ids' => ['required', 'array', 'min:1', 'max:1000'], 'row_ids.*' => ['required', 'integer', 'distinct']]);
        $result = $imports->review($this->outlet(), $data['document_id'], $data['source'], $data['row_ids']);
        unset($result['plans']);

        return response()->json($result);
    }

    public function saveMapping(Request $request, ReviewedImportService $imports)
    {
        $data = $request->validate(['document_id' => ['required', 'uuid'], 'row_id' => ['required', 'integer'],
            'kind' => ['required', 'in:bottle,measured,recipe,ignore'], 'product_id' => ['nullable', 'integer'], 'recipe_id' => ['nullable', 'integer'],
            'serving_ml' => ['nullable', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'], 'bottles_per_sale' => ['required', 'integer', 'between:1,100'],
            'confirm_change' => ['sometimes', 'boolean']]);
        $rules = $imports->saveMapping($this->outlet(), $data['document_id'], $data['row_id'], $data);

        return response()->json(['message' => 'Mapping and serving rule saved. Stock has not changed.', 'rules' => $rules]);
    }

    public function applyPos(Request $request, ReviewedImportService $imports)
    {
        return $this->apply($request, $imports, 'pos');
    }

    public function applyExcise(Request $request, ReviewedImportService $imports)
    {
        return $this->apply($request, $imports, 'excise');
    }

    public function history(Request $request)
    {
        $imports = DB::table('imports')->where('outlet_id', $this->outlet())->orderByDesc('id')->paginate(30);
        $documents = DB::table('upload_documents')->where('outlet_id', $this->outlet())->orderByDesc('created_at')->limit(30)->get();

        return view('uploads.history', compact('imports', 'documents'));
    }

    private function apply(Request $request, ReviewedImportService $imports, string $source)
    {
        $data = $request->validate(['document_id' => ['required', 'uuid'], 'review_key' => ['required', 'string', 'size:64'],
            'row_ids' => ['required', 'array', 'min:1', 'max:1000'], 'row_ids.*' => ['required', 'integer', 'distinct']]);

        return response()->json($imports->apply($this->outlet(), $data['document_id'], $source, $data['row_ids'], $data['review_key']));
    }

    private function preview(Request $request, object $parser, ReviewedImportService $imports, string $field, string $types, string $source)
    {
        $outlet = $this->outlet();
        $preview = null;
        if ($request->isMethod('post')) {
            $request->validate([$field => ['required', 'file', 'mimes:'.$types, 'max:15360']]);
            try {
                $parsed = $parser->parse($request->file($field)->getRealPath());
                $id = $imports->store($outlet, $source, $parsed, $request->file($field)->getClientOriginalName());

                $excluded = $source === 'pos' ? ' '.($parsed['excluded_non_liquor'] ?? 0).' non-liquor rows excluded.' : '';

                return redirect()->route('uploads.'.$source, ['document' => $id])->with('uploadNotice', 'Upload read successfully. '.count($parsed['lines']).($source === 'excise' ? ' indent lines read.' : ' liquor POS items read.').$excluded.' Preview is ready; stock has not changed.');
            } catch (ValidationException $error) {
                throw $error;
            } catch (\Throwable $error) {
                report($error);
                throw ValidationException::withMessages([$field => 'The original file could not be read safely. No stock was changed. Check the file format and try again.']);
            }
        }
        if ($request->filled('document')) {
            $request->validate(['document' => ['uuid']]);
            $preview = $imports->preview($outlet, $request->input('document'), $source);
        }
        $brands = Product::where('outlet_id', $outlet)->where('is_active', true)->orderBy('spirit_type')->orderBy('name')->get(['id', 'name', 'spirit_type', 'bottle_size_ml']);
        $recipes = DB::table('recipes')->where('outlet_id', $outlet)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('uploads.'.$source, compact('preview', 'brands', 'recipes'));
    }

    private function outlet(): int
    {
        return $this->currentOutlet->id();
    }
}
