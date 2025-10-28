<?php
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::post('/upload', [FileController::class, 'upload']);
Route::get('/history', [FileController::class, 'history']);
Route::get('/file-content', [FileController::class, 'search']);

// rotas para teste e incluir no swagger
// GET http://localhost:8080/api/history?file_name=Instruments

// GET http://localhost:8080/api/history?date=2025-10-28

// GET http://localhost:8080/api/history?file_name=Instruments&date=2025-10-28
