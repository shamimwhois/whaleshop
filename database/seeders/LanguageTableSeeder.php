<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMedia;


use App\Enums\Status;
use App\Enums\DisplayMode;
use App\Models\Language;
use Illuminate\Database\Seeder;


class LanguageTableSeeder extends Seeder
{
    use SeedsMedia;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $englishLanguageArray = [
            'name'         => 'English',
            'code'         => 'en',
            'display_mode' => DisplayMode::LTR,
            'status'       => Status::ACTIVE
        ];

        $banglaLanguageArray = [
            'name'         => 'Bangla',
            'code'         => 'bn',
            'display_mode' => DisplayMode::LTR,
            'status'       => Status::ACTIVE
        ];

        $arabicLanguageArray = [
            'name'         => 'Arabic',
            'code'         => 'ar',
            'display_mode' => DisplayMode::RTL,
            'status'       => Status::ACTIVE
        ];

        $englishLanguage = Language::firstOrCreate(['code' => $englishLanguageArray['code']], $englishLanguageArray);
        if ($englishLanguage->wasRecentlyCreated && file_exists(public_path('/images/seeder/language/english.png'))) {
            $this->attachMedia($englishLanguage, public_path('/images/seeder/language/english.png'), 'language');
        }

        $banglaLanguage = Language::firstOrCreate(['code' => $banglaLanguageArray['code']], $banglaLanguageArray);
        if ($banglaLanguage->wasRecentlyCreated && file_exists(public_path('/images/seeder/language/bangla.png'))) {
            $this->attachMedia($banglaLanguage, public_path('/images/seeder/language/bangla.png'), 'language');
        }

        $arabicLanguage = Language::firstOrCreate(['code' => $arabicLanguageArray['code']], $arabicLanguageArray);
        if ($arabicLanguage->wasRecentlyCreated && file_exists(public_path('/images/seeder/language/arabic.png'))) {
            $this->attachMedia($arabicLanguage, public_path('/images/seeder/language/arabic.png'), 'language');
        }
    }
}
