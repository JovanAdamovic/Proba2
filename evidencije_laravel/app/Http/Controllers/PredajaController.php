<?php

namespace App\Http\Controllers;

use App\Http\Resources\PredajaResource;
use App\Models\Predaja;
use App\Models\Predmet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class PredajaController extends Controller
{

    #[OA\Get(
        path: "/api/predaje",
        summary: "Lista predaja. ADMIN: sve; STUDENT: moje; PROFESOR: predaje za moje predmete.",
        tags: ["Predaje"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Lista predaja"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function index()
    {

        $user = auth()->user();

        if ($user->uloga === 'ADMIN') {
            return PredajaResource::collection(
                Predaja::with(['student', 'zadatak.predmet', 'proveraPlagijata'])->get()
            );
        }

        if ($user->uloga === 'STUDENT') {
            return $this->moje();
        }

        return $this->zaMojePredmete();
    }

    #[OA\Get(
        path: "/api/predaje/{id}",
        summary: "Detalji predaje. ADMIN: sve; STUDENT: samo svoje; PROFESOR: samo za svoje predmete.",
        tags: ["Predaje"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID predaje",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalji predaje"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno"),
            new OA\Response(response: 404, description: "Predaja nije pronađena"),
            new OA\Response(response: 409, description: "Predaja nema vezan predmet")
        ]
    )]

    public function show($id)
    {
        $user = auth()->user();


        $predaja = Predaja::with(['student', 'zadatak.predmet', 'proveraPlagijata'])
            ->findOrFail($id);

        if ($user->uloga === 'ADMIN') {
            return new PredajaResource($predaja);
        }

        if ($user->uloga === 'STUDENT') {
            if ((int)$predaja->student_id !== (int)$user->id) {
                return response()->json(['message' => 'Zabranjeno'], 403);
            }
        }

        if ($user->uloga === 'PROFESOR') {
            $predmetId = $predaja->zadatak?->predmet_id;

            if (!$predmetId) {
                return response()->json(['message' => 'Predaja nema vezan predmet.'], 409);
            }

            $predmetJeNjegov = Predmet::where('id', $predmetId)
                ->where('profesor_id', $user->id)
                ->exists();

            if (!$predmetJeNjegov) {
                return response()->json(['message' => 'Zabranjeno'], 403);
            }
        }

        return new PredajaResource($predaja);
    }
     
    #[OA\Get(
        path: "/api/predaje/{id}/file",
        summary: "Preuzimanje fajla predaje. STUDENT: samo svoje; PROFESOR: samo za svoje predmete; ADMIN: sve.",
        tags: ["Predaje"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID predaje",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Fajl predaje (binary)"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno"),
            new OA\Response(response: 404, description: "Predaja nema fajl ili fajl nije pronađen"),
            new OA\Response(response: 409, description: "Predaja nema vezan predmet")
        ]
    )]

    public function file($id)
    {
        $user = auth()->user();

        $predaja = Predaja::with(['student', 'zadatak.predmet', 'proveraPlagijata'])
            ->findOrFail($id);

        if (!$predaja->file_path) {
            return response()->json(['message' => 'Predaja nema fajl.'], 404);
        }

        if ($user->uloga === 'STUDENT' && (int) $predaja->student_id !== (int) $user->id) {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        if ($user->uloga === 'PROFESOR') {
            $predmetId = $predaja->zadatak?->predmet_id;

            if (!$predmetId) {
                return response()->json(['message' => 'Predaja nema vezan predmet.'], 409);
            }

            $predmetJeNjegov = Predmet::where('id', $predmetId)
                ->where('profesor_id', $user->id)
                ->exists();

            if (!$predmetJeNjegov) {
                return response()->json(['message' => 'Zabranjeno'], 403);
            }
        }

        if (!Storage::disk('public')->exists($predaja->file_path)) {
            return response()->json(['message' => 'Fajl nije pronađen.'], 404);
        }

        return response()->file(Storage::disk('public')->path($predaja->file_path));
    }

     
    #[OA\Post(
        path: "/api/predaje",
        summary: "Kreiranje predaje (STUDENT). Upload fajla je opcioni (multipart/form-data).",
        tags: ["Predaje"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["zadatak_id"],
                    properties: [
                        new OA\Property(property: "zadatak_id", type: "integer", example: 10),
                        new OA\Property(
                            property: "file",
                            type: "string",
                            format: "binary",
                            description: "Fajl predaje (pdf/doc/docx/txt/zip, max 10MB)"
                        ),
                        new OA\Property(
                            property: "file_path",
                            type: "string",
                            nullable: true,
                            example: "predaje/abc123.pdf",
                            description: "Ako se ne šalje fajl, može se proslediti postojeća putanja (opciono)."
                        )
                    ],
                    type: "object"
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Predaja kreirana"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno (nije student / nije upisan)"),
            new OA\Response(response: 409, description: "Već postoji predaja za ovaj zadatak"),
            new OA\Response(response: 422, description: "Validaciona greška")
        ]
    )]

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->uloga !== 'STUDENT') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $validator = Validator::make($request->all(), [
            'zadatak_id' => 'required|integer|exists:zadaci,id',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt,zip|max:10240',
            'file_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $zadatakId = $request->zadatak_id;

        $upisan = $user->predmeti()
            ->whereHas('zadaci', fn($q) => $q->where('zadaci.id', $zadatakId))
            ->exists();

        if (!$upisan) {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $vecPostoji = Predaja::where('student_id', $user->id)
            ->where('zadatak_id', $zadatakId)
            ->exists();
        if ($vecPostoji) {
            return response()->json(['message' => 'Već postoji predaja za ovaj zadatak.'], 409);
        }

        $filePath = $request->file_path;

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('predaje', 'public');
        }

        $predaja = Predaja::create([
            'zadatak_id' => $zadatakId,
            'student_id' => $user->id,
            'status' => 'PREDATO',
            'file_path' => $filePath,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Predaja je uspešno kreirana.',
            'data' => new PredajaResource($predaja->load(['student', 'zadatak.predmet'])),
        ], 201);
    }

    #[OA\Put(
        path: "/api/predaje/{id}",
        summary: "Ažuriranje predaje (PROFESOR ili ADMIN). Može menjati status/ocenu/komentar i opcionalno uploadovati novi fajl.",
        tags: ["Predaje"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID predaje",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: "status",
                            type: "string",
                            nullable: true,
                            example: "OCENJENO",
                            description: "Dozvoljene vrednosti: PREDATO, OCENJENO, VRAĆENO, ZAKAŠNJENO"
                        ),
                        new OA\Property(property: "ocena", type: "number", format: "float", nullable: true, example: 9.5),
                        new OA\Property(property: "komentar", type: "string", nullable: true, example: "Dobar rad, sitne ispravke."),
                        new OA\Property(
                            property: "file",
                            type: "string",
                            format: "binary",
                            description: "Novi fajl (pdf/doc/docx/txt/zip, max 10MB)"
                        ),
                        new OA\Property(property: "file_path", type: "string", nullable: true, example: "predaje/novi.pdf"),
                        new OA\Property(property: "submitted_at", type: "string", format: "date-time", nullable: true, example: "2026-03-02 12:30:00")
                    ],
                    type: "object"
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Predaja ažurirana"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno"),
            new OA\Response(response: 404, description: "Predaja nije pronađena"),
            new OA\Response(response: 422, description: "Validaciona greška")
        ]
    )]


    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $predaja = Predaja::with('zadatak.predmet')->findOrFail($id);

        if ($user->uloga === 'STUDENT') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        if ($user->uloga === 'PROFESOR') {
            $predmetId = $predaja->zadatak?->predmet_id;
            $predmetJeNjegov = Predmet::where('id', $predmetId)->where('profesor_id', $user->id)->exists();
            if (!$predmetJeNjegov) return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $allowedStatus = ['PREDATO', 'OCENJENO', 'VRAĆENO', 'ZAKAŠNJENO'];

        $validator = Validator::make($request->all(), [

            'status' => ['sometimes', Rule::in($allowedStatus)],
            'ocena' => 'sometimes|nullable|numeric|min:0|max:10',
            'komentar' => 'sometimes|nullable|string',
            'file' => 'sometimes|file|mimes:pdf,doc,docx,txt,zip|max:10240',
            'file_path' => 'sometimes|string|max:255',
            'submitted_at' => 'sometimes|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $data = $validator->validated();

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('predaje', 'public');
        }

        $predaja->update($data);

        return response()->json(
            new PredajaResource($predaja->load(['student', 'zadatak'])),
            200
        );
    }

    #[OA\Delete(
        path: "/api/predaje/{id}",
        summary: "Brisanje predaje. STUDENT: samo svoje i ako nije OCENJENO; PROFESOR: samo za svoje predmete; ADMIN: sve.",
        tags: ["Predaje"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID predaje",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Predaja obrisana"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Zabranjeno"),
            new OA\Response(response: 404, description: "Predaja nije pronađena"),
            new OA\Response(response: 409, description: "Predaja je već ocenjena / nema vezan predmet")
        ]
    )]

    public function destroy($id)
    {
        $user = auth()->user();
        $predaja = Predaja::with('zadatak.predmet')->find($id);
        if (!$predaja) {
            return response()->json(['message' => 'Predaja nije pronađena.'], 404);
        }

        if ($user->uloga === 'STUDENT') {
            if ((int)$predaja->student_id !== (int)$user->id) {
                return response()->json(['message' => 'Zabranjeno'], 403);
            }

            if ($predaja->status === 'OCENJENO') {
                return response()->json(['message' => 'Predaja je već ocenjena.'], 409);
            }
        }

        if ($user->uloga === 'PROFESOR') {
            $predmetId = $predaja->zadatak?->predmet_id;
            if (!$predmetId) {
                return response()->json(['message' => 'Predaja nema vezan predmet.'], 409);
            }

            $predmetJeNjegov = Predmet::where('id', $predmetId)
                ->where('profesor_id', $user->id)
                ->exists();

            if (!$predmetJeNjegov) {
                return response()->json(['message' => 'Zabranjeno'], 403);
            }
        }

        $predaja->delete();

        return response()->json(['message' => 'Predaja je uspešno obrisana.'], 200);
    }

    #[OA\Get(
        path: "/api/predaje/moje",
        summary: "Moje predaje (STUDENT).",
        tags: ["Predaje"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Lista mojih predaja"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]


    public function moje()
    {
        $user = auth()->user();

        return PredajaResource::collection(
            Predaja::with(['student', 'zadatak'])
                ->where('student_id', $user->id)
                ->get()
        );
    }

    #[OA\Get(
        path: "/api/predaje/za-moje-predmete",
        summary: "Predaje za moje predmete (PROFESOR).",
        tags: ["Predaje"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Lista predaja"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]


    public function zaMojePredmete()
    {
        $user = auth()->user();

        return PredajaResource::collection(
            Predaja::with(['student', 'zadatak.predmet', 'proveraPlagijata'])
                ->whereHas('zadatak.predmet', function ($q) use ($user) {
                    $q->where('profesor_id', $user->id);
                })
                ->get()
        );
    }

    
}
