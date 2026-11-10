<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductExchangeProduct extends Model
{
    protected $table = "product_exchange_products";
    protected $fillable = [
        'product_exchange_id',
        'product_id',
        'variation_id',
        'variation_names',
        'quantity',
        'price',
        'total',
        'exchange_price',
        'exchange_product_id',
        'exchange_product_quantity',
        'exchange_product_price',
        'exchange_product_total',
        'user_id'
    ];
    protected $casts = [
        'id'                       => 'integer',
        'product_exchange_id'      => 'integer',
        'product_id'               => 'integer',
        'variation_id'             => 'integer',
        'variation_names'          => 'string',
        'quantity'                 => 'integer',
        'price'                    => 'decimal:6',
        'total'                    => 'decimal:6',
        'exchange_price'           => 'decimal:6',
        'exchange_product_id'      => 'integer',
        'exchange_product_quantity' => 'integer',
        'exchange_product_price'   => 'decimal:6',
        'exchange_product_total'   => 'decimal:6',
        'user_id'                  => 'integer'
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function exchangeProduct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'exchange_product_id')->withTrashed();
    }
}
