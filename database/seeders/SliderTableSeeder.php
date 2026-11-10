<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMedia;

use App\Enums\Status;
use App\Models\Slider;
use Illuminate\Database\Seeder;
use Dipokhalder\EnvEditor\EnvEditor;

class SliderTableSeeder extends Seeder
{
    use SeedsMedia;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public array $sliders = [
        'Slider One',
        'Slider Two',
        'Slider Three',
    ];

    public function run(): void
    {
        $envService = new EnvEditor();
        if ($envService->getValue('DEMO')) {
            foreach ($this->sliders as $slider) {
                $sliderObject = Slider::firstOrCreate(['title' => $slider], [
                    'title'       => $slider,
                    'description' => $slider,
                    'status'      => Status::ACTIVE,
                ]);
                if ($sliderObject->wasRecentlyCreated && file_exists(public_path('/images/seeder/slider/' . env('DISPLAY_TYPE') .'/' . strtolower(str_replace(' ', '_', $slider)) . '.png'))) {
                    $this->attachMedia($sliderObject, public_path('/images/seeder/slider/' . env('DISPLAY_TYPE') .'/' . strtolower(str_replace(' ', '_', $slider)) . '.png'), 'slider');
                }
            }
        }
    }
}
