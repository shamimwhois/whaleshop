<?php

namespace App\Models;



use App\Support\Concerns\HasMediaUrlFallback;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Benefit extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaUrlFallback;
    protected $table = "benefits";
    protected $fillable = ['title', 'description', 'status', 'sort'];
    protected $casts = [
        'id'          => 'integer',
        'title'       => 'string',
        'description' => 'string',
        'status'      => 'integer',
        'sort'        => 'integer',
    ];

    public function getThumbAttribute(): string
    {
        return $this->resolveMediaUrl('benefit', 'thumb', 'images/default/benefit/thumb.png');
    }

    public function getCoverAttribute(): string
    {
        return $this->resolveMediaUrl('benefit', 'cover', 'images/default/benefit/cover.png');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (! static::canGenerateImageConversions()) {
            return;
        }

        $this->addMediaConversion('thumb')->fit(Fit::Fill, 36, 36)->keepOriginalImageFormat()->sharpen(10);
        $this->addMediaConversion('cover')->width(600)->keepOriginalImageFormat()->sharpen(10);
    }
}
