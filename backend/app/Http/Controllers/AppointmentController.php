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
     * لیست نوبت‌های یک روز (مرتب بر اساس ساعت): GET /api/appointments?date=YYYY-MM-DD
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
     * ثبت نوبت جدید (متصل به حساب مشتری و بررسی ساعت کاری در بک‌اند): POST /api/appointments
     */
    public function store(Request $request): JsonResponse
    {
        $customerId = $request->input('customer_id');
        if (!$customerId) {
            return response()->json(
                [
                    'message' => 'ثبت نوبت ناموفق بود: ابتدا باید وارد حساب کاربری خود شوید.',
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
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        // مقدار پیش‌فرض از حساب مشتری است اما کاربر می‌تواند در فرم آن را تغییر دهد
        $customerName = trim((string) $request->input('customer_name', $customer->name));
        $customerPhone = CustomerController::normalizeDigits(
            (string) $request->input('customer_phone', $customer->phone)
        );

        if ($customerName === '' || $customerPhone === '') {
            return response()->json(
                [
                    'message' => 'لطفاً نام و شماره موبایل را کامل وارد کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (mb_strlen($customerName) > 40) {
            return response()->json(
                [
                    'message' => 'ثبت نوبت ناموفق بود: نام واردشده خیلی طولانی است (حداکثر ۴۰ کاراکتر مجاز است).',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (!preg_match('/^09\d{9}$/', $customerPhone)) {
            return response()->json(
                [
                    'message' => 'ثبت نوبت ناموفق بود: شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با 09 شروع می‌شود (مثلاً 09123456789) وارد کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $serviceId = $request->input('service_id');
        if (!$serviceId || !Service::where('id', $serviceId)->exists()) {
            return response()->json(
                [
                    'message' => 'لطفاً یکی از خدمات معتبر آرایشگاه را انتخاب کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $rawDate = CustomerController::normalizeDigits((string) $request->input('appointment_date', ''));
        if ($rawDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) || !strtotime($rawDate)) {
            return response()->json(
                [
                    'message' => 'لطفاً یک تاریخ معتبر برای نوبت انتخاب کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $rawTime = CustomerController::normalizeDigits((string) $request->input('appointment_time', ''));
        if (!in_array($rawTime, self::ALLOWED_TIME_SLOTS, true)) {
            return response()->json(
                [
                    'message' => 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده معتبر نیست. ساعت کاری آرایشگاه از ۱۰:۰۰ تا ۲۰:۰۰ است.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $now = Carbon::now('Asia/Tehran');
        $appointmentDate = Carbon::parse($rawDate, 'Asia/Tehran')->startOfDay();
        $today = $now->copy()->startOfDay();

        // بررسی ۱: اگر تاریخ واردشده برای روزهای گذشته باشد
        if ($appointmentDate->lessThan($today)) {
            return response()->json(
                [
                    'message' => 'ثبت نوبت ناموفق بود: تاریخ انتخاب‌شده مربوط به گذشته است.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        // بررسی ۲: اگر تاریخ برای امروز باشد ولی ساعت آن گذشته باشد
        $appointmentDateTime = Carbon::parse($rawDate . ' ' . $rawTime, 'Asia/Tehran');
        if ($appointmentDate->equalTo($today) && $appointmentDateTime->lessThanOrEqualTo($now)) {
            return response()->json(
                [
                    'message' => 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده در امروز گذشته است. لطفاً ساعت دیگری در ادامه امروز یا روز دیگری را انتخاب کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        // بررسی ۳: جلوگیری از رزرو تکراری یک ساعت در یک روز (نوبت‌های لغوشده آزاد هستند)
        $alreadyBooked = Appointment::whereDate('appointment_date', $rawDate)
            ->where('appointment_time', $rawTime)
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
