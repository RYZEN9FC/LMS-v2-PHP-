<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAlias extends Model
{
    protected $fillable = ['outlet_id', 'product_id', 'source', 'source_name', 'normalised_name'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
