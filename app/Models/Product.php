<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['stock' => 'integer', 'minimum_stock' => 'integer', 'average_cost' => 'decimal:6', 'active' => 'boolean'];
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('multiplier');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
