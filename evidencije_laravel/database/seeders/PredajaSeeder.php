<?php

namespace Database\Seeders;

use App\Models\Predaja;
use App\Models\User;
use App\Models\Zadatak;
use Illuminate\Database\Seeder;

class PredajaSeeder extends Seeder
{
    public function run(): void
    {
        $studenti = User::where('uloga', 'STUDENT')->get();
        $zadaci = Zadatak::all();

        if ($studenti->isEmpty() || $zadaci->isEmpty()) {
            return;
        }

        foreach ($zadaci->take(10) as $zadatak) {
            $student = $studenti->random();

            Predaja::create([
                'student_id'   => $student->id,     
                'zadatak_id'   => $zadatak->id,
                'status'       => 'PREDATO',
                'ocena'        => null,
                'komentar'     => null,
                'file_path'    => 'radovi/test.pdf', 
                'submitted_at' => now()->subDays(rand(0, 5)),
            ]);
        }
    }
}
