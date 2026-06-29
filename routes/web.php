<?php

use App\Http\Controllers\Admin\ClinicSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnamnesisInvitationController;
use App\Http\Controllers\AnamnesisQuestionController;
use App\Http\Controllers\AnamnesisRecordController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientTreatmentPurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicAnamnesisController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::post('webhooks/whatsapp', WhatsAppWebhookController::class)->name('webhooks.whatsapp');

Route::get('anamnesis/fill/success', [PublicAnamnesisController::class, 'success'])->name('anamnesis.public.success');
Route::get('anamnesis/fill/{token}', [PublicAnamnesisController::class, 'show'])->name('anamnesis.public.show');
Route::post('anamnesis/fill/{token}', [PublicAnamnesisController::class, 'store'])->name('anamnesis.public.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('clients', ClientController::class);
    Route::get('clients/{client}/treatments/create', [ClientTreatmentPurchaseController::class, 'create'])->name('clients.treatments.create');
    Route::post('clients/{client}/treatments', [ClientTreatmentPurchaseController::class, 'store'])->name('clients.treatments.store');
    Route::delete('clients/{client}/treatments/{purchase}', [ClientTreatmentPurchaseController::class, 'destroy'])->name('clients.treatments.destroy');
    Route::post('clients/treatments/preview', [ClientTreatmentPurchaseController::class, 'preview'])->name('clients.treatments.preview');
    Route::get('clients/{client}/anamnesis/create', [AnamnesisRecordController::class, 'create'])->name('clients.anamnesis.create');
    Route::post('clients/{client}/anamnesis', [AnamnesisRecordController::class, 'store'])->name('clients.anamnesis.store');
    Route::post('clients/{client}/anamnesis/request', [AnamnesisInvitationController::class, 'store'])->name('clients.anamnesis.request');
    Route::get('clients/{client}/anamnesis/{anamnesisRecord}', [AnamnesisRecordController::class, 'show'])->name('clients.anamnesis.show');

    Route::put('anamnesis-questions/reorder', [AnamnesisQuestionController::class, 'reorder'])->name('anamnesis-questions.reorder');
    Route::resource('anamnesis-questions', AnamnesisQuestionController::class)->except(['show']);
    Route::resource('treatments', TreatmentController::class)->except(['show']);
    Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('clients/{client}/available-treatments', [AppointmentController::class, 'availableTreatments'])->name('clients.available-treatments');
    Route::get('appointments/available-slots', [AppointmentController::class, 'availableSlots'])->name('appointments.available-slots');
    Route::get('appointments/complete', [AppointmentController::class, 'completeIndex'])->name('appointments.complete.index');
    Route::post('appointments/complete-bulk', [AppointmentController::class, 'completeBulk'])->name('appointments.complete.bulk');
    Route::patch('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::patch('appointments/{appointment}/uncomplete', [AppointmentController::class, 'uncomplete'])->name('appointments.uncomplete');
    Route::resource('appointments', AppointmentController::class)->except(['show']);

    Route::middleware(EnsureUserIsAdmin::class)->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('settings', [ClinicSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [ClinicSettingController::class, 'update'])->name('settings.update');
    });
});

require __DIR__.'/settings.php';
