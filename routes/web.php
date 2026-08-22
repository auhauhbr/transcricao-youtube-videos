<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\DownloadTranscriptController;
use App\Http\Controllers\ExtractionController;
use App\Http\Controllers\ExtractTranscriptController;
use App\Http\Controllers\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'extractUrl' => route('transcripts.extract', absolute: false),
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
});

Route::post('/transcripts/extract', ExtractTranscriptController::class)
    ->middleware('throttle:transcript-extractions')
    ->name('transcripts.extract');

Route::get('/extractions/{extraction}/download', DownloadTranscriptController::class)
    ->whereUlid('extraction')
    ->name('extractions.download');

Route::get('/extractions/{extraction}', ExtractionController::class)
    ->whereUlid('extraction')
    ->name('extractions.show');
