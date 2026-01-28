<?php

namespace Database\Seeders;

use App\Models\Predmet;
use App\Models\User;
use App\Models\Zadatak;
use Illuminate\Database\Seeder;

class ZadatakSeeder extends Seeder
{
    public function run(): void
    {
        $predmeti = Predmet::all();

        $profesor = User::where('uloga', 'PROFESOR')->first();

        foreach ($predmeti as $predmet) {
            for ($i = 1; $i <= 3; $i++) {
                Zadatak::create([
                    'predmet_id'   => $predmet->id,
                    'profesor_id'  => $profesor->id, 
                    'naslov'       => "Zadatak $i",   
                    'opis'         => "Opis zadatka $i za predmet {$predmet->naziv}",
                    'rok_predaje'  => now()->addDays(7 + $i),
                ]);
            }
        }
    }
}
