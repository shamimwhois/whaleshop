<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;
use Dipokhalder\EnvEditor\EnvEditor;
use Database\Seeders\Concerns\IdempotentSeeder;

class TaxTableSeeder extends Seeder
{
    use IdempotentSeeder;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $envService = new EnvEditor();
        if ($envService->getValue('DEMO')) {
            $this->insertMissing('taxes', [
                [
                    'name'       => 'No-VAT',
                    'code'       => 'VAT-0%',
                    'tax_rate'   => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name'       => 'VAT-5',
                    'code'       => 'VAT-5%',
                    'tax_rate'   => 5,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name'       => 'VAT-10',
                    'code'       => 'VAT-10%',
                    'tax_rate'   => 10,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name'       => 'VAT-20',
                    'code'       => 'VAT-20%',
                    'tax_rate'   => 20,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
            ], 'code');
        }
    }
}
