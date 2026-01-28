<?php

namespace Database\Seeders;

use App\Models\Predaja;
use App\Models\ProveraPlagijata;
use Illuminate\Database\Seeder;

class ProveraPlagijataSeeder extends Seeder
{
    public function run(): void
    {
        $predaje = Predaja::all();

        if ($predaje->isEmpty()) {
            return;
        }

        foreach ($predaje as $predaja) {
            ProveraPlagijata::create([
                'predaja_id' => $predaja->id,
                'procenat_slicnosti' => rand(5, 70), 
                'status' => 'ZAVRSENO',
            ]);
        }
    }
}
