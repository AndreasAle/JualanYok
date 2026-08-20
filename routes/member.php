<?php

use App\Http\Controllers\Customer\CourseController;
use App\Http\Controllers\Customer\MemberController;
use App\Http\Controllers\Customer\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('member')
    ->name('member.')
    ->group(function () {
        Route::get('/', [MemberController::class, 'index'])->name('dashboard');

        Route::get('/pembelian', [PurchaseController::class, 'index'])->name('orders.index');
        Route::get('/pembelian/{order:number}', [PurchaseController::class, 'show'])->name('orders.show');
        Route::get('/pembelian/{order:number}/download/{access}', [PurchaseController::class, 'download'])
            ->middleware('throttle:30,1')
            ->name('orders.download');
        Route::post('/pembelian/{order:number}/refund', [PurchaseController::class, 'requestRefund'])
            ->name('orders.refund');

        Route::get('/kelas', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/kelas/{enrollment}', [CourseController::class, 'show'])->name('courses.show');
        Route::post('/kelas/{enrollment}/lesson/{lesson}/selesai', [CourseController::class, 'complete'])
            ->name('courses.complete');

        Route::get('/profil', [MemberController::class, 'profile'])->name('profile');
        Route::put('/profil', [MemberController::class, 'updateProfile'])->name('profile.update');
    });
