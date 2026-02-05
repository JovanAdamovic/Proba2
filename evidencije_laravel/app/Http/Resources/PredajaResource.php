<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredajaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'zadatak_id' => $this->zadatak_id,
            'student_id' => $this->student_id,

            'status' => $this->status,
            'ocena' => $this->ocena,
            'komentar' => $this->komentar,
            'file_path' => $this->file_path,
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),

            'zadatak' => $this->whenLoaded('zadatak', fn() => [
                'id' => $this->zadatak->id,
                'naslov' => $this->zadatak->naslov,
                'rok_predaje' => $this->zadatak->rok_predaje,
                'predmet_id' => $this->zadatak->predmet_id,
            ]),

            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'ime' => $this->student->ime,
                'prezime' => $this->student->prezime,
                'email' => $this->student->email,
            ]),

            'provera_plagijata' => $this->when(
                auth()->check() && auth()->user()->uloga === 'PROFESOR' && $this->relationLoaded('proveraPlagijata'),
                fn() => [
                    'id' => $this->proveraPlagijata?->id,
                    'status' => $this->proveraPlagijata?->status,
                    'procenat_slicnosti' => $this->proveraPlagijata?->procenat_slicnosti !== null
                        ? (float) $this->proveraPlagijata->procenat_slicnosti
                        : null,
                ]
            ),


            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
