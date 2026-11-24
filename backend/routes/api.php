<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ServiceController;
use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Support\Facades\Route;

// خدمات آرایشگاه
Route::get('/services', [ServiceController::class, 'index']);

// حساب کاربری مشتری‌ها (ثبت‌نام، ورود و دریافت اطلاعات حساب)
Route::post('/customers/register', [CustomerController::class, 'register']);
Route::post('/customers/login', [CustomerController::class, 'login']);
Route::get('/customers/{id}', [CustomerController::class, 'show']);

// رزرو نوبت توسط مشتری (دریافت فقط ساعت‌های پر و ثبت نوبت جدید)
Route::get('/appointments/booked-slots', [AppointmentController::class, 'bookedSlots']);
Route::post('/appointments', [AppointmentController::class, 'store']);

// ورود صاحب آرایشگاه به پنل مدیریت
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// مسیرهای مدیریتی محافظت‌شده (فقط صاحب آرایشگاه پس از ورود دسترسی دارد)
Route::middleware(EnsureAdminAuthenticated::class)->group(function () {
    Route::get('/admin/me', [AdminAuthController::class, 'me']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
});
