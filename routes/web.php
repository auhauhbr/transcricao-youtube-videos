<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\DownloadLibraryTranscriptController;
use App\Http\Controllers\DownloadTranscriptController;
use App\Http\Controllers\ExtractionController;
use App\Http\Controllers\ExtractTranscriptController;
use App\Http\Controllers\LibraryBulkController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LibraryFolderController;
use App\Http\Controllers\LibraryTagController;
use App\Http\Controllers\LibraryTranscriptController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\UserDocumentDownloadController;
use App\Http\Controllers\UserDocumentRevisionController;
use App\Http\Controllers\UserDocumentWorkspaceController;
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

    Route::get('/library', LibraryController::class)->name('library.index');
    Route::post('/library/folders', [LibraryFolderController::class, 'store'])->name('library.folders.store');
    Route::patch('/library/folders/{folder}', [LibraryFolderController::class, 'update'])->whereUlid('folder')->name('library.folders.update');
    Route::delete('/library/folders/{folder}', [LibraryFolderController::class, 'destroy'])->whereUlid('folder')->name('library.folders.destroy');
    Route::post('/library/tags', [LibraryTagController::class, 'store'])->name('library.tags.store');
    Route::patch('/library/tags/{tag}', [LibraryTagController::class, 'update'])->whereUlid('tag')->name('library.tags.update');
    Route::delete('/library/tags/{tag}', [LibraryTagController::class, 'destroy'])->whereUlid('tag')->name('library.tags.destroy');
    Route::patch('/library/items/move', [LibraryBulkController::class, 'move'])->name('library.items.move');
    Route::post('/library/items/tags', [LibraryBulkController::class, 'addTags'])->name('library.items.tags.add');
    Route::delete('/library/items/tags', [LibraryBulkController::class, 'removeTags'])->name('library.items.tags.remove');
    Route::delete('/library/items', [LibraryBulkController::class, 'destroy'])->name('library.items.destroy');
    Route::get('/library/{userTranscript}/download', DownloadLibraryTranscriptController::class)
        ->whereUlid('userTranscript')
        ->name('library.download');
    Route::get('/library/{userTranscript}/workspace', [UserDocumentWorkspaceController::class, 'show'])
        ->whereUlid('userTranscript')
        ->name('library.workspace');
    Route::put('/library/{userTranscript}/document', [UserDocumentWorkspaceController::class, 'update'])
        ->whereUlid('userTranscript')
        ->name('library.document.update');
    Route::get('/library/{userTranscript}/document/download', UserDocumentDownloadController::class)
        ->whereUlid('userTranscript')
        ->name('library.document.download');
    Route::get('/library/{userTranscript}/document/revisions', [UserDocumentRevisionController::class, 'index'])
        ->whereUlid('userTranscript')
        ->name('library.document.revisions.index');
    Route::post('/library/{userTranscript}/document/revisions', [UserDocumentRevisionController::class, 'store'])
        ->whereUlid('userTranscript')
        ->name('library.document.revisions.store');
    Route::get('/library/{userTranscript}/document/revisions/{revision}', [UserDocumentRevisionController::class, 'show'])
        ->whereUlid('userTranscript')
        ->whereUlid('revision')
        ->name('library.document.revisions.show');
    Route::post('/library/{userTranscript}/document/revisions/{revision}/restore', [UserDocumentRevisionController::class, 'restore'])
        ->whereUlid('userTranscript')
        ->whereUlid('revision')
        ->name('library.document.revisions.restore');
    Route::get('/library/{userTranscript}', [LibraryTranscriptController::class, 'show'])
        ->whereUlid('userTranscript')
        ->name('library.show');
    Route::delete('/library/{userTranscript}', [LibraryTranscriptController::class, 'destroy'])
        ->whereUlid('userTranscript')
        ->name('library.destroy');
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
