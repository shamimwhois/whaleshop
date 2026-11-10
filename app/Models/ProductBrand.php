<?php

namespace App\Models;

use App\Enums\Status;
use App\Support\Concerns\HasMediaUrlFallback;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductBrand extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasMediaUrlFallback;
    protected $table = "product_brands";
    protected $fillable = ['name', 'slug', 'description', 'status'];
    protected $casts = [
        'id'          => 'integer',
        'name'        => 'string',
        'slug'        => 'string',
        'description' => 'string',
        'status'      => 'integer',
    ];

    public function getThumbAttribute(): string
    {
        return $this->resolveMediaUrl('product-brand', 'thumb', 'images/default/brand/thumb.png');
    }

    public function getCoverAttribute(): string
    {
        return $this->resolveMediaUrl('product-brand', 'cover', 'images/default/brand/cover.png');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (! static::canGenerateImageConversions()) {
            return;
        }

        $this->addMediaConversion('thumb')->fit(Fit::Fill, 108, 108)->keepOriginalImageFormat()->sharpen(10);
        $this->addMediaConversion('cover')->width(450)->keepOriginalImageFormat()->sharpen(10);
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class)->where(['status' => Status::ACTIVE]);
    }
}
