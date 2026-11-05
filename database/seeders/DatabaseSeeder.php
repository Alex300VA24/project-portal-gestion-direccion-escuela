<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = [
            ['role' => 'director_escuela', 'name' => 'Director de Escuela', 'email' => 'director@portal.local'],
            ['role' => 'secretaria', 'name' => 'Secretaría Académica', 'email' => 'secretaria@portal.local'],
            ['role' => 'profesor', 'name' => 'Profesor Demo', 'email' => 'profesor@portal.local'],
            ['role' => 'alumno', 'name' => 'Alumno Demo', 'email' => 'alumno@portal.local'],
        ];

        foreach ($accounts as $account) {
            $role = Role::firstOrCreate([
                'name' => $account['role'],
                'guard_name' => 'web',
            ]);

            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('Portal2026!'),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
