<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeReason extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'status', 'details'];

    protected $casts = [
        'id'        => 'integer',
        'title'     => 'string',
        'status'    => 'integer',
        'details'   => 'string'
    ];

    public function product_exchanges()
    {
        return $this->hasMany(ProductExchange::class);
    }
}
