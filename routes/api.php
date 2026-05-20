<?php

use App\Http\Controllers\Api\CommunicationController;
use App\Http\Controllers\Api\NotificationTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('communications')->group(function (): void {
    Route::post('/', [CommunicationController::class, 'store'])->name('communications.store');
    Route::get('/{communication}', [CommunicationController::class, 'show'])->name('communications.show');
});

Route::apiResource('notification-templates', NotificationTemplateController::class);
