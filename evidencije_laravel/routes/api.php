<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PredmetController;
use App\Http\Controllers\ZadatakController;
use App\Http\Controllers\PredajaController;
use App\Http\Controllers\UserController;




Route::controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');
});



Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', fn (Request $request) => response()->json($request->user())); 
    Route::post('/logout', [AuthController::class, 'logout']); 

    Route::get('/users', [UserController::class, 'index']);

    Route::get('/predmeti/moji', [PredmetController::class, 'moji']);
    Route::get('/zadaci/moji', [ZadatakController::class, 'moji']);
     Route::get('/predaje/moje', [PredajaController::class, 'moje']);
    Route::get('/predaje/za-moje-predmete', [PredajaController::class, 'zaMojePredmete']); 


    Route::get('/predmeti', [PredmetController::class, 'index']);
    Route::get('/predmeti/{id}', [PredmetController::class, 'show']);

    Route::get('/zadaci', [ZadatakController::class, 'index']);
    Route::get('/zadaci/{id}', [ZadatakController::class, 'show']);

     Route::get('/predaje', [PredajaController::class, 'index']);
    Route::get('/predaje/export/csv', [PredajaController::class, 'exportCsv']);
    Route::get('/predaje/{id}/file', [PredajaController::class, 'file']);
    Route::get('/predaje/{id}', [PredajaController::class, 'show']);

    Route::post('/predaje', [PredajaController::class, 'store']);
    Route::delete('/predaje/{id}', [PredajaController::class, 'destroy']);

    Route::post('/zadaci', [ZadatakController::class, 'store']);
    Route::put('/zadaci/{id}', [ZadatakController::class, 'update']);
    Route::delete('/zadaci/{id}', [ZadatakController::class, 'destroy']);
    Route::put('/predaje/{id}', [PredajaController::class, 'update']);

    Route::post('/predmeti', [PredmetController::class, 'store']);
    Route::put('/predmeti/{id}', [PredmetController::class, 'update']);
    Route::delete('/predmeti/{id}', [PredmetController::class, 'destroy']);

   
});