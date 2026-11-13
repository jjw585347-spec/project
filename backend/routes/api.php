<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

// خدمات آرایشگاه
Route::get('/services', [ServiceController::class, 'index']);

// حساب کاربری مشتری‌ها (ثبت‌نام، ورود و دریافت اطلاعات حساب)
Route::post('/customers/register', [CustomerController::class, 'register']);
Route::post('/customers/login', [CustomerController::class, 'login']);
Route::get('/customers/{id}', [CustomerController::class, 'show']);

// مدیریت نوبت‌ها (لیست نوبت‌ها، ثبت نوبت جدید و لغو نوبت)
Route::get('/appointments', [AppointmentController::class, 'index']);
Route::post('/appointments', [AppointmentController::class, 'store']);
Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
