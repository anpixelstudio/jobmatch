<?php

use App\Http\Controllers\CandidateController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('uploads', [UploadController::class, 'index'])->name('uploads.index');
    Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');
    Route::post('jobs', [UploadController::class, 'storeJob'])->name('jobs.store');

    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('chat', [ChatController::class, 'store'])->name('chat.store');
    Route::post('chat/reset', [ChatController::class, 'reset'])->name('chat.reset');

    Route::get('candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::get('candidates/{candidate}/preview', [CandidateController::class, 'preview'])->name('candidates.preview');
    Route::get('candidates/{candidate}/pdf', [CandidateController::class, 'pdf'])->name('candidates.pdf');
    Route::get('candidates/{candidate}', [CandidateController::class, 'show'])->name('candidates.show');
    Route::patch('candidates/{candidate}/status', [CandidateController::class, 'updateStatus'])->name('candidates.status');
});

require __DIR__.'/settings.php';
