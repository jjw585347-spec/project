<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * بازه‌های زمانی مجاز کاری آرایشگاه (از ۱۰:۰۰ تا ۲۰:۰۰ با فاصله ۳۰ دقیقه)
     */
    public const ALLOWED_TIME_SLOTS = [
        '10:00', '10:30',
        '11:00', '11:30',
        '12:00', '12:30',
        '13:00', '13:30',
        '14:00', '14:30',
        '15:00', '15:30',
        '16:00', '16:30',
        '17:00', '17:30',
        '18:00', '18:30',
        '19:00', '19:30',
        '20:00',
    ];

    /**
     * لیست نوبت‌های یک روز (مخصوص پنل مدیریت - مرتب بر اساس ساعت): GET /api/appointments?date=YYYY-MM-DD
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date') ?: Carbon::now('Asia/Tehran')->toDateString();

        $appointments = Appointment::with(['service:id,name,price,duration', 'customer:id,name,phone'])
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
    }

    /**
     * دریافت فقط ساعت‌های رزروشده (فعال) در یک تاریخ برای فرم رزرو مشتری (بدون اطلاعات شخصی مشتری‌ها):
     * GET /api/appointments/booked-slots?date=YYYY-MM-DD
     */
    public function bookedSlots(Request $request): JsonResponse
    {
        $date = $request->query('date') ?: Carbon::now('Asia/Tehran')->toDateString();

        $slots = Appointment::whereDate('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_time', 'asc')
            ->pluck('appointment_time')
            ->values();

        return response()->json(
            [
                'date' => $date,
                'booked_slots' => $slots,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * بررسی معتبر بودن رشته تاریخ میلادی با فرمت YYYY-MM-DD و صحت تقویمی آن
     */
    private static function isValidGregorianDate(string $date): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    /**
     * ثبت نوبت جدید (متصل به حساب مشتری و بررسی ساعت کاری در بک‌اند): POST /api/appointments
     */
    public function store(Request $request): JsonResponse
    {
        $errors = [];

        $customerName = trim((string) $request->input('customer_name', ''));
        $customerPhone = CustomerController::normalizeDigits(
            (string) $request->input('customer_phone', '')
        );
        $serviceId = $request->input('service_id');
        $rawDate = CustomerController::normalizeDigits((string) $request->input('appointment_date', ''));
        $rawTime = CustomerController::normalizeDigits((string) $request->input('appointment_time', ''));

        // ۱. اعتبارسنجی نام مشتری (خالی نباشد)
        if ($customerName === '') {
            $errors['customer_name'] = 'لطفاً نام و نام خانوادگی را وارد کنید.';
        }

        // ۲. اعتبارسنجی شماره موبایل (۱۱ رقم و شروع با 09 طبق R10)
        if ($customerPhone === '') {
            $errors['customer_phone'] = 'لطفاً شماره موبایل را وارد کنید.';
        } elseif (!preg_match('/^09\d{9}$/', $customerPhone)) {
            $errors['customer_phone'] = 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود (مثلاً 09123456789) وارد کنید.';
        }

        // ۳. اعتبارسنجی خدمت انتخاب‌شده
        if (
            $serviceId === null ||
            $serviceId === '' ||
            !is_numeric($serviceId) ||
            !Service::where('id', (int) $serviceId)->exists()
        ) {
            $errors['service_id'] = 'لطفاً یکی از خدمات معتبر آرایشگاه را انتخاب کنید.';
        }

        // ۴. اعتبارسنجی تاریخ نوبت
        $isDateFormatValid = self::isValidGregorianDate($rawDate);
        $now = Carbon::now('Asia/Tehran');
        $today = $now->copy()->startOfDay();
        $appointmentDate = null;

        if (!$isDateFormatValid) {
            $errors['appointment_date'] = 'لطفاً یک تاریخ معتبر برای نوبت انتخاب کنید.';
        } else {
            $appointmentDate = Carbon::createFromFormat('Y-m-d', $rawDate, 'Asia/Tehran')->startOfDay();
            if ($appointmentDate->lessThan($today)) {
                $errors['appointment_date'] = 'ثبت نوبت ناموفق بود: تاریخ انتخاب‌شده مربوط به گذشته است.';
            }
        }

        // ۵. اعتبارسنجی ساعت نوبت
        if (!in_array($rawTime, self::ALLOWED_TIME_SLOTS, true)) {
            $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده معتبر نیست. ساعت کاری آرایشگاه از ۱۰:۰۰ تا ۲۰:۰۰ است.';
        } elseif ($isDateFormatValid && $appointmentDate !== null) {
            $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', $rawDate . ' ' . $rawTime, 'Asia/Tehran');
            if ($appointmentDate->equalTo($today) && $appointmentDateTime->lessThanOrEqualTo($now)) {
                $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده در امروز گذشته است. لطفاً ساعت دیگری در ادامه امروز یا روز دیگری را انتخاب کنید.';
            } elseif (!$appointmentDate->lessThan($today)) {
                $alreadyBooked = Appointment::whereDate('appointment_date', $rawDate)
                    ->where('appointment_time', $rawTime)
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if ($alreadyBooked) {
                    $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: این ساعت در این روز قبلاً رزرو شده است. لطفاً ساعت یا روز دیگری را انتخاب کنید.';
                }
            }
        }

        if (!empty($errors)) {
            return response()->json(
                [
                    'message' => reset($errors),
                    'errors' => $errors,
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $customerId = $request->input('customer_id');
        if (!$customerId) {
            return response()->json(
                [
                    'message' => 'ثبت نوبت ناموفق بود: ابتدا باید وارد حساب کاربری خود شوید.',
                    'errors' => [
                        'customer_id' => 'ثبت نوبت ناموفق بود: ابتدا باید وارد حساب کاربری خود شوید.',
                    ],
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $customer = Customer::find($customerId);
        if (!$customer) {
            return response()->json(
                [
                    'message' => 'ثبت نوبت ناموفق بود: حساب کاربری شما در سیستم یافت نشد. لطفاً دوباره وارد شوید.',
                    'errors' => [
                        'customer_id' => 'ثبت نوبت ناموفق بود: حساب کاربری شما در سیستم یافت نشد. لطفاً دوباره وارد شوید.',
                    ],
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        // اتصال نوبت به حساب مشتری (customer_id) به همراه نام و شماره قابل ویرایش
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'service_id' => (int) $serviceId,
            'appointment_date' => $rawDate,
            'appointment_time' => $rawTime,
            'status' => 'active',
        ]);

        $appointment->load(['service:id,name,price,duration', 'customer:id,name,phone']);

        return response()->json(
            [
                'message' => 'نوبت شما با موفقیت ثبت شد.',
                'appointment' => $appointment,
            ],
            201,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * لغو نوبت توسط صاحب آرایشگاه (بدون پاک شدن از دیتابیس): PATCH /api/appointments/{id}/cancel
     */
    public function cancel($id): JsonResponse
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(
                [
                    'message' => 'نوبت مورد نظر یافت نشد.',
                ],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $appointment->status = 'cancelled';
        $appointment->save();
        $appointment->load(['service:id,name,price,duration', 'customer:id,name,phone']);

        return response()->json(
            [
                'message' => 'رزرو با موفقیت لغو شد.',
                'appointment' => $appointment,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
