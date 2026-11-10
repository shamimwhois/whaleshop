<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMedia;

use App\Enums\Status;
use App\Enums\Activity;
use App\Enums\InputType;
use App\Models\SocialLogin;
use App\Models\GatewayOption;
use Illuminate\Database\Seeder;

class SocialLoginTableSeeder extends Seeder
{
    use SeedsMedia;

    /**
     * Run the database seeds.
     *
     * @return void
     */

    public array $provider_datas = [
        [
            "name"    => "google",
            "slug"    => "google",
            "misc"    => null,
            "status"  => Activity::DISABLE,
            "options" => [
                [
                    "option"     => 'google_client_id',
                    "type"       => InputType::TEXT,
                    "activities" => '',
                ],
                [
                    "option"     => 'google_client_secret',
                    "type"       => InputType::TEXT,
                    "activities" => '',
                ],
                [
                    "option"     => 'google_status',
                    "value"      => Activity::DISABLE,
                    "type"       => InputType::SELECT,
                    "activities" => [
                        Activity::ENABLE  => "enable",
                        Activity::DISABLE => "disable",
                    ],
                ],
            ]
        ],
    ];

    public function run(): void
    {
        foreach ($this->provider_datas as $provider_data) {
            $provider = SocialLogin::firstOrCreate(['slug' => $provider_data['slug']], [
                'name'   => $provider_data['name'],
                'slug'   => $provider_data['slug'],
                'misc'   => json_encode($provider_data['misc']),
                'status' => $provider_data['status'],
            ]);
            if ($provider->wasRecentlyCreated && file_exists(public_path('/images/seeder/social-login/' . strtolower(str_replace(' ', '_', $provider_data['slug'])) . '.png'))) {
                $this->attachMedia($provider, public_path('/images/seeder/social-login/' . strtolower(str_replace(' ', '_', $provider_data['slug'])) . '.png'), 'social-login');
            }
            $this->gatewayOption($provider->id, $provider_data['options']);
        }
    }

    public function gatewayOption($id, $options): void
    {
        foreach ($options as $option) {                GatewayOption::firstOrCreate([
                    'model_id'   => $id,
                    'model_type' => 'App\Models\SocialLogin',
                    'option'     => $option['option'],
                ], [
                    'model_id'   => $id,
                    'model_type' => 'App\Models\SocialLogin',
                    'option'     => $option['option'],
                'value'      => $option['value'] ?? "",
                'type'       => $option['type'],
                'activities' => json_encode($option['activities'])
            ]);
        }
    }
}
