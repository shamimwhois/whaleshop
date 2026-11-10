<?php

namespace Database\Seeders;


use App\Enums\Ask;
use App\Models\Currency;
use Dipokhalder\EnvEditor\EnvEditor;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\IdempotentSeeder;


class CurrencyTableSeeder extends Seeder
{
    use IdempotentSeeder;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->insertMissing('currencies', [
            [
                'name' => 'Dollars',
                'symbol' => '$',
                'code' => 'USD',
                'is_cryptocurrency' => Ask::NO,
                'exchange_rate' => 1
            ],
        ], 'code');

        $envService = new EnvEditor();
        if ($envService->getValue('DEMO')) {
            $this->insertMissing('currencies', [
                [
                    'name' => 'Rupee',
                    'symbol' => '₹',
                    'code' => 'INR',
                    'is_cryptocurrency' => Ask::NO,
                    'exchange_rate' => 1
                ],
                [
                    'name' => 'Taka',
                    'symbol' => '৳',
                    'code' => 'BDT',
                    'is_cryptocurrency' => Ask::NO,
                    'exchange_rate' => 1
                ],
                [
                    'name' => 'Naira',
                    'symbol' => '₦',
                    'code' => 'NGN',
                    'is_cryptocurrency' => Ask::NO,
                    'exchange_rate' => 1
                ],
                [
                    'name' => 'Peso',
                    'symbol' => '₱',
                    'code' => 'ARS',
                    'is_cryptocurrency' => Ask::NO,
                    'exchange_rate' => 1
                ],
            ], 'code');
        }
    }
}
