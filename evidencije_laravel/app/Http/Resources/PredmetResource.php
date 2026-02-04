<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredmetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profesori = collect();

        if ($this->relationLoaded('profesori')) {
            $profesori = $profesori->merge(
                $this->profesori->map(function ($profesor) {
                    return [
                        'id' => $profesor->id,
                        'ime' => $profesor->ime,
                        'prezime' => $profesor->prezime,
                        'email' => $profesor->email,
                    ];
                })
            );
        }

        if ($this->relationLoaded('profesor') && $this->profesor) {
            $profesori->push([
                'id' => $this->profesor->id,
                'ime' => $this->profesor->ime,
                'prezime' => $this->profesor->prezime,
                'email' => $this->profesor->email,
            ]);
        }

        $user = $request->user();
        $ulogovaniProfesorId = null;

        if ($user && $user->uloga === 'PROFESOR') {
            $predaje = ((int)$this->profesor_id === (int)$user->id)
                || $profesori->contains(function ($p) use ($user) {
                    return (int)$p['id'] === (int)$user->id;
                });

            $ulogovaniProfesorId = $predaje ? $user->id : null;
        }

        return [
            'id' => $this->id,
            'naziv' => $this->naziv,
            'sifra' => $this->sifra,
            'godina_studija' => $this->godina_studija,

            'profesor_id' => $this->profesor_id,

            'ulogovani_profesor_id' => $ulogovaniProfesorId,

            'profesori' => $profesori->unique('id')->values(),

            'studenti' => $this->whenLoaded('studenti', function () {
                return $this->studenti->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'ime' => $student->ime,
                        'prezime' => $student->prezime,
                        'email' => $student->email,
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
