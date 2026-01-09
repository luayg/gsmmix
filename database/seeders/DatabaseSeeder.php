<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            SpatieDemoSeeder::class,
        ]);
        // User::factory(10)->create();
     $this->call(DemoRolesSeeder::class);
    $this->call(AssignAdminRoleSeeder::class);
    $this->call(RbacSeeder::class);
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
