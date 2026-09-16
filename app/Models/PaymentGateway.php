<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentGateway extends Model
{
    protected $fillable = ['name', 'slug', 'driver', 'description', 'instructions', 'logo_path', 'config', 'credentials', 'fixed_fee', 'percent_fee', 'tax_percent', 'minimum_amount', 'maximum_amount', 'sandbox', 'active', 'ordering'];
    protected $hidden = ['credentials'];
    protected function casts(): array
    {
        return ['config' => 'array', 'credentials' => 'encrypted:array', 'fixed_fee' => 'decimal:8', 'percent_fee' => 'decimal:4', 'tax_percent' => 'decimal:4', 'minimum_amount' => 'decimal:8', 'maximum_amount' => 'decimal:8', 'sandbox' => 'boolean', 'active' => 'boolean', 'ordering' => 'integer'];
    }
    public function currencies() { return $this->belongsToMany(Currency::class); }
    public function transactions() { return $this->hasMany(PaymentTransaction::class); }
}
