<?php

namespace Database\Seeders;

use App\Models\MenuTemplate;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\IdempotentSeeder;

class MenuTemplateTableSeeder extends Seeder
{
    use IdempotentSeeder;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->insertMissing('menu_templates', [
            [
                'name'       => 'Contact Us',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ], 'name');
    }
}
