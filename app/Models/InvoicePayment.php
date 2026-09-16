<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class InvoicePayment extends Model
{
    protected $fillable=['payment_transaction_id','finance_transaction_id','amount','currency_code','exchange_rate','reference','paid_at'];
    protected function casts(): array { return ['amount'=>'decimal:4','exchange_rate'=>'decimal:8','paid_at'=>'datetime']; }
    public function invoice(){ return $this->belongsTo(Invoice::class); }
}
