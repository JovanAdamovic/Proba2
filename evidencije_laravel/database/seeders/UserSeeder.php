<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // PROFESORI
        User::create([
            'ime' => 'Petar',
            'prezime' => 'Petrović',
            'email' => 'profesor@fon.rs',
            'password' => Hash::make('password'),
            'uloga' => 'PROFESOR',
        ]);

        User::create([
            'ime' => 'Nikola',
            'prezime' => 'Nikolić',
            'email' => 'profnikola@fon.rs',
            'password' => Hash::make('password'),
            'uloga' => 'PROFESOR',
        ]);

        // STUDENTI
        User::create([
            'ime' => 'Ana',
            'prezime' => 'Jovanović',
            'email' => 'ana@student.rs',
            'password' => Hash::make('password'),
            'uloga' => 'STUDENT',
        ]);

        User::create([
            'ime' => 'Marko',
            'prezime' => 'Petrović',
            'email' => 'marko@student.rs',
            'password' => Hash::make('password'),
            'uloga' => 'STUDENT',
        ]);

        User::create([
            'ime' => 'Jovana',
            'prezime' => 'Nikolić',
            'email' => 'jovana@student.rs',
            'password' => Hash::make('password'),
            'uloga' => 'STUDENT',
        ]);

        // ADMIN
        User::create([
            'ime' => 'Admin',
            'prezime' => 'Sistema',
            'email' => 'admin@fon.rs',
            'password' => Hash::make('admin123'),
            'uloga' => 'ADMIN',
        ]);
    }
}
