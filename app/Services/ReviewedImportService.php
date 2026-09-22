<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewedImportService
{
    public static function sourceKey(string $source, array $line): string
    {
        $identity = $source === 'excise' ? strtoupper($line['code']).'|'.$line['size_ml'] : mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $line['name'])));

        return hash('sha256', $identity);
    }

    public function store(int $outletId, string $source, array $preview, string $file): string
    {
        $from = $source === 'excise' ? Carbon::createFromFormat('!d-M-y', $preview['date'])->toDateString() : $preview['from'];
        $to = $source === 'excise' ? $from : $preview['to'];
        $lines = $preview['lines'];
        $canonical = $lines;
        usort($canonical, fn ($a, $b) => self::sourceKey($source, $a) <=> self::sourceKey($source, $b));
        $contentHash = hash('sha256', json_encode([$from, $to, $canonical], JSON_PRESERVE_ZERO_FRACTION));
        $key = $source === 'excise' ? hash('sha256', strtoupper($preview['indent_number'])) : $contentHash;

        return DB::transaction(function () use ($outletId, $source, $preview, $file, $from, $to, $contentHash, $key, $lines) {
            Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
            $existing = DB::table('upload_documents')->where(['outlet_id' => $outletId, 'source' => $source, 'document_key' => $key])->first();
            if ($existing) {
                if ($existing->content_hash !== $contentHash) {
                    $this->fail('This indent number already exists with different contents. Resolve the original import before replacing it.');
                }

                return $existing->id;
            }
            $id = (string) Str::uuid();
            $metadata = $preview;
            unset($metadata['lines']);
            DB::table('upload_documents')->insert(['id' => $id, 'outlet_id' => $outletId, 'source' => $source, 'document_key' => $key, 'content_hash' => $contentHash,
                'file_name' => $file, 'effective_from' => $from, 'effective_to' => $to, 'granularity' => $preview['granularity'] ?? 'daily',
                'metadata' => json_encode($metadata), 'created_at' => now(), 'updated_at' => now()]);
            $mappings = DB::table('source_mappings')->where(['outlet_id' => $outletId, 'source' => $source, 'is_active' => true])->get()->keyBy('source_key');
            foreach ($lines as $index => $line) {
                $sourceKey = self::sourceKey($source, $line);
                DB::table('upload_rows')->insert(['document_id' => $id, 'source_key' => $sourceKey, 'row_key' => hash('sha256', $source === 'excise' ? (string) $line['number'] : $sourceKey),
                    'payload' => json_encode($line), 'mapping' => $mappings->get($sourceKey)?->rules, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
    }

    public function preview(int $outletId, string $id, string $source): array
    {
        $document = $this->document($outletId, $id, $source);
        $preview = json_decode($document->metadata, true);
        $preview['file'] = $document->file_name;
        $preview['document_id'] = $id;
        $preview['lines'] = DB::table('upload_rows')->where('document_id', $id)->orderBy('id')->get()->map(function ($row) {
            $line = json_decode($row->payload, true);
            $rules = $row->mapping ? json_decode($row->mapping, true) : null;

            return $line + ['row_id' => $row->id, 'rules' => $rules, 'applied' => $row->applied_import_id !== null, 'common_product_id' => $rules['product_id'] ?? null];
        })->all();
        $preview['mapped_items'] = count(array_filter($preview['lines'], fn ($line) => $line['rules'] !== null));

        return $preview;
    }

    public function saveMapping(int $outletId, string $documentId, int $rowId, array $input): array
    {
        return DB::transaction(function () use ($outletId, $documentId, $rowId, $input) {
            Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
            $document = $this->document($outletId, $documentId);
            $row = DB::table('upload_rows')->where('document_id', $documentId)->where('id', $rowId)->first();
            if (! $row || $row->applied_import_id) {
                $this->fail('This row is missing or already submitted. Posted history cannot be remapped.');
            }
            $line = json_decode($row->payload, true);
            $kind = $input['kind'];
            if ($document->source === 'excise' && $kind !== 'bottle') {
                $this->fail('Indent rows must map to a stocked bottle.');
            }
            $rules = ['kind' => $kind, 'product_id' => null, 'recipe_id' => null, 'serving_ml' => null, 'bottles_per_sale' => 1];
            if (in_array($kind, ['bottle', 'measured'], true)) {
                $product = Product::where('outlet_id', $outletId)->where('is_active', true)->find($input['product_id'] ?? 0);
                if (! $product) {
                    $this->fail('Choose an existing active brand.');
                }
                if ($document->source === 'excise' && (float) $product->bottle_size_ml !== (float) $line['size_ml']) {
                    $this->fail('Indent bottle volume does not match the selected brand. Add its correct bottle-size entry.');
                }
                if ($document->source === 'excise') {
                    foreach (DB::table('upload_rows')->where('document_id', $documentId)->where('id', '!=', $rowId)->whereNotNull('mapping')->get() as $other) {
                        $otherRules = json_decode($other->mapping, true);
                        if (($otherRules['product_id'] ?? null) === $product->id && $other->source_key !== $row->source_key) {
                            $this->fail('Another excise code in this indent already uses that brand. Review the product identity.');
                        }
                    }
                }
                $rules['product_id'] = $product->id;
                $rules['bottles_per_sale'] = $document->source === 'excise' ? 1 : (int) ($input['bottles_per_sale'] ?? 1);
                $rules['serving_ml'] = $kind === 'measured' ? (float) ($input['serving_ml'] ?? 0) : null;
                if ($kind === 'measured' && ($rules['serving_ml'] <= 0 || $rules['serving_ml'] > 100000)) {
                    $this->fail('Enter the actual millilitres consumed per serving.');
                }
            } elseif ($kind === 'recipe') {
                $recipe = DB::table('recipes')->where('outlet_id', $outletId)->where('is_active', true)->find($input['recipe_id'] ?? 0);
                if (! $recipe || ! DB::table('recipe_ingredients')->where('recipe_id', $recipe->id)->exists()) {
                    $this->fail('Choose a saved drink with at least one ingredient.');
                }
                $rules['recipe_id'] = $recipe->id;
            }
            $existing = DB::table('source_mappings')->where(['outlet_id' => $outletId, 'source' => $document->source, 'source_key' => $row->source_key])->first();
            $encoded = json_encode($rules);
            if ($existing && json_decode($existing->rules, true) != $rules && empty($input['confirm_change'])) {
                $this->fail('This item already has a saved mapping. Confirm the correction before changing it.');
            }
            if ($existing) {
                $mappingId = $existing->id;
                DB::table('source_mappings')->where('id', $mappingId)->update(['is_active' => true, 'rules' => $encoded, 'product_id' => $rules['product_id'], 'recipe_id' => $rules['recipe_id'], 'updated_at' => now()]);
            } else {
                $mappingId = DB::table('source_mappings')->insertGetId(['outlet_id' => $outletId, 'source' => $document->source, 'source_key' => $row->source_key,
                    'source_name' => $line['name'], 'product_id' => $rules['product_id'], 'recipe_id' => $rules['recipe_id'], 'rules' => $encoded, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('mapping_changes')->insert(['source_mapping_id' => $mappingId, 'before_rules' => $existing?->rules, 'after_rules' => $encoded, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('upload_rows')->where('id', $rowId)->update(['mapping' => $encoded, 'updated_at' => now()]);

            return $rules;
        });
    }

    public function review(int $outletId, string $id, string $source, array $rowIds): array
    {
        $document = $this->document($outletId, $id, $source);
        $rows = DB::table('upload_rows')->where('document_id', $id)->whereIn('id', $rowIds)->orderBy('id')->get();
        if (count($rowIds) !== count(array_unique($rowIds)) || $rows->count() !== count($rowIds)) {
            $this->fail('Select each original document row only once.');
        }
        $plans = [];
        $summary = [];
        $ignored = 0;
        $legacyImports = DB::table('imports')->where('outlet_id', $outletId)->where('source_type', $source)->where('status', 'applied')->get()
            ->filter(fn ($import) => ! isset(json_decode($import->metadata ?? '{}', true)['document_id']));
        $legacyNames = DB::table('import_lines')->whereIn('import_id', $legacyImports->pluck('id'))->get(['import_id', 'source_item_name'])->groupBy('import_id');
        $documentMetadata = json_decode($document->metadata, true);
        $partsCache = [];
        foreach ($rows as $row) {
            if ($row->applied_import_id) {
                $this->fail('One or more selected rows were already submitted. They cannot change stock twice.');
            }
            if (! $row->mapping) {
                $this->fail('Every selected row must have a saved mapping.');
            }
            $line = json_decode($row->payload, true);
            $rules = json_decode($row->mapping, true);
            $parts = $partsCache[$row->mapping] ??= $this->parts($outletId, $rules);
            foreach ($legacyImports as $legacy) {
                $legacyMetadata = json_decode($legacy->metadata ?? '{}', true);
                if (isset($legacyMetadata['document_id'])) {
                    continue;
                }
                if ($source === 'excise' && ($legacyMetadata['indent_number'] ?? null) === ($documentMetadata['indent_number'] ?? null)) {
                    $this->fail('This indent was submitted by the previous importer (#'.$legacy->id.'). Its old line identities cannot be safely reapplied. Review that history first.');
                }
                if ($source === 'pos' && $legacy->effective_to >= $document->effective_from && $legacy->effective_from <= $document->effective_to) {
                    $names = $legacyNames->get($legacy->id, collect())->pluck('source_item_name');
                    if ($names->contains(fn ($name) => self::sourceKey('pos', ['name' => $name]) === $row->source_key)) {
                        $this->fail('This POS item overlaps a submission made by the previous importer (#'.$legacy->id.'). No stock was changed.');
                    }
                }
            }
            if ($source === 'excise' && (! $parts || $parts[0]['size_ml'] !== (float) $line['size_ml'])) {
                $this->fail('The saved indent mapping has a different bottle size. Correct and save the mapping.');
            }
            if ($source === 'pos') {
                $overlap = DB::table('upload_rows as r')->join('upload_documents as d', 'd.id', '=', 'r.document_id')
                    ->where('d.outlet_id', $outletId)->where('d.source', 'pos')->where('r.source_key', $row->source_key)
                    ->whereNotNull('r.applied_import_id')->where('d.effective_from', '<=', $document->effective_to)->where('d.effective_to', '>=', $document->effective_from)->exists();
                if ($overlap) {
                    $this->fail('An overlapping POS period already contains '.$line['name'].'. Use non-overlapping reports; summary and customer exports must not count the same sales twice.');
                }
            }
            if ($rules['kind'] === 'ignore') {
                $ignored++;
            }
            $daily = $source === 'excise' ? [$document->effective_to => ['sold' => $line['bottles'], 'comp' => 0, 'nc' => 0, 'sold_amount' => $line['amount']]] : $line['daily'];
            foreach ($daily as $date => $figures) {
                $quantity = $figures['sold'] + $figures['comp'] + $figures['nc'];
                if ($rules['kind'] === 'bottle' && collect(['sold', 'comp', 'nc'])->contains(fn ($channel) => floor((float) $figures[$channel]) !== (float) $figures[$channel])) {
                    $this->fail('A bottle or bucket sale must contain a whole count. Check '.$line['name'].' or map it as measured ml.');
                }
                if ($quantity <= 0 || ! $parts) {
                    continue;
                }
                $movements = [];
                foreach ($parts as $part) {
                    $volume = $source === 'excise' ? $line['volume_ml'] : array_sum(array_map(fn ($channel) => round($figures[$channel] * $part['per_sale_ml'], 2), ['sold', 'comp', 'nc']));
                    if ($volume <= 0 || $volume > 9999999999) {
                        $this->fail('Calculated stock volume is zero or outside the supported range for '.$line['name'].'.');
                    }
                    $sign = $source === 'excise' ? 1 : -1;
                    $movements[] = ['product_id' => $part['id'], 'volume_ml' => $sign * $volume, 'sale_type' => $source === 'excise' ? null : $part['sale_type'],
                        'sale_quantity' => $source === 'excise' ? null : ($part['sale_type'] === 'full_bottle' ? $quantity * $rules['bottles_per_sale'] : $quantity)];
                    $summary[$part['id']] ??= ['name' => $part['name'], 'size_ml' => $part['size_ml'], 'change_ml' => 0, 'sold_ml' => 0, 'comp_ml' => 0, 'nc_ml' => 0];
                    $summary[$part['id']]['change_ml'] += $sign * $volume;
                    foreach (['sold', 'comp', 'nc'] as $channel) {
                        $summary[$part['id']][$channel.'_ml'] += $source === 'excise' ? 0 : round($figures[$channel] * $part['per_sale_ml'], 2);
                    }
                }
                $plans[] = ['row_id' => $row->id, 'source_name' => $line['name'], 'date' => $date, 'quantity' => $quantity, 'figures' => $figures,
                    'rules' => $rules, 'parts' => $parts, 'movements' => $movements, 'amount' => $source === 'excise' ? $line['amount'] : ($figures['sold_amount'] ?? 0)];
            }
        }
        if (! $plans && ! $ignored) {
            $this->fail('Selected rows contain no stock quantity. No import was applied.');
        }
        $balances = DB::table('stock_movements')->where('outlet_id', $outletId)->whereIn('product_id', array_keys($summary))
            ->selectRaw('product_id, SUM(volume_ml) as total')->groupBy('product_id')->pluck('total', 'product_id');
        foreach ($summary as $productId => &$value) {
            $value['before_ml'] = (float) ($balances[$productId] ?? 0);
            $value['after_ml'] = round($value['before_ml'] + $value['change_ml'], 2);
            if ($source === 'pos' && $value['after_ml'] < -0.001) {
                $this->fail('Insufficient total stock for '.$value['name'].'. This submission would leave '.$value['after_ml'].' ml. Add the missing receipts or correct the serving rule first.');
            }
        }
        unset($value);
        $hash = hash('sha256', json_encode([$id, $rowIds, $plans, $summary, $ignored]));

        return ['review_key' => $hash, 'summary' => array_values($summary), 'plans' => $plans, 'ignored' => $ignored, 'selected_rows' => $rows->count(),
            'period_notice' => $document->granularity === 'period' ? 'Period totals are recorded at the report end date. They are not daily sales.' : null];
    }

    public function apply(int $outletId, string $id, string $source, array $rowIds, string $reviewKey): array
    {
        return DB::transaction(function () use ($outletId, $id, $source, $rowIds, $reviewKey) {
            Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
            $document = $this->document($outletId, $id, $source);
            $review = $this->review($outletId, $id, $source, $rowIds);
            if (! hash_equals($review['review_key'], $reviewKey)) {
                $this->fail('Stock, mapping or recipe changed after review. Review the submission again.');
            }
            $sourceMetadata = json_decode($document->metadata ?? '{}', true);
            $importId = DB::table('imports')->insertGetId(['outlet_id' => $outletId, 'source_type' => $source, 'file_name' => $document->file_name,
                'fingerprint' => hash('sha256', $id.'|'.implode(',', $rowIds)), 'effective_from' => $document->effective_from, 'effective_to' => $document->effective_to,
                'status' => 'applied', 'row_count' => count($rowIds), 'metadata' => json_encode(['document_id' => $id, 'granularity' => $document->granularity, 'ignored' => $review['ignored'],
                    'indent_number' => $source === 'excise' ? ($sourceMetadata['indent_number'] ?? null) : null,
                    'selected_row_ids' => $rowIds, 'review' => $review['summary']]), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($review['plans'] as $plan) {
                $volume = array_sum(array_column($plan['movements'], 'volume_ml'));
                $lineId = DB::table('import_lines')->insertGetId(['import_id' => $importId, 'product_id' => $plan['rules']['product_id'], 'recipe_id' => $plan['rules']['recipe_id'],
                    'effective_date' => $plan['date'], 'source_item_name' => $plan['source_name'], 'sale_type' => $source === 'excise' ? 'indent' : $plan['movements'][0]['sale_type'],
                    'quantity' => $plan['quantity'], 'volume_ml' => $volume, 'line_value' => $plan['amount'],
                    'unit_price' => $source === 'pos' ? ($plan['figures']['sold'] > 0 ? $plan['amount'] / $plan['figures']['sold'] : null) : $plan['amount'] / $plan['quantity'],
                    'raw_data' => json_encode(['source_row_id' => $plan['row_id'], 'figures' => $plan['figures'], 'rules' => $plan['rules'], 'ingredients' => $plan['parts']]),
                    'created_at' => now(), 'updated_at' => now()]);
                foreach ($plan['movements'] as $movement) {
                    DB::table('stock_movements')->insert($movement + ['outlet_id' => $outletId, 'import_id' => $importId, 'import_line_id' => $lineId,
                        'effective_date' => $plan['date'], 'movement_type' => $source === 'excise' ? 'indent' : 'sale',
                        'value_change' => $source === 'excise' ? $plan['amount'] : 0, 'receipt_price_per_ml' => $source === 'excise' ? $plan['amount'] / $volume : null,
                        'reference' => 'Reviewed '.$source.' import #'.$importId, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('upload_rows')->where('document_id', $id)->whereIn('id', $rowIds)->update(['applied_import_id' => $importId, 'updated_at' => now()]);

            return ['import_id' => $importId, 'message' => count($review['summary']).' brands updated. '.$review['selected_rows'].' rows processed; '.$review['ignored'].' explicitly ignored. Import #'.$importId.'.',
                'summary' => $review['summary']];
        }, 3);
    }

    private function parts(int $outletId, array $rules): array
    {
        if ($rules['kind'] === 'ignore') {
            return [];
        }
        if ($rules['kind'] === 'recipe') {
            $recipe = DB::table('recipes')->where('outlet_id', $outletId)->where('is_active', true)->find($rules['recipe_id']);
            if (! $recipe) {
                $this->fail('The selected cocktail is no longer active.');
            }
            $parts = DB::table('recipe_ingredients')->join('products', 'products.id', '=', 'recipe_ingredients.product_id')
                ->where('recipe_id', $recipe->id)->where('products.outlet_id', $outletId)->where('products.is_active', true)
                ->get(['products.id', 'products.name', 'products.bottle_size_ml', 'recipe_ingredients.volume_ml']);
            if (! $parts->count() || $parts->count() !== DB::table('recipe_ingredients')->where('recipe_id', $recipe->id)->count()) {
                $this->fail('The cocktail contains missing or inactive ingredients.');
            }

            return $parts->map(fn ($part) => ['id' => $part->id, 'name' => $part->name, 'size_ml' => (float) $part->bottle_size_ml, 'per_sale_ml' => (float) $part->volume_ml, 'sale_type' => 'cocktail'])->all();
        }
        $product = Product::where('outlet_id', $outletId)->where('is_active', true)->find($rules['product_id']);
        if (! $product) {
            $this->fail('The selected brand is no longer available.');
        }
        $ml = $rules['kind'] === 'bottle' ? (float) $product->bottle_size_ml * $rules['bottles_per_sale'] : (float) $rules['serving_ml'];
        $type = $rules['kind'] === 'bottle' ? 'full_bottle' : ($ml === 30.0 ? 'peg_30' : ($ml === 60.0 ? 'peg_60' : 'measured'));

        return [['id' => $product->id, 'name' => $product->name, 'size_ml' => (float) $product->bottle_size_ml, 'per_sale_ml' => $ml, 'sale_type' => $type]];
    }

    private function document(int $outletId, string $id, ?string $source = null): object
    {
        $document = DB::table('upload_documents')->where('outlet_id', $outletId)->where('id', $id)->when($source, fn ($q) => $q->where('source', $source))->first();
        if (! $document) {
            $this->fail('The saved upload was not found. Upload the original document again.');
        }

        return $document;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['stock' => $message]);
    }
}
