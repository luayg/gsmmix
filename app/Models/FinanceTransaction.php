<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceTransaction extends Model
{
    protected $table = 'finance_transactions';

    protected $fillable = [
        'user_id',
        'kind',           // payment | credit_add | credit_remove | order_lock | order_release ...
        'direction',      // income | expense
        'paid',           // 0/1
        'amount',
        'currency_code',
        'original_amount',
        'exchange_rate',
        'reference',
        'note',
        'balance_before',
        'balance_after',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return ['paid' => 'boolean', 'amount' => 'decimal:4', 'original_amount' => 'decimal:4', 'exchange_rate' => 'decimal:8', 'balance_before' => 'decimal:4', 'balance_after' => 'decimal:4'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function source() { return $this->morphTo(); }
}
