<?php

namespace App\Http\Controllers;

use App\Http\Resources\PredajaResource;
use App\Models\Predaja;
use App\Models\Predmet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PredajaController extends Controller
{
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

    public function moje()
    {
        $user = auth()->user();

        return PredajaResource::collection(
            Predaja::with(['student', 'zadatak'])
                ->where('student_id', $user->id)
                ->get()
        );
    }

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

    public function exportCsv()
    {
        $user = auth()->user();

        if ($user->uloga === 'STUDENT') {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $query = Predaja::with(['student', 'zadatak.predmet', 'proveraPlagijata']);

        if ($user->uloga === 'PROFESOR') {
            $query->whereHas('zadatak.predmet', function ($q) use ($user) {
                $q->where('profesor_id', $user->id);
            });
        }

        $predaje = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ];

        $filename = 'predaje_export.csv';

        return response()->streamDownload(function () use ($predaje) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID',
                'Student',
                'Email',
                'Predmet',
                'Zadatak',
                'Status',
                'Ocena',
                'Komentar',
                'Provera plagijata',
                'Procenat slicnosti',
                'Predato',
            ]);

            foreach ($predaje as $predaja) {
                fputcsv($handle, [
                    $predaja->id,
                    $predaja->student?->ime . ' ' . $predaja->student?->prezime,
                    $predaja->student?->email,
                    $predaja->zadatak?->predmet?->naziv,
                    $predaja->zadatak?->naslov,
                    $predaja->status,
                    $predaja->ocena,
                    $predaja->komentar,
                    $predaja->proveraPlagijata?->status,
                    $predaja->proveraPlagijata?->procenat_slicnosti,
                    $predaja->submitted_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $filename, $headers);
    }
}
