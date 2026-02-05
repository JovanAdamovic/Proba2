<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZadatakResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'predmet_id' => $this->predmet_id,
            'profesor_id' => $this->profesor_id,

            'naslov' => $this->naslov,
            'opis' => $this->opis,
            'rok_predaje' => $this->rok_predaje,

            'predmet' => $this->whenLoaded('predmet', fn() => [
                'id' => $this->predmet->id,
                'naziv' => $this->predmet->naziv,
                'sifra' => $this->predmet->sifra,
            ]),

            'profesor' => $this->whenLoaded('profesor', fn() => [
                'id' => $this->profesor->id,
                'ime' => $this->profesor->ime,
                'prezime' => $this->profesor->prezime,
                'email' => $this->profesor->email,
            ]),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
