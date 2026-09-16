<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class InvoiceItem extends Model
{
    protected $fillable=['description','quantity','unit_price','discount','tax_rate','line_total','ordering'];
    protected function casts(): array { return ['quantity'=>'decimal:4','unit_price'=>'decimal:4','discount'=>'decimal:4','tax_rate'=>'decimal:4','line_total'=>'decimal:4']; }
    public function invoice(){ return $this->belongsTo(Invoice::class); }
}
