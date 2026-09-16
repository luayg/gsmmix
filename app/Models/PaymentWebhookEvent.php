<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentWebhookEvent extends Model
{
    protected $fillable = ['payment_gateway_id','event_id','payload_hash','status','error','processed_at'];
    protected function casts(): array { return ['processed_at' => 'datetime']; }
    public function gateway() { return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id'); }
}
