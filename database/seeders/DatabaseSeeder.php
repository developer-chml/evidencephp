<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

         \App\Models\User::factory()->create([
             'name' => 'Administrador',
             'email' => 'carloshenriquemendeslopes@outlook.com',
             'password' => bcrypt("A1B2C3D4E5"),
         ]);
    }
}
