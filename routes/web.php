<?php

use App\Http\Controllers\ExtractTranscriptController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'extractUrl' => route('transcripts.extract', absolute: false),
    ]);
});

Route::post('/transcripts/extract', ExtractTranscriptController::class)
    ->name('transcripts.extract');
