<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentTransaction extends Model
{
    protected $fillable = ['uuid', 'user_id', 'payment_gateway_id', 'currency_id', 'currency_code', 'exchange_rate', 'amount_base', 'fee_base', 'payable_base', 'payable_currency', 'status', 'external_id', 'proof_path', 'metadata', 'approved_by', 'paid_at'];
    protected function casts(): array
    {
        return ['metadata' => 'array', 'exchange_rate' => 'decimal:8', 'amount_base' => 'decimal:8', 'fee_base' => 'decimal:8', 'payable_base' => 'decimal:8', 'payable_currency' => 'decimal:8', 'paid_at' => 'datetime'];
    }
    public function user() { return $this->belongsTo(User::class); }
    public function gateway() { return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id'); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function invoice() { return $this->morphOne(Invoice::class, 'source'); }
}
