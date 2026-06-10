<?php

use App\Http\Controllers\PostAgentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PostAgentController::class, 'index'])->name('posts.index');
Route::get('/generate', [PostAgentController::class, 'index']);
Route::post('/generate', [PostAgentController::class, 'generate'])->name('posts.generate');
