<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
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