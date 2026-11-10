<?php

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\ExchangeReason;
use Illuminate\Database\Seeder;
use Dipokhalder\EnvEditor\EnvEditor;

class ExchangeReasonTableSeeder extends Seeder
{

    public array $exchangeReasons = [
        'Ordered the wrong product or size',
        'Shipped the wrong product or size',
        'The product was damaged or defective',
        'The product did not match the description',
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $envService = new EnvEditor();
        if ($envService->getValue('DEMO')) {
            foreach ($this->exchangeReasons as $exchangeReason) {
                ExchangeReason::create([
                    'title'   => $exchangeReason,
                    'details' => "There is a great divergence between the way a product is depicted by the merchant and the actual appearance or functionality of the product. I want to exchange it",
                    'status'  => Status::ACTIVE,
                ]);
            }
        }
    }
}
