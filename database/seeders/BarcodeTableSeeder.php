<?php

namespace Database\Seeders;

use App\Models\Barcode;
use Illuminate\Database\Seeder;
use Dipokhalder\EnvEditor\EnvEditor;
use Database\Seeders\Concerns\IdempotentSeeder;

class BarcodeTableSeeder extends Seeder
{
    use IdempotentSeeder;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->insertMissing('barcodes', [
            [
                'name'       => 'EAN-13',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name'       => 'UPC-A',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ], 'name');
    }
}
