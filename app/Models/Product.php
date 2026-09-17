<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['outlet_id', 'name', 'spirit_type', 'excise_code', 'bottle_size_ml', 'is_active'];

    protected function casts(): array
    {
        return ['bottle_size_ml' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
