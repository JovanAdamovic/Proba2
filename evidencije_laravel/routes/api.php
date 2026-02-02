<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;



Route::controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');
});



Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', fn (Request $request) => response()->json($request->user())); 
    Route::post('/logout', [AuthController::class, 'logout']); 

   
});