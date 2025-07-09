<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenantRegistrationController;

// Route d'accueil
Route::get('/', function () {
    return view('welcome');
});

// Routes d'inscription du locataire
Route::get('/register/tenant', [TenantRegistrationController::class, 'showRegistrationForm'])->name('tenant.register');
Route::post('/register/tenant', [TenantRegistrationController::class, 'register'])->name('tenant.register.submit');
Route::get('/register/tenant/success', [TenantRegistrationController::class, 'showSuccessPage'])->name('tenant.register.success');
Route::post('/check-tenant-exists', [\App\Http\Controllers\TenantRegistrationController::class, 'checkTenantExists'])->name('tenant.check.exists');

Route::get('/test-key', function () {
    return config('app.key');
});

Route::get('/log-test', function () {
    \Log::info('Test log route');
    return 'ok';
});