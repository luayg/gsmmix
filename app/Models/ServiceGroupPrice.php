<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceGroupPrice extends Model
{
    protected $table = 'service_group_prices';

    protected $fillable = [
        'service_id',
        'service_type',
        'group_id',
        'price',
        'auto_price',
        'discount',
        'discount_type',
    ];

    protected $casts = ['auto_price' => 'boolean'];

    public static function servicePrice(array|object $service): float
    {
        $cost = (float) data_get($service, 'cost', 0);
        $profit = (float) data_get($service, 'profit', 0);
        $profitType = data_get($service, 'profit_type', 1);
        $percent = (int) $profitType === 2 || $profitType === 'percent';

        return round($cost + ($percent ? $cost * $profit / 100 : $profit), 4);
    }

    /** Automatic prices follow the service; legacy rows remain explicit overrides. */
    public function basePrice(array|object|null $service = null): ?float
    {
        if ($this->auto_price && $service === null) {
            return null;
        }
        $price = $this->auto_price ? self::servicePrice($service) : $this->price;

        return is_numeric($price) && is_finite((float) $price) && (float) $price >= 0
            ? (float) $price : null;
    }

    /** Null means unavailable; zero is a valid, explicitly free price. */
    public function finalPrice(array|object|null $service = null): ?float
    {
        $price = $this->basePrice($service);
        if ($price === null) {
            return null;
        }
        $discount = max(0, (float) ($this->discount ?? 0));
        $reduction = (int) ($this->discount_type ?? 1) === 2
            ? $price * $discount / 100 : $discount;

        return max(0, $price - $reduction);
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

}
