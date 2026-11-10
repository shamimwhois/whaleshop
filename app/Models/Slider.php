<?php

namespace App\Models;

use App\Support\Concerns\HasMediaUrlFallback;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Slider extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaUrlFallback;

    protected $table = "sliders";
    protected $fillable = ['title', 'link', 'description', 'status'];
    protected $casts = [
        'id'          => 'integer',
        'title'       => 'string',
        'description' => 'string',
        'status'      => 'integer',
        'link'        => 'string',
    ];

    public function getImageAttribute(): string
    {
        return $this->resolveMediaUrl('slider', 'cover', 'images/default/slider.png');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (! static::canGenerateImageConversions()) {
            return;
        }

        $this->addMediaConversion('cover')->fit(Fit::Fill, 1689, 600)->keepOriginalImageFormat()->sharpen(10);
    }
}
