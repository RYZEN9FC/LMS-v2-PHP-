<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodStockMovement extends Model
{
    protected $fillable = [
        'outlet_id', 'food_ingredient_id', 'food_sale_id', 'entered_by', 'effective_date',
        'movement_type', 'quantity_base', 'unit_cost_per_base', 'value_change', 'reference', 'note',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'quantity_base' => 'decimal:3',
            'unit_cost_per_base' => 'decimal:6',
            'value_change' => 'decimal:2',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(FoodIngredient::class, 'food_ingredient_id');
    }
}
