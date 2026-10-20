<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['outstanding'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function getOutstandingAttribute(): string
    {
        return bcadd((string) ($this->attributes['outstanding'] ?? $this->sales()->sum('balance')), '0', 2);
    }
}
