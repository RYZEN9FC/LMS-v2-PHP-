<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'timezone'];

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }
}
