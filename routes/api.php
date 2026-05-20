<?php

use App\Http\Controllers\Api\CommunicationController;
use App\Http\Controllers\Api\NotificationTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('communications')->group(function (): void {
    Route::get('/', [CommunicationController::class, 'index'])->name('communications.index');
    Route::post('/', [CommunicationController::class, 'store'])->name('communications.store');
    Route::get('/{communication}', [CommunicationController::class, 'show'])->name('communications.show');
    Route::match(['put', 'patch'], '/{communication}', [CommunicationController::class, 'update'])->name('communications.update');
    Route::delete('/{communication}', [CommunicationController::class, 'destroy'])->name('communications.destroy');
});

Route::apiResource('notification-templates', NotificationTemplateController::class);
