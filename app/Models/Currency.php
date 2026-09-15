<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Currency extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'exchange_rate', 'decimal_places', 'symbol_position', 'decimal_separator', 'thousands_separator', 'is_default', 'active', 'ordering', 'rate_updated_at'];
    protected function casts(): array
    {
        return ['exchange_rate' => 'decimal:8', 'decimal_places' => 'integer', 'is_default' => 'boolean', 'active' => 'boolean', 'ordering' => 'integer', 'rate_updated_at' => 'datetime'];
    }
}
