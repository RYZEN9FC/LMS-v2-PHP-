<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAlias;

class ProductNameMatcher
{
    public static function normalise(string $name): string
    {
        $name = strtoupper(trim($name));

        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    public function match(int $outletId, string $source, string $sourceName): ?Product
    {
        $normalised = self::normalise($sourceName);
        $alias = ProductAlias::query()->with('product')->where([
            'outlet_id' => $outletId, 'source' => $source, 'normalised_name' => $normalised,
        ])->first();

        // A match is authoritative only after a user has saved an alias.
        // Similar-looking names are suggestions, never an automatic mapping.
        return $alias && self::normalise($alias->source_name) === $normalised ? $alias->product : null;
    }
}
