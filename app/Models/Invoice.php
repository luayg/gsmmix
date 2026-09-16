<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Invoice extends Model
{
    public const STATUSES = ['draft', 'pending', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'];
    protected $fillable = ['number','user_id','status','currency_code','exchange_rate','customer_snapshot','company_snapshot','subtotal','discount_total','tax_total','fee_total','total','paid_total','issued_at','due_at','paid_at','notes','terms','source_type','source_id'];
    protected function casts(): array { return ['customer_snapshot'=>'array','company_snapshot'=>'array','exchange_rate'=>'decimal:8','subtotal'=>'decimal:4','discount_total'=>'decimal:4','tax_total'=>'decimal:4','fee_total'=>'decimal:4','total'=>'decimal:4','paid_total'=>'decimal:4','issued_at'=>'date','due_at'=>'date','paid_at'=>'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(InvoiceItem::class)->orderBy('ordering'); }
    public function payments() { return $this->hasMany(InvoicePayment::class)->orderByDesc('paid_at'); }
    public function source() { return $this->morphTo(); }
    public function getBalanceDueAttribute(): string { return bcsub((string)$this->total, (string)$this->paid_total, 4); }
}
