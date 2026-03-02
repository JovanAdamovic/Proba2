<?php

namespace App\Http\Controllers;

use App\Models\Predmet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\PredmetResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class PredmetController extends Controller
{
    #[OA\Get(
        path: "/api/predmeti",
        summary: "Lista predmeta (isti rezultat kao /predmeti/moji). Vraća predmete u skladu sa ulogom korisnika.",
        tags: ["Predmeti"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Lista predmeta"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function index()
    {
        return $this->moji();
    }

    #[OA\Get(
        path: "/api/predmeti/moji",
        summary: "Moji predmeti. STUDENT: upisani predmeti; PROFESOR: predmeti koje predaje; ADMIN: svi predmeti.",
        tags: ["Predmeti"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Lista predmeta"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function moji()
    {
        $user = auth()->user();

        $hasPivot = Schema::hasTable('predmet_profesor');
        $relations = ['profesor', 'studenti'];
        if ($hasPivot) {
            $relations[] = 'profesori';
        }

        if ($user->uloga === 'STUDENT') {
            return PredmetResource::collection(
                $user->predmeti()->with($relations)->get()
            );
        }

        if ($user->uloga === 'PROFESOR') {
            $query = Predmet::where('profesor_id', $user->id);

            if ($hasPivot) {
                $query->orWhereHas('profesori', function ($subquery) use ($user) {
                    $subquery->where('users.id', $user->id);
                });
            }

            return PredmetResource::collection(
                $query->with($relations)->get()
            );
        }

        return PredmetResource::collection(
            Predmet::with($relations)->get()
        );
    }

    #[OA\Get(
        path: "/api/predmeti/{id}",
        summary: "Detalji predmeta. ADMIN: može sve. STUDENT: samo ako je upisan. PROFESOR: samo ako predaje predmet.",
        tags: ["Predmeti"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID predmeta",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalji predmeta"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno"),
            new OA\Response(response: 404, description: "Predmet nije pronađen")
        ]
    )]
    public function show($id)
    {
        $user = auth()->user();

        $hasPivot = Schema::hasTable('predmet_profesor');
        $relations = ['profesor', 'studenti'];
        if ($hasPivot) {
            $relations[] = 'profesori';
        }

        $predmet = Predmet::with($relations)->findOrFail($id);

        if ($user->uloga === 'ADMIN') {
            return new PredmetResource($predmet);
        }

        if ($user->uloga === 'STUDENT') {
            $upisan = $user->predmeti()
                ->where('predmeti.id', $predmet->id)
                ->exists();

            if (!$upisan) {
                return response()->json(['message' => 'Zabranjeno'], 403);
            }
        }

        if ($user->uloga === 'PROFESOR') {
            $predaje = (int)$predmet->profesor_id === (int)$user->id;

            if ($hasPivot) {
                $predaje = $predaje || $predmet->profesori->contains('id', $user->id);
            }

            if (!$predaje) {
                return response()->json(['message' => 'Zabranjeno'], 403);
            }
        }

        return new PredmetResource($predmet);
    }

    #[OA\Post(
        path: "/api/predmeti",
        summary: "Kreiranje predmeta (ADMIN). Podržava profesora preko profesor_id ili listu profesora preko profesor_ids, kao i student_ids.",
        tags: ["Predmeti"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["naziv", "sifra", "godina_studija"],
                properties: [
                    new OA\Property(property: "naziv", type: "string", example: "Internet tehnologije"),
                    new OA\Property(property: "sifra", type: "string", example: "ITEH"),
                    new OA\Property(property: "godina_studija", type: "integer", example: 2),

                    new OA\Property(property: "profesor_id", type: "integer", nullable: true, example: 5),
                    new OA\Property(
                        property: "profesor_ids",
                        type: "array",
                        items: new OA\Items(type: "integer"),
                        example: [5, 7]
                    ),
                    new OA\Property(
                        property: "student_ids",
                        type: "array",
                        items: new OA\Items(type: "integer"),
                        example: [10, 11, 12]
                    ),
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Predmet kreiran"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)"),
            new OA\Response(response: 422, description: "Validaciona greška"),
            new OA\Response(response: 500, description: "Tabela predmet_profesor ne postoji / server greška")
        ]
    )]
    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->uloga !== 'ADMIN') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $validator = Validator::make($request->all(), [
            'profesor_id'    => ['nullable', 'exists:users,id'],
            'profesor_ids'   => ['sometimes', 'array'],
            'profesor_ids.*' => ['integer', 'exists:users,id'],
            'student_ids'    => ['sometimes', 'array'],
            'student_ids.*'  => ['integer', 'exists:users,id'],

            'naziv'          => ['required', 'string', 'max:255'],
            'sifra'          => ['required', 'string', 'max:50', 'unique:predmeti,sifra'],
            'godina_studija' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $hasPivot = Schema::hasTable('predmet_profesor');

        $data = $validator->validated();

        $profesorIds = collect($data['profesor_ids'] ?? [])
            ->merge(!empty($data['profesor_id']) ? [$data['profesor_id']] : [])
            ->unique()
            ->values()
            ->all();

        $studentIds = $data['student_ids'] ?? [];

        if (!empty($profesorIds) && !$hasPivot) {
            return response()->json(['message' => 'Tabela predmet_profesor ne postoji. Pokreni migracije.'], 500);
        }

        if (!empty($profesorIds)) {
            $countProfesori = User::whereIn('id', $profesorIds)->where('uloga', 'PROFESOR')->count();
            if ($countProfesori !== count($profesorIds)) {
                return response()->json(['message' => 'Profesori nisu validni.'], 422);
            }
        }

        if (!empty($studentIds)) {
            $countStudenti = User::whereIn('id', $studentIds)->where('uloga', 'STUDENT')->count();
            if ($countStudenti !== count($studentIds)) {
                return response()->json(['message' => 'Studenti nisu validni.'], 422);
            }
        }

        unset($data['profesor_ids'], $data['student_ids']);

        if (!empty($profesorIds)) {
            $data['profesor_id'] = $data['profesor_id'] ?? $profesorIds[0];
        } elseif (array_key_exists('profesor_ids', $request->all()) && !array_key_exists('profesor_id', $data)) {
            $data['profesor_id'] = null;
        }

        $predmet = Predmet::create($data);

        if (!empty($profesorIds) && $hasPivot) {
            $predmet->profesori()->sync($profesorIds);
        }

        if (!empty($studentIds)) {
            $predmet->studenti()->sync($studentIds);
        }

        $loadRelations = ['profesor', 'studenti'];
        if ($hasPivot) {
            $loadRelations[] = 'profesori';
        }

        return response()->json(
            new PredmetResource($predmet->load($loadRelations)),
            201
        );
    }

    #[OA\Put(
        path: "/api/predmeti/{id}",
        summary: "Izmena predmeta (ADMIN). Može menjati naziv/sifra/godina_studija, profesore i studente.",
        tags: ["Predmeti"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID predmeta",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "naziv", type: "string", example: "Internet tehnologije"),
                    new OA\Property(property: "sifra", type: "string", example: "ITEH"),
                    new OA\Property(property: "godina_studija", type: "integer", example: 2),

                    new OA\Property(property: "profesor_id", type: "integer", nullable: true, example: 5),
                    new OA\Property(
                        property: "profesor_ids",
                        type: "array",
                        items: new OA\Items(type: "integer"),
                        example: [5, 7]
                    ),
                    new OA\Property(
                        property: "student_ids",
                        type: "array",
                        items: new OA\Items(type: "integer"),
                        example: [10, 11, 12]
                    ),
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Predmet ažuriran"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)"),
            new OA\Response(response: 404, description: "Predmet nije pronađen"),
            new OA\Response(response: 422, description: "Validaciona greška"),
            new OA\Response(response: 500, description: "Tabela predmet_profesor ne postoji / server greška")
        ]
    )]
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->uloga !== 'ADMIN') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $predmet = Predmet::find($id);
        if (!$predmet) {
            return response()->json(['message' => 'Predmet nije pronađen.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'profesor_id'    => ['sometimes', 'nullable', 'exists:users,id'],
            'profesor_ids'   => ['sometimes', 'array'],
            'profesor_ids.*' => ['integer', 'exists:users,id'],
            'student_ids'    => ['sometimes', 'array'],
            'student_ids.*'  => ['integer', 'exists:users,id'],

            'naziv'          => ['sometimes', 'required', 'string', 'max:255'],
            'sifra'          => ['sometimes', 'required', 'string', 'max:50', Rule::unique('predmeti', 'sifra')->ignore($predmet->id)],
            'godina_studija' => ['sometimes', 'required', 'integer', 'min:1', 'max:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $hasPivot = Schema::hasTable('predmet_profesor');

        $data = $validator->validated();

        $profesorIds = collect($data['profesor_ids'] ?? [])
            ->merge((isset($data['profesor_id']) && $data['profesor_id']) ? [$data['profesor_id']] : [])
            ->unique()
            ->values()
            ->all();

        $studentIds = $data['student_ids'] ?? null;

        if (!empty($profesorIds) && !$hasPivot) {
            return response()->json(['message' => 'Tabela predmet_profesor ne postoji. Pokreni migracije.'], 500);
        }

        if (!empty($profesorIds)) {
            $countProfesori = User::whereIn('id', $profesorIds)->where('uloga', 'PROFESOR')->count();
            if ($countProfesori !== count($profesorIds)) {
                return response()->json(['message' => 'Profesori nisu validni.'], 422);
            }
        }

        if (is_array($studentIds) && !empty($studentIds)) {
            $countStudenti = User::whereIn('id', $studentIds)->where('uloga', 'STUDENT')->count();
            if ($countStudenti !== count($studentIds)) {
                return response()->json(['message' => 'Studenti nisu validni.'], 422);
            }
        }

        unset($data['profesor_ids'], $data['student_ids']);

        if (!empty($profesorIds)) {
            $data['profesor_id'] = $data['profesor_id'] ?? $profesorIds[0];
        } elseif (array_key_exists('profesor_ids', $request->all()) && !array_key_exists('profesor_id', $data)) {
            $data['profesor_id'] = null;
        }

        $predmet->update($data);

        if (!empty($profesorIds) && $hasPivot) {
            $predmet->profesori()->sync($profesorIds);
        } elseif (array_key_exists('profesor_ids', $request->all()) && $hasPivot) {
            $predmet->profesori()->sync([]);
        }

        if (is_array($studentIds)) {
            $predmet->studenti()->sync($studentIds);
        }

        $loadRelations = ['profesor', 'studenti'];
        if ($hasPivot) {
            $loadRelations[] = 'profesori';
        }

        return response()->json(
            new PredmetResource($predmet->load($loadRelations)),
            200
        );
    }

    #[OA\Delete(
        path: "/api/predmeti/{id}",
        summary: "Brisanje predmeta (ADMIN).",
        tags: ["Predmeti"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID predmeta",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Predmet obrisan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Predmet je obrisan.")
                    ],
                    type: "object"
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)"),
            new OA\Response(response: 404, description: "Predmet nije pronađen")
        ]
    )]
    public function destroy($id)
    {
        $user = auth()->user();
        if ($user->uloga !== 'ADMIN') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $predmet = Predmet::find($id);
        if (!$predmet) {
            return response()->json(['message' => 'Predmet nije pronađen.'], 404);
        }

        $predmet->delete();
        return response()->json(['message' => 'Predmet je obrisan.'], 200);
    }
}