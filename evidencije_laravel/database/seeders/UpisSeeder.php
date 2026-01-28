<?php

namespace Database\Seeders;

use App\Models\Upis;
use App\Models\User;
use App\Models\Predmet;
use Illuminate\Database\Seeder;

class UpisSeeder extends Seeder
{
    public function run(): void
    {
        $studenti = User::where('uloga', 'STUDENT')->get();
        $predmeti = Predmet::all();

        foreach ($studenti as $student) {
            foreach ($predmeti as $predmet) {
                Upis::create([
                    'student_id' => $student->id,
                    'predmet_id' => $predmet->id,
                ]);
            }
        }
    }
}
