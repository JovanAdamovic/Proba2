<?php

namespace App\Http\Controllers;

use App\Http\Resources\ZadatakResource;
use App\Models\Predmet;
use App\Models\Zadatak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class ZadatakController extends Controller
{
    #[OA\Get(
        path: "/api/zadaci",
        summary: "Lista zadataka dostupnih ulogovanom korisniku (ADMIN: svi, STUDENT: za upisane predmete, PROFESOR: njegovi zadaci)",
        tags: ["Zadaci"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Lista zadataka"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
        ]
    )]
    public function index()
    {
        return $this->moji();
    }

    #[OA\Get(
        path: "/api/zadaci/{id}",
        summary: "Detalji zadatka (ADMIN: bilo koji, STUDENT: samo za upisani predmet, PROFESOR: samo svoj zadatak)",
        tags: ["Zadaci"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID zadatka",
                schema: new OA\Schema(type: "integer"),
                example: 10
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalji zadatka"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno"),
            new OA\Response(response: 404, description: "Zadatak nije pronađen")
        ]
    )]
    public function show($id)
    {
        $user = auth()->user();

        $zadatak = Zadatak::with(['predmet', 'profesor'])->findOrFail($id);

        if ($user->uloga === 'ADMIN') return new ZadatakResource($zadatak);

        if ($user->uloga === 'STUDENT') {
            $upisan = $user->predmeti()->where('predmeti.id', $zadatak->predmet_id)->exists();
            if (!$upisan) return response()->json(['message' => 'Zabranjeno'], 403);
        }

        if ($user->uloga === 'PROFESOR') {
            if ((int)$zadatak->profesor_id !== (int)$user->id) {
                return response()->json(['message' => 'Zabranjeno'], 403);
            }
        }

        return new ZadatakResource($zadatak);
    }

    #[OA\Post(
        path: "/api/zadaci",
        summary: "Kreiranje zadatka (PROFESOR/ADMIN). Profesor može kreirati samo za predmet koji predaje.",
        tags: ["Zadaci"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["predmet_id", "naslov", "rok_predaje"],
                properties: [
                    new OA\Property(property: "predmet_id", type: "integer", example: 1),
                    new OA\Property(property: "naslov", type: "string", example: "Seminarski rad - I faza"),
                    new OA\Property(property: "opis", type: "string", nullable: true, example: "Uraditi ER dijagram i opis poslovnih pravila."),
                    new OA\Property(property: "rok_predaje", type: "string", format: "date-time", example: "2026-03-20 23:59:00")
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Zadatak uspešno kreiran"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno"),
            new OA\Response(response: 422, description: "Validaciona greška")
        ]
    )]
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->uloga, ['PROFESOR', 'ADMIN'])) {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $validator = Validator::make($request->all(), [
            'predmet_id'  => 'required|exists:predmeti,id',
            'naslov'      => 'required|string|max:255',
            'opis'        => 'nullable|string',
            'rok_predaje' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($user->uloga === 'PROFESOR') {
            $query = Predmet::where('id', $request->predmet_id)
                ->where('profesor_id', $user->id);

            if (Schema::hasTable('predmet_profesor')) {
                $query->orWhereHas('profesori', function ($sub) use ($user) {
                    $sub->where('users.id', $user->id);
                });
            }

            $ok = $query->exists();

            if (!$ok) return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $zadatak = Zadatak::create([
            'predmet_id'  => $request->predmet_id,
            'profesor_id' => $user->id,
            'naslov'      => $request->naslov,
            'opis'        => $request->opis,
            'rok_predaje' => $request->rok_predaje,
        ]);

        return response()->json([
            'message' => 'Zadatak je uspešno kreiran.',
            'data'    => new ZadatakResource($zadatak->load(['predmet', 'profesor'])),
        ], 201);
    }

    #[OA\Put(
        path: "/api/zadaci/{id}",
        summary: "Ažuriranje zadatka (PROFESOR/ADMIN). Profesor može menjati samo svoj zadatak; ako menja predmet_id, mora biti predmet koji predaje.",
        tags: ["Zadaci"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID zadatka",
                schema: new OA\Schema(type: "integer"),
                example: 10
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "predmet_id", type: "integer", example: 1),
                    new OA\Property(property: "naslov", type: "string", example: "Seminarski rad - I faza (izmena)"),
                    new OA\Property(property: "opis", type: "string", nullable: true, example: "Dodati i BPMN dijagram."),
                    new OA\Property(property: "rok_predaje", type: "string", format: "date-time", example: "2026-03-22 23:59:00")
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Zadatak uspešno ažuriran"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno"),
            new OA\Response(response: 404, description: "Zadatak nije pronađen"),
            new OA\Response(response: 422, description: "Validaciona greška")
        ]
    )]
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        $zadatak = Zadatak::with('predmet')->find($id);
        if (!$zadatak) {
            return response()->json(['message' => 'Zadatak nije pronađen.'], 404);
        }

        if ($user->uloga === 'STUDENT') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        if ($user->uloga === 'PROFESOR' && (int)$zadatak->profesor_id !== (int)$user->id) {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $validator = Validator::make($request->all(), [
            'predmet_id'  => 'sometimes|exists:predmeti,id',
            'naslov'      => 'sometimes|string|max:255',
            'opis'        => 'sometimes|nullable|string',
            'rok_predaje' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($user->uloga === 'PROFESOR' && $request->has('predmet_id')) {
            $query = Predmet::where('id', $request->predmet_id)
                ->where('profesor_id', $user->id);

            if (Schema::hasTable('predmet_profesor')) {
                $query->orWhereHas('profesori', function ($sub) use ($user) {
                    $sub->where('users.id', $user->id);
                });
            }

            $ok = $query->exists();

            if (!$ok) return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $zadatak->update($validator->validated());

        return response()->json(
            new ZadatakResource($zadatak->load(['predmet', 'profesor'])),
            200
        );
    }

    #[OA\Delete(
        path: "/api/zadaci/{id}",
        summary: "Brisanje zadatka (samo ADMIN)",
        tags: ["Zadaci"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID zadatka",
                schema: new OA\Schema(type: "integer"),
                example: 10
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Zadatak uspešno obrisan"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)"),
            new OA\Response(response: 404, description: "Zadatak nije pronađen")
        ]
    )]
    public function destroy($id)
    {
        $user = auth()->user();

        if ($user->uloga !== 'ADMIN') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $zadatak = Zadatak::find($id);
        if (!$zadatak) {
            return response()->json(['message' => 'Zadatak nije pronađen.'], 404);
        }

        $zadatak->delete();

        return response()->json(['message' => 'Zadatak je uspešno obrisan.'], 200);
    }

    #[OA\Get(
        path: "/api/zadaci/moji",
        summary: "Moji zadaci (ADMIN: svi, STUDENT: za upisane predmete, PROFESOR: njegovi zadaci)",
        tags: ["Zadaci"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Lista zadataka"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
        ]
    )]
    public function moji()
    {
        $user = auth()->user();

        if ($user->uloga === 'ADMIN') {
            return ZadatakResource::collection(
                Zadatak::with(['predmet', 'profesor'])->get()
            );
        }

        if ($user->uloga === 'STUDENT') {
            return ZadatakResource::collection(
                Zadatak::with(['predmet', 'profesor'])
                    ->whereHas('predmet', function ($q) use ($user) {
                        $q->whereIn('predmeti.id', $user->predmeti()->pluck('predmeti.id'));
                    })
                    ->get()
            );
        }

        return ZadatakResource::collection(
            Zadatak::with(['predmet', 'profesor'])
                ->where('profesor_id', $user->id)
                ->get()
        );
    }
}