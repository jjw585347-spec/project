<?php

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// لیست خدمات: http://localhost:8000/api/services
Route::get('/services', function () {
    return response()->json(
        Service::orderBy('id')->get(['id', 'name', 'price', 'duration']),
        200,
        [],
        JSON_UNESCAPED_UNICODE
    );
});

// ثبت نوبت جدید: http://localhost:8000/api/appointments
Route::post('/appointments', function (Request $request) {
    $validated = $request->validate([
        'customer_name' => ['required', 'string', 'max:255'],
        'customer_phone' => ['required', 'string'],
        'service_id' => ['required', 'exists:services,id'],
        'appointment_date' => ['required', 'date'],
        'appointment_time' => ['required', 'string'],
    ]);

    // تبدیل اعداد فارسی و عربی شماره موبایل به انگلیسی
    $normalizedPhone = strtr(
        trim($validated['customer_phone']),
        [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]
    );

    // بررسی منطقی بودن شماره موبایل (۱۱ رقم و شروع با 09)
    if (!preg_match('/^09\d{9}$/', $normalizedPhone)) {
        return response()->json(
            [
                'message' => 'ثبت نوبت ناموفق بود: شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با 09 شروع می‌شود (مثلاً 09123456789) وارد کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    $validated['customer_phone'] = $normalizedPhone;

    $now = Carbon::now('Asia/Tehran');
    $appointmentDate = Carbon::parse($validated['appointment_date'], 'Asia/Tehran')->startOfDay();
    $today = $now->copy()->startOfDay();

    // بررسی ۱: اگر تاریخ وارد شده برای روزهای گذشته باشد
    if ($appointmentDate->lessThan($today)) {
        return response()->json(
            [
                'message' => 'ثبت ناموفق بود. تاریخ برای گذشته است.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    // بررسی ۲: اگر تاریخ برای امروز باشد ولی ساعت آن گذشته باشد
    $appointmentDateTime = Carbon::parse(
        $validated['appointment_date'] . ' ' . $validated['appointment_time'],
        'Asia/Tehran'
    );

    if ($appointmentDate->equalTo($today) && $appointmentDateTime->lessThanOrEqualTo($now)) {
        return response()->json(
            [
                'message' => 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده در امروز گذشته است. لطفاً ساعت دیگری در ادامه امروز یا روز دیگری را برای نوبت خود انتخاب کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    // بررسی ۳: جلوگیری از رزرو تکراری یک ساعت در یک روز
    $alreadyBooked = Appointment::whereDate('appointment_date', $validated['appointment_date'])
        ->where('appointment_time', $validated['appointment_time'])
        ->exists();

    if ($alreadyBooked) {
        return response()->json(
            [
                'message' => 'ثبت نوبت ناموفق بود: این ساعت در این روز قبلاً رزرو شده است. لطفاً ساعت یا روز دیگری را انتخاب کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    $appointment = Appointment::create($validated);
    $appointment->load('service:id,name,price,duration');

    return response()->json(
        [
            'message' => 'نوبت شما با موفقیت ثبت شد.',
            'appointment' => $appointment,
        ],
        201,
        [],
        JSON_UNESCAPED_UNICODE
    );
});
