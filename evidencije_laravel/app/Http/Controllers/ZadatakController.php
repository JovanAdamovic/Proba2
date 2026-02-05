<?php

namespace App\Http\Controllers;

use App\Http\Resources\ZadatakResource;
use App\Models\Predmet;
use App\Models\Zadatak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ZadatakController extends Controller
{
    public function index()
    {
        return $this->moji();
    }

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
