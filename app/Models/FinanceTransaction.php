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
        'reference',
        'note',
        'balance_after',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
