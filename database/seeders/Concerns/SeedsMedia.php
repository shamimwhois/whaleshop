<?php

namespace Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SeedsMedia
{
    /**
     * Attach a seeded media file to a model.
     *
     * Spatie Media Library generates image conversions with GD/Imagick. On a PHP
     * build without either extension we still attach the original file so the
     * demo images are visible; only the derived conversions (`thumb`, `cover`,
     * `preview`) are skipped by the `Media` model's graceful fallback to the
     * original URL.
     */
    protected function attachMedia(Model $model, string $path, string $collection): void
    {
        if (! file_exists($path)) {
            return;
        }

        if ($this->canProcessImages()) {
            $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection);
            return;
        }

        // No GD/Imagick: attach the original without running conversions so the
        // demo image is still usable on the storefront.
        $model->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection($collection);
    }

    private function canProcessImages(): bool
    {
        return function_exists('imagecreatefromstring') || extension_loaded('imagick');
    }
}
