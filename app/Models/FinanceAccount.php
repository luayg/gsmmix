<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceAccount extends Model
{
    protected $table = 'finance_accounts';

    protected $fillable = [
        'user_id',
        'locked_amount',
        'total_receipts',
        'paid_credits',
        'overdraft_limit',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
