<?php

namespace Database\Seeders;

use App\Enums\Ask;
use App\Models\User;
use App\Enums\Status;
use App\Models\Address;
use App\Enums\Role as EnumRole;
use Illuminate\Database\Seeder;
use Dipokhalder\EnvEditor\EnvEditor;


class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $envService = new EnvEditor();

        $admin = $this->createUser([
            'name'              => 'John Doe',
            'email'             => 'admin@example.com',
            'phone'             => '1254875855',
            'username'          => 'admin',
            'email_verified_at' => now(),
            'password'          => bcrypt('123456'),
            'status'            => Status::ACTIVE,
            'country_code'      => '+880',
            'is_guest'          => Ask::NO
        ], EnumRole::ADMIN);
        if ($envService->getValue('DEMO')) {
            $this->createAddress($admin, [
                'full_name'    => $admin->name,
                'email'        => $admin->email,
                'country_code' => $admin->country_code,
                'phone'        => $admin->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1209',
                'address'      => 'House :20, Road: 19, Block: B, Dhanmondi 32',
                'user_id'      => $admin->id,
            ]);
            $this->createAddress($admin, [
                'full_name'    => $admin->name,
                'email'        => $admin->email,
                'country_code' => $admin->country_code,
                'phone'        => $admin->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1216',
                'address'      => 'House :31, Road: 9, Block: A, Mirpur 1',
                'user_id'      => $admin->id,
            ]);
        }

        $customer = $this->createUser([
            'name'              => 'Walking Customer',
            'email'             => 'walkingcustomer@example.com',
            'phone'             => '125444455',
            'username'          => 'default-customer',
            'email_verified_at' => now(),
            'password'          => bcrypt('123456'),
            'status'            => Status::ACTIVE,
            'country_code'      => '+880',
            'is_guest'          => Ask::NO
        ], EnumRole::CUSTOMER);
        if ($envService->getValue('DEMO')) {
            $this->createAddress($customer, [
                'full_name'    => $customer->name,
                'email'        => $customer->email,
                'country_code' => $customer->country_code,
                'phone'        => $customer->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1216',
                'address'      => 'House :3, Road: 11, Block: A, Mirpur 12',
                'user_id'      => $customer->id,
            ]);
        }

        if ($envService->getValue('DEMO')) {
            $customerOne = $this->createUser([
                'name'              => 'Will Smith',
                'email'             => 'customer@example.com',
                'phone'             => '125333344',
                'username'          => 'will-smith',
                'email_verified_at' => now(),
                'password'          => bcrypt('123456'),
                'status'            => Status::ACTIVE,
                'country_code'      => '+880',
                'is_guest'          => Ask::NO
            ], EnumRole::CUSTOMER);
            $this->createAddress($customerOne, [
                'full_name'    => $customerOne->name,
                'email'        => $customerOne->email,
                'country_code' => $customerOne->country_code,
                'phone'        => $customerOne->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1216',
                'address'      => 'House :3, Road: 1, Block: C, Mirpur 2',
                'user_id'      => $customerOne->id,
            ]);
            $this->createAddress($customerOne, [
                'full_name'    => $customerOne->name,
                'email'        => $customerOne->email,
                'country_code' => $customerOne->country_code,
                'phone'        => $customerOne->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1209',
                'address'      => 'House :30, Road: 13, Block: A, Dhanmondi 32',
                'user_id'      => $customerOne->id,
            ]);

            $employeeOne = $this->createUser([
                'name'              => 'Kiron Khan',
                'email'             => 'manager@example.com',
                'phone'             => '1275333453',
                'username'          => 'kiron-khan1313',
                'email_verified_at' => now(),
                'password'          => bcrypt('123456'),
                'status'            => Status::ACTIVE,
                'country_code'      => '+880',
                'is_guest'          => Ask::NO
            ], EnumRole::MANAGER);
            $this->createAddress($employeeOne, [
                'full_name'    => $employeeOne->name,
                'email'        => $employeeOne->email,
                'country_code' => $employeeOne->country_code,
                'phone'        => $employeeOne->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1209',
                'address'      => 'House :10, Road: 5, Block: A, Dhanmondi 32',
                'user_id'      => $employeeOne->id,
            ]);

            $employeeTwo = $this->createUser([
                'name'              => 'Shohag Ali',
                'email'             => 'shohag@example.com',
                'phone'             => '1257654433',
                'username'          => 'shohag-ali3324',
                'email_verified_at' => now(),
                'password'          => bcrypt('123456'),
                'status'            => Status::ACTIVE,
                'country_code'      => '+880',
                'is_guest'          => Ask::NO
            ], EnumRole::MANAGER);
            $this->createAddress($employeeTwo, [
                'full_name'    => $employeeTwo->name,
                'email'        => $employeeTwo->email,
                'country_code' => $employeeTwo->country_code,
                'phone'        => $employeeTwo->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1216',
                'address'      => 'House :30, Road: 4, Block: D, Mirpur 11',
                'user_id'      => $employeeTwo->id,
            ]);

            $posOperatorOne = $this->createUser([
                'name'              => 'Farha Israt ',
                'email'             => 'posoperator@example.com',
                'phone'             => '156873641',
                'username'          => 'farha-istat343',
                'email_verified_at' => now(),
                'password'          => bcrypt('123456'),
                'status'            => Status::ACTIVE,
                'country_code'      => '+880',
                'is_guest'          => Ask::NO
            ], EnumRole::POS_OPERATOR);
            $this->createAddress($posOperatorOne, [
                'full_name'    => $posOperatorOne->name,
                'email'        => $posOperatorOne->email,
                'country_code' => $posOperatorOne->country_code,
                'phone'        => $posOperatorOne->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1209',
                'address'      => 'House :30, Road: 4, Block: D, Dhanmondi 11',
                'user_id'      => $posOperatorOne->id,
            ]);

            $posOperatorTwo = $this->createUser([
                'name'              => 'Sahataz Afnan',
                'email'             => 'sahataz@example.com',
                'phone'             => '1249955570',
                'username'          => 'sahataz-afnan232',
                'email_verified_at' => now(),
                'password'          => bcrypt('123456'),
                'status'            => Status::ACTIVE,
                'country_code'      => '+880',
                'is_guest'          => Ask::NO
            ], EnumRole::POS_OPERATOR);
            $this->createAddress($posOperatorTwo, [
                'full_name'    => $posOperatorTwo->name,
                'email'        => $posOperatorTwo->email,
                'country_code' => $posOperatorTwo->country_code,
                'phone'        => $posOperatorTwo->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1216',
                'address'      => 'House :10, Road: 2, Block: C, Mirpur 14',
                'user_id'      => $posOperatorTwo->id,
            ]);

            $stuffOne = $this->createUser([
                'name'              => 'Rohim Miya',
                'email'             => 'stuff@example.com',
                'phone'             => '1222224443',
                'username'          => 'rohim-miya768',
                'email_verified_at' => now(),
                'password'          => bcrypt('123456'),
                'status'            => Status::ACTIVE,
                'country_code'      => '+880',
                'is_guest'          => Ask::NO
            ], EnumRole::STUFF);
            $this->createAddress($stuffOne, [
                'full_name'    => $stuffOne->name,
                'email'        => $stuffOne->email,
                'country_code' => $stuffOne->country_code,
                'phone'        => $stuffOne->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1216',
                'address'      => 'House :11, Road: 12, Block: C, Mirpur 6',
                'user_id'      => $stuffOne->id,
            ]);

            $stuffTwo = $this->createUser([
                'name'              => 'Kala Chan',
                'email'             => 'kala@example.com',
                'phone'             => '1238426043',
                'username'          => 'kala-chan890',
                'email_verified_at' => now(),
                'password'          => bcrypt('123456'),
                'status'            => Status::ACTIVE,
                'country_code'      => '+880',
                'is_guest'          => Ask::NO
            ], EnumRole::STUFF);
            $this->createAddress($stuffTwo, [
                'full_name'    => $stuffTwo->name,
                'email'        => $stuffTwo->email,
                'country_code' => $stuffTwo->country_code,
                'phone'        => $stuffTwo->phone,
                'country'      => "Bangladesh",
                'state'        => 'Dhaka',
                'city'         => 'Dhaka',
                'zip_code'     => '1209',
                'address'      => 'House :1, Road: 2, Block: A, Dhanmondi 27',
                'user_id'      => $stuffTwo->id,
            ]);
        }
    }

    private function createUser(array $attributes, int $role): User
    {
        $user = User::firstOrCreate(['email' => $attributes['email']], $attributes);

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function createAddress(User $user, array $attributes): void
    {
        Address::firstOrCreate([
            'user_id' => $user->id,
            'address' => $attributes['address'],
        ], $attributes);
    }
}
