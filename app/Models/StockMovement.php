<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'outlet_id', 'product_id', 'import_id', 'import_line_id', 'effective_date',
        'movement_type', 'sale_type', 'sale_quantity', 'volume_ml', 'value_change',
        'receipt_price_per_ml', 'reference',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'sale_quantity' => 'decimal:3',
            'volume_ml' => 'decimal:2',
            'value_change' => 'decimal:2',
            'receipt_price_per_ml' => 'decimal:6',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
