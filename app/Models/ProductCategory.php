<?php

namespace App\Models;


use App\Enums\Status;
use App\Support\Concerns\HasMediaUrlFallback;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;


class ProductCategory extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use HasRecursiveRelationships;
    use HasMediaUrlFallback;

    protected $table = "product_categories";
    protected $fillable = ['name', 'slug', 'description', 'status', 'parent_id'];
    protected $casts = [
        'id'          => 'integer',
        'name'        => 'string',
        'slug'        => 'string',
        'description' => 'string',
        'status'      => 'integer',
        'parent_id'   => 'integer',
    ];
    protected $appends = array('cover');

    public function getThumbAttribute(): string
    {
        return $this->resolveMediaUrl('product-category', 'thumb', 'images/default/category/thumb.png');
    }

    public function getCoverAttribute(): string
    {
        return $this->resolveMediaUrl('product-category', 'cover', 'images/default/category/cover.png');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (! static::canGenerateImageConversions()) {
            return;
        }

        $this->addMediaConversion('thumb')->width(252)->height(183)->keepOriginalImageFormat()->sharpen(10);
        $this->addMediaConversion('cover')->width(960)->height(1440)->keepOriginalImageFormat()->sharpen(10);
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class)->where(['status' => Status::ACTIVE]);
    }

    public function parent_category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', Status::ACTIVE);
    }
}
