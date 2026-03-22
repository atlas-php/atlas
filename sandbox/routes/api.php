<?php

declare(strict_types=1);

use App\Http\Controllers\AssetController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// ─── Chat ────────────────────────────────────────────────────
Route::post('/chat', [ChatController::class, 'chat']);

// ─── Conversations ───────────────────────────────────────────
Route::get('/conversations', [ChatController::class, 'index']);
Route::get('/conversations/{id}', [ChatController::class, 'show']);
Route::delete('/conversations/{id}', [ChatController::class, 'destroy']);

// ─── Messages (infinite scroll) ─────────────────────────────
Route::get('/conversations/{conversationId}/messages', [ChatController::class, 'messages']);

// ─── Retry / Siblings ────────────────────────────────────────
Route::post('/conversations/{conversationId}/retry', [ChatController::class, 'retry']);
Route::get('/conversations/{conversationId}/messages/{messageId}/siblings', [ChatController::class, 'siblings']);
Route::post('/conversations/{conversationId}/messages/{messageId}/cycle', [ChatController::class, 'cycleSibling']);

// ─── Execution Status ────────────────────────────────────────
Route::get('/executions/{id}', [ChatController::class, 'executionStatus']);
Route::get('/conversations/{conversationId}/processing', [ChatController::class, 'processingStatus']);

// ─── Assets (file proxy) ────────────────────────────────────
Route::get('/assets/{id}.{extension}', [AssetController::class, 'show']);
Route::get('/assets/{id}', [AssetController::class, 'show']);
