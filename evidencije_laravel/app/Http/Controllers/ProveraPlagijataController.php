<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProveraPlagijataResource;
use App\Models\Predaja;
use App\Models\Predmet;
use App\Models\ProveraPlagijata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class ProveraPlagijataController extends Controller
{
    private function proveriProfesora()
    {
        $user = auth()->user();
        if (!$user || $user->uloga !== 'PROFESOR') {
            abort(response()->json(['message' => 'Zabranjeno'], 403));
        }
        return $user;
    }

    #[OA\Get(
        path: "/api/provere-plagijata",
        summary: "Lista provera plagijata za predaje na predmetima profesora (samo PROFESOR)",
        tags: ["Provere plagijata"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Uspešno vraćena lista provera"
            ),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije PROFESOR)")
        ]
    )]
    public function index()
    {
        $user = $this->proveriProfesora();

        return ProveraPlagijataResource::collection(
            ProveraPlagijata::with(['predaja.zadatak.predmet'])
                ->whereHas('predaja.zadatak.predmet', fn($q) => $q->where('profesor_id', $user->id))
                ->get()
        );
    }

    #[OA\Get(
        path: "/api/provere-plagijata/{id}",
        summary: "Detalji provere plagijata (samo PROFESOR i samo za svoje predmete)",
        tags: ["Provere plagijata"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID provere plagijata",
                schema: new OA\Schema(type: "integer"),
                example: 5
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Uspešno vraćena provera"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije PROFESOR ili nije njegov predmet)"),
            new OA\Response(response: 404, description: "Provera nije pronađena")
        ]
    )]
    public function show($id)
    {
        $user = $this->proveriProfesora();

        $provera = ProveraPlagijata::with(['predaja.zadatak.predmet'])->findOrFail($id);

        $predmetId = $provera->predaja?->zadatak?->predmet_id;

        $ok = Predmet::where('id', $predmetId)
            ->where('profesor_id', $user->id)
            ->exists();

        if (!$ok) return response()->json(['message' => 'Zabranjeno'], 403);

        return new ProveraPlagijataResource($provera);
    }

    // Ove rute ne koristiš, ali ostavljamo da Swagger pokaže da su zabranjene
    #[OA\Post(
        path: "/api/provere-plagijata",
        summary: "Nije dozvoljeno (403)",
        tags: ["Provere plagijata"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 403, description: "Zabranjeno")]
    )]
    public function store(Request $request)
    {
        return response()->json(['message' => 'Zabranjeno'], 403);
    }

    #[OA\Put(
        path: "/api/provere-plagijata/{id}",
        summary: "Nije dozvoljeno (403)",
        tags: ["Provere plagijata"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer"),
                example: 5
            )
        ],
        responses: [new OA\Response(response: 403, description: "Zabranjeno")]
    )]
    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Zabranjeno'], 403);
    }

    #[OA\Delete(
        path: "/api/provere-plagijata/{id}",
        summary: "Nije dozvoljeno (403)",
        tags: ["Provere plagijata"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer"),
                example: 5
            )
        ],
        responses: [new OA\Response(response: 403, description: "Zabranjeno")]
    )]
    public function destroy($id)
    {
        return response()->json(['message' => 'Zabranjeno'], 403);
    }

    #[OA\Post(
        path: "/api/predaje/{predajaId}/provera-plagijata",
        summary: "Pokretanje provere plagijata za predaju (samo PROFESOR i samo za svoje predmete)",
        tags: ["Provere plagijata"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "predajaId",
                in: "path",
                required: true,
                description: "ID predaje",
                schema: new OA\Schema(type: "integer"),
                example: 12
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Provera već postoji (vraća postojeći rezultat)",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "predaja_id", type: "integer", example: 12),
                        new OA\Property(property: "procenat_slicnosti", type: "number", format: "float", example: 23.5),
                        new OA\Property(property: "status", type: "string", example: "ZAVRSENO"),
                    ]
                )
            ),
            new OA\Response(
                response: 201,
                description: "Provera uspešno kreirana",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "predaja_id", type: "integer", example: 12),
                        new OA\Property(property: "procenat_slicnosti", type: "number", format: "float", example: 12.0),
                        new OA\Property(property: "status", type: "string", example: "ZAVRSENO"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije PROFESOR ili nije njegov predmet)"),
            new OA\Response(response: 404, description: "Predaja nije pronađena"),
            new OA\Response(response: 500, description: "Plagiarism API nije konfigurisan"),
            new OA\Response(response: 502, description: "Greška pri komunikaciji sa eksternim API-jem / nevažeći odgovor")
        ]
    )]
    public function pokreni($predajaId)
    {
        $user = auth()->user();

        if ($user->uloga !== 'PROFESOR') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $predaja = Predaja::with(['zadatak.predmet'])->findOrFail($predajaId);

        $predmetId = $predaja->zadatak?->predmet_id;

        $ok = Predmet::where('id', $predmetId)
            ->where('profesor_id', $user->id)
            ->exists();

        if (!$ok) {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $postojeca = ProveraPlagijata::where('predaja_id', $predajaId)->first();
        if ($postojeca) {
            $line = "Provera plagijata: {$postojeca->procenat_slicnosti}% ({$postojeca->status})";

            $trenutni = $predaja->komentar ?? '';
            if (stripos($trenutni, 'Provera plagijata:') === false) {
                $novi = trim($trenutni);
                $novi = $novi ? ($novi . "\n" . $line) : $line;
                $predaja->update(['komentar' => $novi]);
            }

            return response()->json([
                'predaja_id' => (int) $predajaId,
                'procenat_slicnosti' => (float) $postojeca->procenat_slicnosti,
                'status' => $postojeca->status,
            ], 200);
        }

        $apiUrl = config('services.plagiarism_api.url');
        $apiToken = config('services.plagiarism_api.token');

        if (!$apiUrl) {
            return response()->json(['message' => 'Plagiarism API nije konfigurisan.'], 500);
        }

        $payload = [
            'predaja_id' => $predaja->id,
            'file_path' => $predaja->file_path,
            'student_id' => $predaja->student_id,
            'zadatak_id' => $predaja->zadatak_id,
        ];

        $request = Http::timeout(15);
        if ($apiToken) {
            $request = $request->withToken($apiToken);
        }

        $response = $request->post($apiUrl, $payload);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Provera plagijata nije uspela.',
                'status' => $response->status(),
            ], 502);
        }

        $data = $response->json();
        $procenat = $data['procenat_slicnosti'] ?? $data['similarity'] ?? null;

        if ($procenat === null) {
            return response()->json([
                'message' => 'Nevažeći odgovor API-ja za plagijat.',
            ], 502);
        }

        $provera = ProveraPlagijata::create([
            'predaja_id' => $predajaId,
            'procenat_slicnosti' => $procenat,
            'status' => 'ZAVRSENO',
        ]);

        $line = "Provera plagijata: {$provera->procenat_slicnosti}% ({$provera->status})";
        $trenutni = $predaja->komentar ?? '';
        $novi = trim($trenutni);
        $novi = $novi ? ($novi . "\n" . $line) : $line;

        $predaja->update([
            'komentar' => $novi
        ]);

        return response()->json([
            'predaja_id' => (int) $predajaId,
            'procenat_slicnosti' => (float) $provera->procenat_slicnosti,
            'status' => $provera->status,
        ], 201);
    }
}