<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestFormController;
use App\Http\Controllers\ProdPayloadController;

Route::get('/', [TestFormController::class, 'show']);
Route::post('/', [TestFormController::class, 'decrypt'])->name('test-form.decrypt');

Route::get('/prod-test', [ProdPayloadController::class, 'show'])->name('prod-test.show');
Route::post('/prod-test', [ProdPayloadController::class, 'decrypt'])->name('prod-test.decrypt');

