<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo accounts. The support chat expects the support user to have id 999.
     */
    public function run(): void
    {
        $accounts = [
            ['id' => 999, 'first_name' => 'Support', 'last_name' => 'Team', 'email' => 'support@dirlbuzz.test', 'phone' => '0600000999', 'user_type' => 'support'],
            ['id' => 1, 'first_name' => 'Salma', 'last_name' => 'Creator', 'email' => 'creator@dirlbuzz.test', 'phone' => '0600000001', 'user_type' => 'creator'],
            ['id' => 2, 'first_name' => 'Youssef', 'last_name' => 'Business', 'email' => 'business@dirlbuzz.test', 'phone' => '0600000002', 'user_type' => 'business'],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['id' => $account['id']],
                $account + ['password' => Hash::make('password')]
            );

            UserProfile::firstOrCreate(['user_id' => $user->id]);
        }
    }
}
