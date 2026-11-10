<?php

namespace Database\Seeders;

use App\Models\Benefit;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\Slider;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\SeedsMedia;

/**
 * Idempotently attaches the bundled demo images to already seeded models.
 *
 * The original demo seeders only attach media on `wasRecentlyCreated`, so on a
 * database that was seeded on a PHP build without GD/Imagick (which used to skip
 * attachments entirely) the storefront shows placeholder graphics. This seeder
 * can be re-run safely: it only attaches an image when the target collection is
 * still empty.
 */
class DemoMediaTableSeeder extends Seeder
{
    use SeedsMedia;

    public function run(): void
    {
        $displayType = env('DISPLAY_TYPE', 'fashion');
        $seederPath  = public_path('images/seeder');

        $this->seedProductCategories($seederPath, $displayType);
        $this->seedProductBrands($seederPath, $displayType);
        $this->seedProducts($seederPath, $displayType);
        $this->seedBenefits($seederPath);
        $this->seedSliders($seederPath, $displayType);
        $this->seedPromotions($seederPath, $displayType);
    }

    /**
     * Convert a model name to the file name convention used by the bundled demo
     * images (e.g. "Dr. Martens" -> "dr._martens").
     */
    private function fileName(string $name): string
    {
        return strtolower(str_replace(' ', '_', $name));
    }

    private function seedProductCategories(string $seederPath, string $displayType): void
    {
        $directory = "{$seederPath}/product-category/{$displayType}";
        if (! is_dir($directory)) {
            return;
        }

        ProductCategory::query()->each(function (ProductCategory $category) use ($directory) {
            if ($category->hasMedia('product-category')) {
                return;
            }

            $file = "{$directory}/" . $this->fileName($category->name) . '.png';
            if (file_exists($file)) {
                $this->attachMedia($category, $file, 'product-category');
            }
        });
    }

    private function seedProductBrands(string $seederPath, string $displayType): void
    {
        $directory = "{$seederPath}/brand/{$displayType}";
        if (! is_dir($directory)) {
            return;
        }

        ProductBrand::query()->each(function (ProductBrand $brand) use ($directory) {
            if ($brand->hasMedia('product-brand')) {
                return;
            }

            $file = "{$directory}/" . $this->fileName($brand->name) . '.png';
            if (file_exists($file)) {
                $this->attachMedia($brand, $file, 'product-brand');
            }
        });
    }

    private function seedProducts(string $seederPath, string $displayType): void
    {
        $directory = "{$seederPath}/product/{$displayType}";
        if (! is_dir($directory)) {
            return;
        }

        Product::query()->each(function (Product $product) use ($directory) {
            $folder = $this->findProductFolder($directory, $product->name);
            if ($folder === null) {
                return;
            }

            $images = glob("{$folder}/*.png") ?: [];
            sort($images, SORT_NATURAL);

            if (! $product->hasMedia('product')) {
                foreach ($images as $image) {
                    $this->attachMedia($product, $image, 'product');
                }
            }
        });
    }

    /**
     * Locate the image folder for a product regardless of the section/type
     * hierarchy (men/women/juniors + clothing/shoes/accessories).
     *
     * The bundled demo images use the seeder convention
     * `strtolower(str_replace(' ', '_', $name))`, not a slug.
     */
    private function findProductFolder(string $directory, string $productName): ?string
    {
        $folderName = $this->fileName($productName);
        $sections   = ['men', 'women', 'juniors'];
        $types      = ['clothing', 'shoes', 'accessories'];

        foreach ($sections as $section) {
            foreach ($types as $type) {
                $candidate = "{$directory}/{$section}/{$type}/{$folderName}";
                if (is_dir($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function seedBenefits(string $seederPath): void
    {
        $directory = "{$seederPath}/benefit";
        if (! is_dir($directory)) {
            return;
        }

        Benefit::query()->each(function (Benefit $benefit) use ($directory) {
            if ($benefit->hasMedia('benefit')) {
                return;
            }

            $file = "{$directory}/" . $this->fileName($benefit->title) . '.png';
            if (file_exists($file)) {
                $this->attachMedia($benefit, $file, 'benefit');
            }
        });
    }

    private function seedSliders(string $seederPath, string $displayType): void
    {
        $directory = "{$seederPath}/slider/{$displayType}";
        if (! is_dir($directory)) {
            return;
        }

        Slider::query()->each(function (Slider $slider) use ($directory) {
            if ($slider->hasMedia('slider')) {
                return;
            }

            $file = "{$directory}/" . $this->fileName($slider->title) . '.png';
            if (file_exists($file)) {
                $this->attachMedia($slider, $file, 'slider');
            }
        });
    }

    private function seedPromotions(string $seederPath, string $displayType): void
    {
        $directory = "{$seederPath}/promotion/{$displayType}";
        if (! is_dir($directory)) {
            return;
        }

        Promotion::query()->each(function (Promotion $promotion) use ($directory) {
            if ($promotion->hasMedia('promotion')) {
                return;
            }

            $file = "{$directory}/" . $this->fileName($promotion->name) . '.png';
            if (file_exists($file)) {
                $this->attachMedia($promotion, $file, 'promotion');
            }
        });
    }
}
