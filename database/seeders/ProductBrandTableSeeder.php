<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMedia;

use App\Enums\Status;
use Illuminate\Support\Str;
use App\Models\ProductBrand;
use Illuminate\Database\Seeder;
use Dipokhalder\EnvEditor\EnvEditor;

class ProductBrandTableSeeder extends Seeder
{
    use SeedsMedia;

    /**
     * Run the database seeds.
     *
     * @return void
     */

    public array $fashionBrands = [
        'Babymel',
        'Burberry',
        'Camper',
        'Chanel',
        'Dr. Martens',
        'Fila',
        'Levi\'s',
        'puma'
    ];

    public function run(): void
    {
        $envService = new EnvEditor();
        if ($envService->getValue('DEMO') && $envService->getValue('DISPLAY_TYPE') == 'fashion') {
            foreach ($this->fashionBrands as $fashionBrand) {                $productBand = ProductBrand::firstOrCreate(['slug' => Str::slug($fashionBrand)], [
                    'name'        => $fashionBrand,
                    'slug'        => Str::slug($fashionBrand),
                    'description' => null,
                    'status'      => Status::ACTIVE,
                ]);
                if ($productBand->wasRecentlyCreated && file_exists(public_path('/images/seeder/brand/' . env('DISPLAY_TYPE') . '/' . strtolower(str_replace(' ', '_', $fashionBrand)) . '.png'))) {
                    $this->attachMedia($productBand, public_path('/images/seeder/brand/' . env('DISPLAY_TYPE') . '/' . strtolower(str_replace(' ', '_', $fashionBrand)) . '.png'), 'product-brand');
                }
            }
        }
    }
}
