<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'product_category_id',
        'local_source_id',
        'name',
        'alias',
        'main_image',
        'description',
        'delivery_time',
        'cost',
        'price',
        'converted_price',
        'currency',
        'profit',
        'profit_type',
        'active',
        'device_based',
        'unlimited',
        'hot',
        'new',
        'sale',
        'ordering',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    protected $casts = [
        'cost' => 'float',
        'price' => 'float',
        'converted_price' => 'float',
        'profit' => 'float',
        'active' => 'boolean',
        'device_based' => 'boolean',
        'unlimited' => 'boolean',
        'hot' => 'boolean',
        'new' => 'boolean',
        'sale' => 'boolean',
        'ordering' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (trim((string) $product->alias) === '') {
                $product->alias = static::uniqueAlias((string) $product->name, $product->id);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function localSource()
    {
        return $this->belongsTo(LocalSource::class, 'local_source_id');
    }

    public function orders()
    {
        return $this->hasMany(ProductOrder::class);
    }

    private static function uniqueAlias(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'product';
        }

        $alias = $base;
        $i = 2;

        while (
            static::query()
                ->where('alias', $alias)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $alias = $base . '-' . $i++;
        }

        return $alias;
    }
}