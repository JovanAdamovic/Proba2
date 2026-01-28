<?php

namespace Database\Seeders;

use App\Models\Predmet;
use App\Models\User;
use Illuminate\Database\Seeder;

class PredmetSeeder extends Seeder
{
    public function run(): void
    {
        $profesor = User::where('uloga', 'PROFESOR')->first();

        Predmet::insert([
            [
                'profesor_id' => $profesor?->id,
                'naziv' => 'Internet tehnologije',
                'sifra' => 'IT2025',
                'godina_studija' => 3,
            ],
            [
                'profesor_id' => $profesor?->id,
                'naziv' => 'Baze podataka',
                'sifra' => 'BP2025',
                'godina_studija' => 2,
            ],
            [
                'profesor_id' => $profesor?->id,
                'naziv' => 'Softversko inženjerstvo',
                'sifra' => 'SI2025',
                'godina_studija' => 3,
            ],
        ]);
    }
}
