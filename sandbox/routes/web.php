<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ChatController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Chat SPA page - supports agent and thread in URL
Route::get('/sandbox/chat/{agent?}/{thread?}', function () {
    return view('chat');
})->name('chat')->where(['agent' => '[a-z0-9-]+', 'thread' => '[0-9]+']);

// Chat API endpoints (CSRF disabled for JSON API)
Route::prefix('api/chat')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->group(function () {
        Route::get('agents', [ChatController::class, 'agents']);
        Route::get('threads', [ChatController::class, 'index']);
        Route::post('threads', [ChatController::class, 'store']);
        Route::get('threads/{id}', [ChatController::class, 'show']);
        Route::delete('threads/{id}', [ChatController::class, 'destroy']);
        Route::post('threads/{id}/messages', [ChatController::class, 'message']);
    });
