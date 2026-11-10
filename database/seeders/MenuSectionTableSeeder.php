<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MenuSection;
use Database\Seeders\Concerns\IdempotentSeeder;

class MenuSectionTableSeeder extends Seeder
{
    use IdempotentSeeder;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->insertMissing('menu_sections', [
            [
                'name'          => 'Support Section',
                'created_at'       => now(),
                'updated_at'       => now()
            ],
            [
                'name'          => 'Legal Section',
                'created_at'       => now(),
                'updated_at'       => now()
            ],
        ], 'name');
    }
}
