<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/settings', 'settings.index')->name('settings.index');
    Route::get('/account/security', [AuthController::class, 'securityForm'])->name('account.security');
    Route::put('/account/password', [AuthController::class, 'updatePassword'])->name('account.password');
    Route::post('/outlet/switch', [OutletController::class, 'switch'])->name('outlets.switch');

    Route::middleware('outlet.permission:reports.view')->group(function (): void {
        Route::get('/', [ReportController::class, 'dashboard'])->name('dashboard');
        Route::get('/reports/current', [ReportController::class, 'current'])->name('reports.current');
        Route::get('/reports/current/excel', [ReportController::class, 'currentExcel'])->name('reports.current.excel');
        Route::get('/reports/current/pdf', [ReportController::class, 'currentPdf'])->name('reports.current.pdf');
        Route::get('/reports/interval', [ReportController::class, 'interval'])->name('reports.interval');
        Route::get('/reports/interval/excel', [ReportController::class, 'intervalExcel'])->name('reports.interval.excel');
        Route::get('/reports/interval/pdf', [ReportController::class, 'intervalPdf'])->name('reports.interval.pdf');
    });

    Route::middleware('outlet.permission:imports.view')->group(function (): void {
        Route::get('/uploads/history', [UploadController::class, 'history'])->name('uploads.history');
    });

    Route::middleware('outlet.permission:imports.manage')->group(function (): void {
        Route::get('/uploads/pos', [UploadController::class, 'pos'])->name('uploads.pos');
        Route::get('/uploads/excise', [UploadController::class, 'excise'])->name('uploads.excise');
        Route::post('/uploads/pos', [UploadController::class, 'pos'])->name('uploads.pos.preview');
        Route::post('/uploads/excise', [UploadController::class, 'excise'])->name('uploads.excise.preview');
        Route::post('/uploads/review', [UploadController::class, 'review'])->name('uploads.review');
        Route::post('/uploads/mapping', [UploadController::class, 'saveMapping'])->name('uploads.mapping');
        Route::post('/uploads/pos/apply', [UploadController::class, 'applyPos'])->name('uploads.pos.apply');
        Route::post('/uploads/excise/apply', [UploadController::class, 'applyExcise'])->name('uploads.excise.apply');
    });

    Route::middleware('outlet.permission:catalogue.view')->group(function (): void {
        Route::get('/brands', [CatalogueController::class, 'brands'])->name('brands.index');
        Route::get('/drinks', [CatalogueController::class, 'drinks'])->name('drinks.index');
        Route::get('/mappings', [CatalogueController::class, 'mappings'])->name('mappings.index');
    });

    Route::middleware('outlet.permission:catalogue.manage')->group(function (): void {
        Route::post('/brands', [CatalogueController::class, 'saveBrand'])->name('brands.store');
        Route::put('/brands/{id}', [CatalogueController::class, 'saveBrand'])->name('brands.update');
        Route::delete('/brands/{id}', [CatalogueController::class, 'deleteBrand'])->name('brands.destroy');
        Route::post('/drinks', [CatalogueController::class, 'saveDrink'])->name('drinks.store');
        Route::put('/drinks/{id}', [CatalogueController::class, 'saveDrink'])->name('drinks.update');
        Route::delete('/drinks/{id}', [CatalogueController::class, 'deleteDrink'])->name('drinks.destroy');
        Route::post('/mappings', [CatalogueController::class, 'saveMapping'])->name('mappings.store');
        Route::delete('/mappings/{id}', [CatalogueController::class, 'deleteMapping'])->name('mappings.destroy');
        Route::delete('/mappings/reviewed/{id}', [CatalogueController::class, 'deleteReviewedMapping'])->name('mappings.reviewed.destroy');
    });

    Route::middleware('outlet.permission:team.manage')->group(function (): void {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::put('/team/{id}', [TeamController::class, 'update'])->name('team.update');
    });
});
