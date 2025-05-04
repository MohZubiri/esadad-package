<?php

use Illuminate\Support\Facades\Route;
use ESadad\PaymentGateway\Http\Controllers\ESadadPaymentController;

Route::group([
    'prefix' => config('esadad.routes.prefix', 'esadad'),
    'middleware' => config('esadad.routes.middleware', ['web']),
    'as' => 'esadad.',
], function () {
    // Payment form
    Route::get('/payment', [ESadadPaymentController::class, 'showPaymentForm'])->name('form');
    
    // Process payment
    Route::post('/payment', [ESadadPaymentController::class, 'processPayment'])->name('process');
    
    // OTP verification
    Route::get('/otp', [ESadadPaymentController::class, 'showOtpForm'])->name('otp');
    Route::post('/otp', [ESadadPaymentController::class, 'verifyOtp'])->name('verify');
    
    // Success page
    Route::get('/success', [ESadadPaymentController::class, 'showSuccessPage'])->name('success');
    
    // Transactions
    Route::get('/transactions', [ESadadPaymentController::class, 'listTransactions'])->name('transactions');
    Route::get('/transactions/{id}', [ESadadPaymentController::class, 'showTransaction'])->name('transaction');
});
