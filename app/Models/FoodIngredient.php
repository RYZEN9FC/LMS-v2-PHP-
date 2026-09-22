<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodIngredient extends Model
{
    protected $fillable = [
        'outlet_id', 'name', 'category', 'base_unit', 'purchase_unit', 'purchase_to_base',
        'low_stock_base', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_to_base' => 'decimal:4',
            'low_stock_base' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FoodStockMovement::class);
    }
}
