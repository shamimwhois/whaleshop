<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMedia;

use App\Enums\Status;
use Illuminate\Support\Str;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Dipokhalder\EnvEditor\EnvEditor;

class ProductCategoryTableSeeder extends Seeder
{
    use SeedsMedia;

    /**
     * Run the database seeds.
     *
     * @return void
     */


    public array $fashionCategories = [
        [
            'name'     => 'Men',
            'children' => [
                [
                    'name'     => 'Clothing',
                    'children' => [
                        ['name' => 'Hoodies & Sweatshirts'],
                        ['name' => 'Jackets & Vests'],
                        ['name' => 'Pants & Tights'],
                        ['name' => 'Shorts'],
                        ['name' => 'Tops & T-shirts']
                    ]
                ],
                [
                    'name'     => 'Shoes',
                    'children' => [
                        ['name' => 'Basket Ball'],
                        ['name' => 'Running'],
                        ['name' => 'Sandals & Slides'],
                        ['name' => 'Sneakers'],
                        ['name' => 'Soccer'],
                    ]
                ],
                [
                    'name'     => 'Accessories',
                    'children' => [
                        ['name' => 'Bags & Backpacks'],
                        ['name' => 'Hat & Beanies'],
                        ['name' => 'Socks'],
                        ['name' => 'Underwear']
                    ]
                ],
            ],
        ],
        [
            'name'     => 'Women',
            'children' => [
                [
                    'name'     => 'Clothing',
                    'children' => [
                        ['name' => 'Dresses & Skirts'],
                        ['name' => 'Hoodies & Sweatshirts'],
                        ['name' => 'Pants'],
                        ['name' => 'Tights & Leggings'],
                        ['name' => 'Tops & T-shirts']
                    ]
                ],
                [
                    'name'     => 'Shoes',
                    'children' => [
                        ['name' => 'Running'],
                        ['name' => 'Sneakers'],
                        ['name' => 'Training & Gym']
                    ]
                ],
                [
                    'name'     => 'Accessories',
                    'children' => [
                        ['name' => 'Bags & Backpacks'],
                        ['name' => 'Hats'],
                        ['name' => 'Socks']
                    ]
                ]
            ]
        ],
        [
            'name'     => 'Juniors',
            'children' => [
                [
                    'name'     => 'Clothing',
                    'children' => [
                        ['name' => 'Hoodies & Sweatshirts'],
                        ['name' => 'Shorts'],
                        ['name' => 'Tops & T-shirts']
                    ]
                ],
                [
                    'name'     => 'Shoes',
                    'children' => [
                        ['name' => 'Basket Ball'],
                        ['name' => 'Running'],
                        ['name' => 'Sneakers']
                    ]
                ],
                [
                    'name'     => 'Accessories',
                    'children' => [
                        ['name' => 'Bags & Backpacks'],
                        ['name' => 'Hats'],
                    ]
                ]
            ]
        ]
    ];

    public function run(): void
    {
        $envService = new EnvEditor();
        if ($envService->getValue('DEMO') && $envService->getValue('DISPLAY_TYPE') == 'fashion') {
            foreach ($this->fashionCategories as $fashionCategory) {
                $productCategory = ProductCategory::firstOrCreate([
                    'parent_id'   => NULL,
                    'name'        => $fashionCategory['name'],
                ], [
                    'parent_id'   => NULL,
                    'name'        => $fashionCategory['name'],
                    'slug'        => Str::slug($fashionCategory['name'] . rand(1, 100)),
                    'description' => null,
                    'status'      => Status::ACTIVE,
                ]);
                if ($productCategory->wasRecentlyCreated && file_exists(public_path('/images/seeder/product-category/' . env('DISPLAY_TYPE') . '/' . strtolower(str_replace(' ', '_', $fashionCategory['name'])) . '.png'))) {
                    $this->attachMedia($productCategory, public_path('/images/seeder/product-category/' . env('DISPLAY_TYPE') . '/' . strtolower(str_replace(' ', '_', $fashionCategory['name'])) . '.png'), 'product-category');
                }

                if ($productCategory->wasRecentlyCreated && isset($fashionCategory['children']) && count($fashionCategory['children']) > 0) {
                    $this->nested($fashionCategory['children'], $productCategory->id);
                }
            }
        }
    }

    public function nested($arrays, $id = null): void
    {
        foreach ($arrays as $array) {
            $productCategory = ProductCategory::firstOrCreate([
                'parent_id'   => $id,
                'name'        => $array['name'],
            ], [
                'parent_id'   => $id,
                'name'        => $array['name'],
                'slug'        => Str::slug($array['name'] . rand(101, 500)).rand(100, 200),
                'description' => null,
                'status'      => Status::ACTIVE,
            ]);
            if ($productCategory->wasRecentlyCreated && file_exists(public_path('/images/seeder/product-category/' . env('DISPLAY_TYPE') . '/' . strtolower(str_replace(' ', '_', $array['name'])) . '.png'))) {
                $this->attachMedia($productCategory, public_path('/images/seeder/product-category/' . env('DISPLAY_TYPE') . '/' . strtolower(str_replace(' ', '_', $array['name'])) . '.png'), 'product-category');
            }
            if ($productCategory->wasRecentlyCreated && isset($array['children']) && count($array) > 0) {
                $this->nested($array['children'], $productCategory->id);
            }
        }
    }
}
