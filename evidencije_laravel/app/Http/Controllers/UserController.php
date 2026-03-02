<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: "/api/users",
        summary: "Lista korisnika (samo ADMIN). Opcioni filter po ulozi.",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "role",
                in: "query",
                required: false,
                description: "Filter po ulozi korisnika",
                schema: new OA\Schema(
                    type: "string",
                    enum: ["STUDENT", "PROFESOR", "ADMIN"]
                ),
                example: "STUDENT"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Uspešno vraćena lista korisnika",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "ime", type: "string", example: "Ana"),
                            new OA\Property(property: "prezime", type: "string", example: "Jovanović"),
                            new OA\Property(property: "email", type: "string", format: "email", example: "ana@student.rs"),
                            new OA\Property(property: "uloga", type: "string", example: "STUDENT"),
                        ]
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: "Neautorizovan pristup"
            ),
            new OA\Response(
                response: 403,
                description: "Zabranjeno (nije ADMIN)"
            ),
            new OA\Response(
                response: 422,
                description: "Validaciona greška (neispravan role parametar)",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Validacija nije prošla."),
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            example: ["role" => ["The selected role is invalid."]]
                        )
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->uloga !== 'ADMIN') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $role = $request->query('role');
        if ($role !== null) {
            $validator = validator(['role' => $role], [
                'role' => ['required', Rule::in(['STUDENT', 'PROFESOR', 'ADMIN'])],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validacija nije prošla.',
                    'errors' => $validator->errors(),
                ], 422);
            }
        }

        $query = User::query()->select(['id', 'ime', 'prezime', 'email', 'uloga']);

        if ($role) {
            $query->where('uloga', $role);
        }

        return response()->json(
            $query->orderBy('prezime')->orderBy('ime')->get()
        );
    }
}