<?php
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::post('/upload', [FileController::class, 'upload']);