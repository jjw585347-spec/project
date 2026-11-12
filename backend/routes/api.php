<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// تابع کمکی برای تبدیل اعداد فارسی و عربی به انگلیسی
if (!function_exists('normalizePersianDigits')) {
    function normalizePersianDigits(string $value): string
    {
        return strtr(
            trim($value),
            [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]
        );
    }
}

// ایجاد حساب مشتری جدید: POST /api/customers/register
Route::post('/customers/register', function (Request $request) {
    $name = trim((string) $request->input('name', ''));
    $rawPhone = (string) $request->input('phone', '');
    $phone = normalizePersianDigits($rawPhone);

    if ($name === '' || $phone === '') {
        return response()->json(
            [
                'message' => 'لطفاً نام و شماره موبایل خود را کامل وارد کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    if (mb_strlen($name) > 40) {
        return response()->json(
            [
                'message' => 'نام واردشده خیلی طولانی است. لطفاً نام را حداکثر تا ۴۰ کاراکتر وارد کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    // نکته ۳: بررسی منطقی بودن شماره موبایل (۱۱ رقم و شروع با 09)
    if (!preg_match('/^09\d{9}$/', $phone)) {
        return response()->json(
            [
                'message' => 'شماره موبایل واردشده منطقی نیست. لطفاً یک شماره موبایل معتبر ۱۱ رقمی که با 09 شروع می‌شود (مثلاً 09123456789) وارد کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    // نکته ۲: بررسی تکراری نبودن نام
    $nameExists = Customer::where('name', $name)->exists();
    if ($nameExists) {
        return response()->json(
            [
                'message' => 'این نام قبلاً استفاده شده است. لطفاً نام دیگری انتخاب کنید (یا اگر قبلاً حساب ساخته‌اید از بخش ورود وارد شوید).',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    $customer = Customer::create([
        'name' => $name,
        'phone' => $phone,
    ]);

    return response()->json(
        [
            'message' => 'حساب شما با موفقیت ایجاد شد.',
            'customer' => $customer,
        ],
        201,
        [],
        JSON_UNESCAPED_UNICODE
    );
});

// ورود مشتری قبلی: POST /api/customers/login
Route::post('/customers/login', function (Request $request) {
    $name = trim((string) $request->input('name', ''));
    $rawPhone = (string) $request->input('phone', '');
    $phone = normalizePersianDigits($rawPhone);

    if ($name === '' || $phone === '') {
        return response()->json(
            [
                'message' => 'لطفاً نام و شماره موبایل خود را وارد کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    if (mb_strlen($name) > 40) {
        return response()->json(
            [
                'message' => 'نام واردشده خیلی طولانی است. لطفاً نام را حداکثر تا ۴۰ کاراکتر وارد کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    // بررسی منطقی بودن شماره موبایل
    if (!preg_match('/^09\d{9}$/', $phone)) {
        return response()->json(
            [
                'message' => 'شماره موبایل واردشده منطقی نیست. لطفاً یک شماره موبایل معتبر ۱۱ رقمی که با 09 شروع می‌شود (مثلاً 09123456789) وارد کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    $customerByName = Customer::where('name', $name)->first();

    if (!$customerByName) {
        return response()->json(
            [
                'message' => 'نام واردشده در سیستم وجود ندارد. لطفاً نام خود را دقیق وارد کنید یا اگر هنوز ثبت‌نام نکرده‌اید، روی دکمه «ایجاد حساب» در پایین کلیک کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    if ($customerByName->phone !== $phone) {
        return response()->json(
            [
                'message' => 'شماره موبایل واردشده با این نام مطابقت ندارد. لطفاً شماره موبایلی که با آن حساب ساخته‌اید را به درستی وارد کنید.',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    return response()->json(
        [
            'message' => 'ورود با موفقیت انجام شد.',
            'customer' => $customerByName,
        ],
        200,
        [],
        JSON_UNESCAPED_UNICODE
    );
});

// دریافت اطلاعات مشتری از دیتابیس: GET /api/customers/{id}
Route::get('/customers/{id}', function ($id) {
    $customer = Customer::findOrFail($id);

    return response()->json(
        [
            'customer' => $customer,
        ],
        200,
        [],
        JSON_UNESCAPED_UNICODE
    );
});

// لیست خدمات: GET /api/services
Route::get('/services', function () {
    return response()->json(
        Service::orderBy('id')->get(['id', 'name', 'price', 'duration']),
        200,
        [],
        JSON_UNESCAPED_UNICODE
    );
});

// لیست نوبت‌های یک روز برای صاحب آرایشگاه (مرتب بر اساس ساعت): GET /api/appointments?date=YYYY-MM-DD
Route::get('/appointments', function (Request $request) {
    $date = $request->query('date') ?: Carbon::now('Asia/Tehran')->toDateString();

    $appointments = Appointment::with('service:id,name,price,duration')
        ->whereDate('appointment_date', $date)
        ->orderBy('appointment_time', 'asc')
        ->orderBy('id', 'asc')
        ->get();

    return response()->json(
        [
            'date' => $date,
            'appointments' => $appointments,
        ],
        200,
        [],
        JSON_UNESCAPED_UNICODE
    );
});

// لغو نوبت توسط صاحب آرایشگاه (بدون پاک شدن از دیتابیس): PATCH /api/appointments/{id}/cancel
Route::patch('/appointments/{id}/cancel', function ($id) {
    $appointment = Appointment::findOrFail($id);
    $appointment->status = 'cancelled';
    $appointment->save();
    $appointment->load('service:id,name,price,duration');

    return response()->json(
        [
            'message' => 'رزرو با موفقیت لغو شد.',
            'appointment' => $appointment,
        ],
        200,
        [],
        JSON_UNESCAPED_UNICODE
    );
});

// ثبت نوبت جدید: POST /api/appointments
Route::post('/appointments', function (Request $request) {
    $rawName = trim((string) $request->input('customer_name', ''));
    if (mb_strlen($rawName) > 40) {
        return response()->json(
            [
                'message' => 'ثبت نوبت ناموفق بود: نام واردشده خیلی طولانی است (حداکثر ۴۰ کاراکتر مجاز است).',
            ],
            422,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    $validated = $request->validate([
        'customer_name' => ['required', 'string', 'max:40'],
        'customer_phone' => ['required', 'string'],
        'service_id' => ['required', 'exists:services,id'],
        'appointment_date' => ['required', 'date'],
        'appointment_time' => ['required', 'string'],
    ]);

    // تبدیل اعداد فارسی و عربی شماره موبایل به انگلیسی
    $normalizedPhone = normalizePersianDigits($validated['customer_phone']);

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

    // بررسی ۳: جلوگیری از رزرو تکراری یک ساعت در یک روز (نوبت‌های لغو شده آزاد هستند)
    $alreadyBooked = Appointment::whereDate('appointment_date', $validated['appointment_date'])
        ->where('appointment_time', $validated['appointment_time'])
        ->where('status', '!=', 'cancelled')
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

    $validated['status'] = 'active';
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
