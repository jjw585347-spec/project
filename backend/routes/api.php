<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\WorkingScheduleController;
use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Support\Facades\Route;

// تنظیمات عمومی آرایشگاه (نام آرایشگاه، مهلت لغو و برنامه هفتگی)
Route::get('/settings', [SettingController::class, 'publicIndex']);

// خدمات آرایشگاه
Route::get('/services', [ServiceController::class, 'index']);

// حساب کاربری مشتری‌ها (ثبت‌نام، ورود و دریافت اطلاعات حساب)
Route::post('/customers/register', [CustomerController::class, 'register']);
Route::post('/customers/login', [CustomerController::class, 'login']);
Route::get('/customers/{id}', [CustomerController::class, 'show']);

// رزرو نوبت توسط مشتری (دریافت فقط ساعت‌های پر، ثبت نوبت جدید و پیگیری/لغو با کد پیگیری ۶ رقمی R9)
Route::get('/appointments/booked-slots', [AppointmentController::class, 'bookedSlots']);
Route::post('/appointments', [AppointmentController::class, 'store']);
Route::post('/appointments/track', [AppointmentController::class, 'track']);
Route::post('/appointments/track/cancel', [AppointmentController::class, 'customerCancel']);

// ورود صاحب آرایشگاه به پنل مدیریت
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// مسیرهای مدیریتی محافظت‌شده (فقط صاحب آرایشگاه پس از ورود دسترسی دارد)
Route::middleware(EnsureAdminAuthenticated::class)->group(function () {
    Route::get('/admin/me', [AdminAuthController::class, 'me']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);

    // مدیریت خدمات آرایشگاه توسط صاحب کار
    Route::get('/admin/services', [ServiceController::class, 'adminIndex']);
    Route::post('/admin/services', [ServiceController::class, 'store']);
    Route::put('/admin/services/{id}', [ServiceController::class, 'update']);
    Route::patch('/admin/services/{id}/status', [ServiceController::class, 'toggleStatus']);
    Route::delete('/admin/services/{id}', [ServiceController::class, 'destroy']);

    // مدیریت ساعت کاری هفتگی، تاریخ‌های تعطیل و مرخصی‌های ساعتی توسط صاحب کار (R7)
    Route::get('/admin/schedule', [WorkingScheduleController::class, 'index']);
    Route::put('/admin/schedule/weekly', [WorkingScheduleController::class, 'updateWeekly']);
    Route::post('/admin/schedule/holidays', [WorkingScheduleController::class, 'storeHoliday']);
    Route::post('/admin/schedule/leaves', [WorkingScheduleController::class, 'storeLeave']);
    Route::delete('/admin/schedule/time-offs/{id}', [WorkingScheduleController::class, 'destroyTimeOff']);

    // صفحه تنظیمات کامل پنل مدیریت (نام آرایشگاه، مهلت لغو، شماره موبایل و رمز ورود با پرسیدن رمز فعلی)
    Route::get('/admin/settings', [SettingController::class, 'adminIndex']);
    Route::put('/admin/settings/general', [SettingController::class, 'updateGeneral']);
    Route::put('/admin/settings/credentials', [AdminAuthController::class, 'updateCredentials']);
});
