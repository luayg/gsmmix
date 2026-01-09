<?php
// [انسخ]
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Group;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $g1 = Group::firstOrCreate(['name' => 'Administrator']);
        $g2 = Group::firstOrCreate(['name' => 'Basic']);

        // اضمن وجود أدمن
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'group_id' => $g1->id,
                'balance'  => 3347.71,
                'status'   => 'active',
            ]
        );

        // 20 مستخدم تجريبي
        for ($i=1; $i<=20; $i++) {
            User::firstOrCreate(
                ['email' => "user{$i}@example.com"],
                [
                    'name' => "User {$i}",
                    'username' => 'u'.Str::padLeft($i, 3, '0'),
                    'password' => Hash::make('password'),
                    'group_id' => $i % 2 ? $g2->id : $g1->id,
                    'balance'  => mt_rand(0, 20000)/100,
                    'status'   => $i % 7 === 0 ? 'inactive' : 'active',
                ]
            );
        }
    }
}
