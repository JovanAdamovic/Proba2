<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/login",
        summary: "Prijava korisnika (Sanctum token). Vraća Bearer token i osnovne podatke o korisniku.",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "ana@student.rs"),
                    new OA\Property(property: "password", type: "string", example: "password")
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Uspešna prijava",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Uspešna prijava."),
                        new OA\Property(property: "access_token", type: "string", example: "1|laravel_sanctum_token"),
                        new OA\Property(property: "token_type", type: "string", example: "Bearer"),
                        new OA\Property(
                            property: "user",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "ime", type: "string", example: "Ana"),
                                new OA\Property(property: "prezime", type: "string", example: "Jovanović"),
                                new OA\Property(property: "email", type: "string", format: "email", example: "ana@student.rs"),
                                new OA\Property(property: "uloga", type: "string", example: "STUDENT")
                            ]
                        )
                    ],
                    type: "object"
                )
            ),
            new OA\Response(
                response: 401,
                description: "Pogrešan email ili lozinka",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Pogrešan email ili lozinka.")
                    ],
                    type: "object"
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validaciona greška",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Validacija nije prošla."),
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            example: ["email" => ["The email field is required."]]
                        )
                    ],
                    type: "object"
                )
            )
        ]
    )]
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije prošla.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Pogrešan email ili lozinka.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Uspešna prijava.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'ime' => $user->ime,
                'prezime' => $user->prezime,
                'email' => $user->email,
                'uloga' => $user->uloga,
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Odjava korisnika',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Uspešna odjava'),
            new OA\Response(response: 401, description: 'Neautorizovan pristup'),
        ]
    )]
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Uspešno ste se odjavili.'], 200);
    }

   #[OA\Get(
        path: '/api/me',
        summary: 'Podaci o trenutno prijavljenom korisniku',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Trenutni korisnik'),
            new OA\Response(response: 401, description: 'Neautorizovan pristup'),
        ]
    )]
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ], 200);
    }
}