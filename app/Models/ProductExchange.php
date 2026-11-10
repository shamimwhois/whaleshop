<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\HasMediaUrlFallback;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductExchange extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaUrlFallback;

    protected $table = "product_exchanges";
    protected $fillable = ['exchange_reason_id', 'note', 'reject_reason', 'order_id', 'user_id', 'order_serial_no', 'exchange_price_difference', 'status'];
    protected $casts = [
        'id'                        => 'integer',
        'exchange_reason_id'        => 'integer',
        'note'                      => 'string',
        'reject_reason'             => 'string',
        'order_id'                  => 'integer',
        'user_id'                   => 'integer',
        'order_serial_no'           => 'integer',
        'exchange_price_difference' => 'decimal:6',
        'status'                    => 'integer'
    ];

    public function getImagesAttribute(): array
    {
        $response = [];
        if (!empty($this->getFirstMediaUrl('exchange'))) {
            $images = $this->getMedia('exchange');
            foreach ($images as $image) {
                $response[] = $image['original_url'];
            }
        }
        return $response;
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (! static::canGenerateImageConversions()) {
            return;
        }

        $this->addMediaConversion('thumb')->width(168)->height(180)->keepOriginalImageFormat()->sharpen(10);
    }

    public function exchangeProducts(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(ProductExchangeProduct::class, 'product_exchange_id');
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\belongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function exchangeReason(): \Illuminate\Database\Eloquent\Relations\belongsTo
    {
        return $this->belongsTo(ExchangeReason::class, 'exchange_reason_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
