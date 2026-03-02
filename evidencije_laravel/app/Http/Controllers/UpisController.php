<?php

namespace App\Http\Controllers;

use App\Http\Resources\UpisResource;
use App\Models\Upis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class UpisController extends Controller
{
    private function proveriAdmina()
    {
        $user = auth()->user();
        if (!$user || $user->uloga !== 'ADMIN') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        return null;
    }

    #[OA\Get(
        path: "/api/upisi",
        summary: "Lista upisa (samo ADMIN)",
        tags: ["Upisi"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Uspešno vraćena lista upisa",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "student_id", type: "integer", example: 7),
                            new OA\Property(property: "predmet_id", type: "integer", example: 3),
                            new OA\Property(
                                property: "student",
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 7),
                                    new OA\Property(property: "ime", type: "string", example: "Ana"),
                                    new OA\Property(property: "prezime", type: "string", example: "Jovanović"),
                                    new OA\Property(property: "email", type: "string", format: "email", example: "ana@student.rs"),
                                    new OA\Property(property: "uloga", type: "string", example: "STUDENT"),
                                ]
                            ),
                            new OA\Property(
                                property: "predmet",
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 3),
                                    new OA\Property(property: "naziv", type: "string", example: "Internet tehnologije"),
                                    new OA\Property(property: "sifra", type: "string", example: "ITEH"),
                                    new OA\Property(property: "godina_studija", type: "integer", example: 2),
                                ]
                            ),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)")
        ]
    )]
    public function index()
    {
        if ($response = $this->proveriAdmina()) {
            return $response;
        }

        return UpisResource::collection(
            Upis::with(['student', 'predmet'])->get()
        );
    }

    #[OA\Get(
        path: "/api/upisi/{id}",
        summary: "Detalji upisa (samo ADMIN)",
        tags: ["Upisi"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID upisa",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Uspešno vraćen upis"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)"),
            new OA\Response(response: 404, description: "Upis nije pronađen")
        ]
    )]
    public function show($id)
    {
        if ($response = $this->proveriAdmina()) {
            return $response;
        }

        $upis = Upis::with(['student', 'predmet'])->findOrFail($id);
        return new UpisResource($upis);
    }

    #[OA\Post(
        path: "/api/upisi",
        summary: "Kreiranje upisa (samo ADMIN)",
        tags: ["Upisi"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["student_id", "predmet_id"],
                properties: [
                    new OA\Property(property: "student_id", type: "integer", example: 7),
                    new OA\Property(property: "predmet_id", type: "integer", example: 3),
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Upis uspešno kreiran"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)"),
            new OA\Response(
                response: 422,
                description: "Validaciona greška",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Validacija nije prošla."),
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            example: ["student_id" => ["The student id field is required."]]
                        )
                    ]
                )
            )
        ]
    )]
    public function store(Request $request)
    {
        if ($response = $this->proveriAdmina()) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer|exists:users,id',
            'predmet_id' => 'required|integer|exists:predmeti,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $upis = Upis::create($validator->validated());

        return response()->json([
            'message' => 'Upis je uspešno kreiran.',
            'data' => new UpisResource($upis->load(['student', 'predmet'])),
        ], 201);
    }

    #[OA\Put(
        path: "/api/upisi/{id}",
        summary: "Izmena upisa (samo ADMIN)",
        tags: ["Upisi"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID upisa",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "student_id", type: "integer", example: 7),
                    new OA\Property(property: "predmet_id", type: "integer", example: 3),
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Upis uspešno izmenjen"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)"),
            new OA\Response(response: 404, description: "Upis nije pronađen"),
            new OA\Response(
                response: 422,
                description: "Validaciona greška",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Validacija nije prošla."),
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            example: ["predmet_id" => ["The selected predmet id is invalid."]]
                        )
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, $id)
    {
        if ($response = $this->proveriAdmina()) {
            return $response;
        }

        $upis = Upis::find($id);
        if (!$upis) {
            return response()->json(['message' => 'Upis nije pronađen.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'student_id' => 'sometimes|integer|exists:users,id',
            'predmet_id' => 'sometimes|integer|exists:predmeti,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $upis->update($validator->validated());

        return response()->json(new UpisResource($upis->load(['student', 'predmet'])), 200);
    }

    #[OA\Delete(
        path: "/api/upisi/{id}",
        summary: "Brisanje upisa (samo ADMIN)",
        tags: ["Upisi"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID upisa",
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Upis uspešno obrisan"),
            new OA\Response(response: 401, description: "Neautorizovan pristup"),
            new OA\Response(response: 403, description: "Zabranjeno (nije ADMIN)"),
            new OA\Response(response: 404, description: "Upis nije pronađen")
        ]
    )]
    public function destroy($id)
    {
        if ($response = $this->proveriAdmina()) {
            return $response;
        }

        $upis = Upis::find($id);
        if (!$upis) {
            return response()->json(['message' => 'Upis nije pronađen.'], 404);
        }

        $upis->delete();

        return response()->json(['message' => 'Upis je uspešno obrisan.'], 200);
    }
}