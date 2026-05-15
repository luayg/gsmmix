<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $fillable = [
        'name',
        'active',
        'ordering',
    ];

    protected $casts = [
        'active' => 'boolean',
        'ordering' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

