<?php

namespace App\Support\Concerns;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolve a media URL for the given collection + conversion, degrading
 * gracefully to the original file when the requested conversion has not been
 * generated.
 *
 * Spatie Media Library generates conversions with GD/Imagick. On PHP builds
 * without those extensions the conversion files never exist (and the
 * conversion may even be unregistered), so calling `Media::getUrl('thumb')`
 * would return an empty string or throw. This helper keeps the storefront
 * usable by always returning a real image URL.
 */
trait HasMediaUrlFallback
{
    /**
     * @param  string  $collection  Media collection name (e.g. "product").
     * @param  string|null  $conversion  Conversion name (e.g. "thumb"), or null for the original.
     * @param  string|null  $default  URL returned when no media is attached.
     */
    protected function resolveMediaUrl(string $collection, ?string $conversion = null, ?string $default = null): string
    {
        $media = $this->getMedia($collection)->last();

        if (! $media instanceof Media) {
            return $default !== null ? asset($default) : '';
        }

        if ($conversion !== null && $this->conversionExists($media, $conversion)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
    }

    /**
     * Determine whether a named conversion is registered and its file exists.
     */
    protected function conversionExists(Media $media, string $conversion): bool
    {
        try {
            if (! $media->hasGeneratedConversion($conversion)) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Whether the current PHP build can generate image conversions.
     *
     * Spatie Media Library relies on GD or Imagick; when neither is loaded we
     * skip registering conversions entirely so seeding/uploading the original
     * image does not abort.
     */
    public static function canGenerateImageConversions(): bool
    {
        return function_exists('imagecreatefromstring') || extension_loaded('imagick');
    }
}
