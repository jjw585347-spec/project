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
     * بازه‌های زمانی پایه شروع نوبت در ساعت کاری پیش‌فرض آرایشگاه (از ۱۰:۰۰ تا ۱۹:۳۰؛ پایان کار ۲۰:۰۰ طبق R6)
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
    ];

    /**
     * استخراج داده‌های ورودی از Request (حتی اگر هدر Content-Type ارسال نشده باشد)
     */
    private static function extractInput(Request $request, string $key, mixed $default = null): mixed
    {
        $val = $request->input($key);
        if ($val !== null) {
            return $val;
        }

        $rawContent = (string) $request->getContent();
        if ($rawContent !== '') {
            $decoded = json_decode($rawContent, true);
            if (is_array($decoded) && array_key_exists($key, $decoded)) {
                return $decoded[$key];
            }
        }

        return $default;
    }

    /**
     * لیست نوبت‌های یک روز (مخصوص پنل مدیریت - مرتب بر اساس ساعت): GET /api/appointments?date=YYYY-MM-DD
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date') ?: Carbon::now('Asia/Tehran')->toDateString();

        $appointments = Appointment::with([
            'service:id,name,price,duration',
            'services:id,name,price,duration',
            'customer:id,name,phone',
        ])
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
     * دریافت بازه‌های اشغال‌شده نوبت‌های فعال (با مدت واقعی خدمات R5) در یک تاریخ مشخص
     */
    public static function getActiveBookedIntervalsForDate(string $date): array
    {
        $activeAppointments = Appointment::with([
            'service:id,name,price,duration',
            'services:id,name,price,duration',
        ])
            ->whereDate('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $intervals = [];
        foreach ($activeAppointments as $appt) {
            if (!WorkingScheduleController::isValidHalfHourTime($appt->appointment_time)) {
                continue;
            }
            $startMin = WorkingScheduleController::timeToMinutes($appt->appointment_time);
            $duration = max(1, $appt->total_duration);
            $endMin = $startMin + $duration;

            $intervals[] = [
                'start_time' => $appt->appointment_time,
                'end_time' => WorkingScheduleController::minutesToTime($endMin),
                'start_minutes' => $startMin,
                'end_minutes' => $endMin,
                'duration' => $duration,
            ];
        }

        return $intervals;
    }

    /**
     * محاسبه ساعت‌های شروعی که یک نوبت با مجموع مدت $durationMinutes دقیقه کامل در آن جا می‌شود (R3, R5, R6, R7, R8)
     */
    public static function computeFittingStartSlots(
        string $date,
        int $durationMinutes,
        array $scheduleStatus,
        array $bookedIntervals,
        ?Carbon $now = null
    ): array {
        if ($scheduleStatus['is_closed'] || $durationMinutes < 1) {
            return [];
        }

        $now = $now ?: Carbon::now('Asia/Tehran');
        $todayStr = $now->toDateString();
        $isToday = ($date === $todayStr);
        $nowMinutes = ($now->hour * 60) + $now->minute;

        $workStartMin = WorkingScheduleController::timeToMinutes($scheduleStatus['start_time']);
        $workEndMin = WorkingScheduleController::timeToMinutes($scheduleStatus['end_time']);
        $hourlyLeaves = $scheduleStatus['hourly_leaves'] ?? [];

        $fittingSlots = [];
        foreach ($scheduleStatus['working_slots'] as $candidateSlot) {
            $slotStartMin = WorkingScheduleController::timeToMinutes($candidateSlot);
            $slotEndMin = $slotStartMin + $durationMinutes;

            // ۱. داخل ساعت کاری روز (شروع >= شروع کار و پایان <= پایان کار طبق R6)
            if ($slotStartMin < $workStartMin || $slotEndMin > $workEndMin) {
                continue;
            }

            // ۲. در روز جاری زمان شروع نگذشته باشد (R3)
            if ($isToday && $slotStartMin <= $nowMinutes) {
                continue;
            }

            // ۳. عدم برخورد کل بازه [slotStartMin, slotEndMin) با مرخصی ساعتی (R7)
            $hitsLeave = false;
            foreach ($hourlyLeaves as $leave) {
                $leaveStartMin = WorkingScheduleController::timeToMinutes($leave['start_time']);
                $leaveEndMin = WorkingScheduleController::timeToMinutes($leave['end_time']);
                if (WorkingScheduleController::intervalsOverlap($slotStartMin, $slotEndMin, $leaveStartMin, $leaveEndMin)) {
                    $hitsLeave = true;
                    break;
                }
            }
            if ($hitsLeave) {
                continue;
            }

            // ۴. عدم هم‌پوشانی کل بازه [slotStartMin, slotEndMin) با هیچ‌یک از نوبت‌های فعال دیگر (R5)
            $hitsBooking = false;
            foreach ($bookedIntervals as $interval) {
                if (WorkingScheduleController::intervalsOverlap(
                    $slotStartMin,
                    $slotEndMin,
                    $interval['start_minutes'],
                    $interval['end_minutes']
                )) {
                    $hitsBooking = true;
                    break;
                }
            }
            if ($hitsBooking) {
                continue;
            }

            $fittingSlots[] = $candidateSlot;
        }

        return $fittingSlots;
    }

    /**
     * دریافت وضعیت زمان‌های یک تاریخ برای فرم رزرو مشتری (بدون اطلاعات شخصی مشتری‌ها):
     * GET /api/appointments/booked-slots?date=YYYY-MM-DD(&duration=... یا &service_ids=1,2)
     */
    public function bookedSlots(Request $request): JsonResponse
    {
        $rawDate = CustomerController::normalizeDigits((string) $request->query('date', ''));
        $date = self::isValidGregorianDate($rawDate)
            ? $rawDate
            : Carbon::now('Asia/Tehran')->toDateString();

        $scheduleStatus = WorkingScheduleController::getDateScheduleStatus($date);
        $bookedIntervals = self::getActiveBookedIntervalsForDate($date);

        // تمام گام‌های ۳۰ دقیقه‌ای که داخل بازه نوبت‌های فعال قرار می‌گیرند (برای booked_slots)
        $bookedSlots = [];
        foreach ($scheduleStatus['working_slots'] as $slot) {
            $slotMin = WorkingScheduleController::timeToMinutes($slot);
            foreach ($bookedIntervals as $interval) {
                if (WorkingScheduleController::intervalsOverlap($slotMin, $slotMin + 30, $interval['start_minutes'], $interval['end_minutes'])) {
                    $bookedSlots[] = $slot;
                    break;
                }
            }
        }

        // محاسبه مجموع مدت خدمات انتخاب‌شده (در صورت ارسال service_ids یا duration)
        $requestedDuration = 30;
        $rawDuration = CustomerController::normalizeDigits((string) $request->query('duration', ''));
        $rawServiceIds = $request->query('service_ids');

        if ($rawServiceIds !== null && $rawServiceIds !== '') {
            $ids = is_array($rawServiceIds)
                ? $rawServiceIds
                : explode(',', CustomerController::normalizeDigits((string) $rawServiceIds));
            $cleanIds = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
            if (!empty($cleanIds)) {
                $sumDuration = (int) Service::whereIn('id', $cleanIds)
                    ->where('is_active', true)
                    ->sum('duration');
                if ($sumDuration > 0) {
                    $requestedDuration = $sumDuration;
                }
            }
        } elseif ($rawDuration !== '' && preg_match('/^\d+$/', $rawDuration) && (int) $rawDuration > 0) {
            $requestedDuration = (int) $rawDuration;
        }

        $availableSlots = self::computeFittingStartSlots(
            $date,
            $requestedDuration,
            $scheduleStatus,
            $bookedIntervals
        );

        return response()->json(
            [
                'date' => $date,
                'booked_slots' => array_values(array_unique($bookedSlots)),
                'booked_intervals' => array_map(fn (array $item) => [
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'duration' => $item['duration'],
                ], $bookedIntervals),
                'available_slots' => $availableSlots,
                'requested_duration' => $requestedDuration,
                'is_closed' => $scheduleStatus['is_closed'],
                'close_reason' => $scheduleStatus['close_reason'],
                'day_name' => $scheduleStatus['day_name'],
                'start_time' => $scheduleStatus['start_time'],
                'end_time' => $scheduleStatus['end_time'],
                'working_slots' => $scheduleStatus['working_slots'],
                'leave_slots' => $scheduleStatus['leave_slots'],
                'hourly_leaves' => $scheduleStatus['hourly_leaves'],
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
     * استخراج و اعتبارسنجی یک یا چند خدمت انتخاب‌شده از ورودی (service_ids یا service_id طبق R8)
     *
     * @return array{0: ?string, 1: \Illuminate\Support\Collection<int, Service>}
     */
    private static function resolveSelectedServices(Request $request): array
    {
        $rawServiceIds = self::extractInput($request, 'service_ids');
        $rawSingleId = self::extractInput($request, 'service_id');

        $candidateList = [];

        if (is_array($rawServiceIds)) {
            $candidateList = $rawServiceIds;
        } elseif (is_string($rawServiceIds) && trim($rawServiceIds) !== '') {
            $candidateList = explode(',', $rawServiceIds);
        } elseif ($rawSingleId !== null && $rawSingleId !== '') {
            $candidateList = is_array($rawSingleId) ? $rawSingleId : [$rawSingleId];
        }

        if (empty($candidateList)) {
            return ['لطفاً یکی از خدمات معتبر آرایشگاه را انتخاب کنید.', collect()];
        }

        $normalizedIds = [];
        foreach ($candidateList as $item) {
            $clean = CustomerController::normalizeDigits(trim((string) $item));
            if ($clean === '' || !preg_match('/^\d+$/', $clean) || (int) $clean <= 0) {
                return ['لطفاً یکی از خدمات معتبر آرایشگاه را انتخاب کنید.', collect()];
            }
            $id = (int) $clean;
            if (!in_array($id, $normalizedIds, true)) {
                $normalizedIds[] = $id;
            }
        }

        if (empty($normalizedIds)) {
            return ['لطفاً یکی از خدمات معتبر آرایشگاه را انتخاب کنید.', collect()];
        }

        $foundServices = Service::whereIn('id', $normalizedIds)->get()->keyBy('id');
        if ($foundServices->count() !== count($normalizedIds)) {
            return ['لطفاً یکی از خدمات معتبر آرایشگاه را انتخاب کنید.', collect()];
        }

        $orderedServices = collect();
        foreach ($normalizedIds as $id) {
            /** @var Service $srv */
            $srv = $foundServices->get($id);
            if (!$srv->is_active) {
                return ['خدمت انتخاب‌شده در حال حاضر غیرفعال است و امکان رزرو آن وجود ندارد.', collect()];
            }
            $orderedServices->push($srv);
        }

        return [null, $orderedServices];
    }

    /**
     * ثبت نوبت جدید برای یک یا چند خدمت و بررسی کامل جا شدن نوبت در بک‌اند (R3, R5, R6, R7, R8):
     * POST /api/appointments
     */
    public function store(Request $request): JsonResponse
    {
        $errors = [];

        $customerName = trim((string) self::extractInput($request, 'customer_name', ''));
        $customerPhone = CustomerController::normalizeDigits(
            (string) self::extractInput($request, 'customer_phone', '')
        );
        $rawDate = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'appointment_date', ''))
        );
        $rawTime = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'appointment_time', ''))
        );

        // ۱. اعتبارسنجی نام مشتری (خالی نباشد)
        if ($customerName === '') {
            $errors['customer_name'] = 'لطفاً نام و نام خانوادگی را وارد کنید.';
        }

        // ۲. اعتبارسنجی شماره موبایل (۱۱ رقم و شروع با 09 طبق R10 / R18)
        if ($customerPhone === '') {
            $errors['customer_phone'] = 'لطفاً شماره موبایل را وارد کنید.';
        } elseif (!preg_match('/^09\d{9}$/', $customerPhone)) {
            $errors['customer_phone'] = 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود (مثلاً 09123456789) وارد کنید.';
        }

        // ۳. اعتبارسنجی خدمت یا خدمات انتخاب‌شده (یک یا چند خدمت فعال طبق R8)
        [$serviceError, $selectedServices] = self::resolveSelectedServices($request);
        if ($serviceError !== null) {
            $errors['service_id'] = $serviceError;
        }

        $totalDuration = $selectedServices->isNotEmpty()
            ? max(1, (int) $selectedServices->sum('duration'))
            : 30;

        // ۴. اعتبارسنجی تاریخ نوبت و بررسی روز تعطیل هفتگی یا تاریخ تعطیل خاص (R7)
        $isDateFormatValid = self::isValidGregorianDate($rawDate);
        $now = Carbon::now('Asia/Tehran');
        $today = $now->copy()->startOfDay();
        $appointmentDate = null;
        $scheduleStatus = null;

        if (!$isDateFormatValid) {
            $errors['appointment_date'] = 'لطفاً یک تاریخ معتبر برای نوبت انتخاب کنید.';
        } else {
            $appointmentDate = Carbon::createFromFormat('Y-m-d', $rawDate, 'Asia/Tehran')->startOfDay();
            if ($appointmentDate->lessThan($today)) {
                $errors['appointment_date'] = 'ثبت نوبت ناموفق بود: تاریخ انتخاب‌شده مربوط به گذشته است.';
            } else {
                $scheduleStatus = WorkingScheduleController::getDateScheduleStatus($rawDate);
                if ($scheduleStatus['is_closed']) {
                    $reasonText = $scheduleStatus['close_reason'] ?: 'آرایشگاه در این روز تعطیل است.';
                    $errors['appointment_date'] = "ثبت نوبت ناموفق بود: {$reasonText} لطفاً روز دیگری را انتخاب کنید.";
                }
            }
        }

        // ۵. اعتبارسنجی ساعت نوبت و جا شدن کامل مجموع خدمات انتخاب‌شده در بازه (R3, R5, R6, R7, R8)
        if (!WorkingScheduleController::isValidHalfHourTime($rawTime)) {
            $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده معتبر نیست. ساعت کاری آرایشگاه از ۱۰:۰۰ تا ۲۰:۰۰ است.';
        } elseif ($scheduleStatus !== null && !$scheduleStatus['is_closed']) {
            $slotStartMin = WorkingScheduleController::timeToMinutes($rawTime);
            $slotEndMin = $slotStartMin + $totalDuration;
            $workStartMin = WorkingScheduleController::timeToMinutes($scheduleStatus['start_time']);
            $workEndMin = WorkingScheduleController::timeToMinutes($scheduleStatus['end_time']);
            $slotEndTimeStr = WorkingScheduleController::minutesToTime($slotEndMin);

            // الف) بررسی شروع داخل ساعت کاری روز
            if ($slotStartMin < $workStartMin || $slotStartMin >= $workEndMin) {
                $errors['appointment_time'] = "ثبت نوبت ناموفق بود: ساعت انتخاب‌شده خارج از ساعت کاری آرایشگاه در روز {$scheduleStatus['day_name']} ({$scheduleStatus['start_time']} تا {$scheduleStatus['end_time']}) است.";
            } else {
                // ب) بررسی زمان گذشته در روز جاری (R3)
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', $rawDate . ' ' . $rawTime, 'Asia/Tehran');
                if ($appointmentDate->equalTo($today) && $appointmentDateTime->lessThanOrEqualTo($now)) {
                    $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده در امروز گذشته است. لطفاً ساعت دیگری در ادامه امروز یا روز دیگری را انتخاب کنید.';
                } else {
                    // ج) بررسی برخورد کل بازه نوبت [slotStartMin, slotEndMin) با مرخصی‌های ساعتی آن روز (R7)
                    $overlapsLeave = false;
                    foreach ($scheduleStatus['hourly_leaves'] as $leave) {
                        $leaveStartMin = WorkingScheduleController::timeToMinutes($leave['start_time']);
                        $leaveEndMin = WorkingScheduleController::timeToMinutes($leave['end_time']);
                        if (WorkingScheduleController::intervalsOverlap($slotStartMin, $slotEndMin, $leaveStartMin, $leaveEndMin)) {
                            $overlapsLeave = true;
                            break;
                        }
                    }

                    if ($overlapsLeave) {
                        $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: در این بازه زمانی مرخصی ثبت شده است و امکان رزرو نوبت وجود ندارد.';
                    } elseif ($slotEndMin > $workEndMin) {
                        // د) بررسی تمام شدن کل خدمات قبل از پایان ساعت کاری روز (R6)
                        $errors['appointment_time'] = "ثبت نوبت ناموفق بود: مجموع زمان خدمات انتخاب‌شده ({$totalDuration} دقیقه تا ساعت {$slotEndTimeStr}) از ساعت پایان کار آرایشگاه ({$scheduleStatus['end_time']}) فراتر می‌رود.";
                    } else {
                        // هـ) بررسی عدم هم‌پوشانی کل بازه [slotStartMin, slotEndMin) با نوبت‌های فعال قبلی (R5)
                        $bookedIntervals = self::getActiveBookedIntervalsForDate($rawDate);
                        $exactSlotBooked = false;
                        $overlapsOtherBooking = false;

                        foreach ($bookedIntervals as $interval) {
                            if (WorkingScheduleController::intervalsOverlap(
                                $slotStartMin,
                                $slotEndMin,
                                $interval['start_minutes'],
                                $interval['end_minutes']
                            )) {
                                if ($interval['start_minutes'] === $slotStartMin) {
                                    $exactSlotBooked = true;
                                } else {
                                    $overlapsOtherBooking = true;
                                }
                                break;
                            }
                        }

                        if ($exactSlotBooked) {
                            $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: این ساعت در این روز قبلاً رزرو شده است. لطفاً ساعت یا روز دیگری را انتخاب کنید.';
                        } elseif ($overlapsOtherBooking) {
                            $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: بازه زمانی خدمات انتخاب‌شده با یکی از نوبت‌های فعال دیگر هم‌پوشانی دارد. لطفاً ساعت یا روز دیگری را انتخاب کنید.';
                        }
                    }
                }
            }
        } elseif (!in_array($rawTime, self::ALLOWED_TIME_SLOTS, true)) {
            $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده معتبر نیست. ساعت کاری آرایشگاه از ۱۰:۰۰ تا ۲۰:۰۰ است.';
        } elseif ($isDateFormatValid && $appointmentDate !== null) {
            $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', $rawDate . ' ' . $rawTime, 'Asia/Tehran');
            if ($appointmentDate->equalTo($today) && $appointmentDateTime->lessThanOrEqualTo($now)) {
                $errors['appointment_time'] = 'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده در امروز گذشته است. لطفاً ساعت دیگری در ادامه امروز یا روز دیگری را انتخاب کنید.';
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

        $customerId = self::extractInput($request, 'customer_id');
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

        /** @var Service $primaryService */
        $primaryService = $selectedServices->first();

        $trackingCode = Appointment::generateUniqueTrackingCode();

        // اتصال نوبت به حساب مشتری و ذخیره تمام خدمات انتخاب‌شده در جدول رابط appointment_service
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'service_id' => $primaryService->id,
            'appointment_date' => $rawDate,
            'appointment_time' => $rawTime,
            'status' => 'active',
            'tracking_code' => $trackingCode,
        ]);

        $appointment->services()->sync($selectedServices->pluck('id')->all());
        $appointment->load([
            'service:id,name,price,duration',
            'services:id,name,price,duration',
            'customer:id,name,phone',
        ]);

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
     * اعتبارسنجی شماره موبایل و کد پیگیری ۶ رقمی برای صفحه «پیگیری نوبت» (R9)
     *
     * @return array{0: array<string, string>, 1: string, 2: string}
     */
    private static function validateTrackingCredentials(Request $request): array
    {
        $errors = [];

        $customerPhone = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'customer_phone', ''))
        );
        $trackingCode = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'tracking_code', ''))
        );

        if ($customerPhone === '') {
            $errors['customer_phone'] = 'لطفاً شماره موبایل را وارد کنید.';
        } elseif (!preg_match('/^09\d{9}$/', $customerPhone)) {
            $errors['customer_phone'] = 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود وارد کنید.';
        }

        if ($trackingCode === '') {
            $errors['tracking_code'] = 'لطفاً کد پیگیری ۶ رقمی را وارد کنید.';
        } elseif (!preg_match('/^\d{6}$/', $trackingCode)) {
            $errors['tracking_code'] = 'کد پیگیری باید یک عدد ۶ رقمی باشد.';
        }

        return [$errors, $customerPhone, $trackingCode];
    }

    /**
     * پیگیری و مشاهده فقط همان نوبت با شماره موبایل و کد پیگیری ۶ رقمی (R9):
     * POST /api/appointments/track
     */
    public function track(Request $request): JsonResponse
    {
        [$errors, $customerPhone, $trackingCode] = self::validateTrackingCredentials($request);

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

        $appointment = Appointment::with([
            'service:id,name,price,duration',
            'services:id,name,price,duration',
        ])
            ->where('customer_phone', $customerPhone)
            ->where('tracking_code', $trackingCode)
            ->first();

        if (!$appointment) {
            return response()->json(
                [
                    'message' => 'نوبتی با این شماره موبایل و کد پیگیری یافت نشد. لطفاً اطلاعات واردشده را بررسی کنید.',
                ],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        return response()->json(
            [
                'appointment' => $appointment,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * لغو نوبت توسط خود مشتری با شماره موبایل و کد پیگیری تا مهلت لغو (پیش‌فرض ۲ ساعت قبل طبق R9):
     * POST /api/appointments/track/cancel
     */
    public function customerCancel(Request $request): JsonResponse
    {
        [$errors, $customerPhone, $trackingCode] = self::validateTrackingCredentials($request);

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

        $appointment = Appointment::with([
            'service:id,name,price,duration',
            'services:id,name,price,duration',
        ])
            ->where('customer_phone', $customerPhone)
            ->where('tracking_code', $trackingCode)
            ->first();

        if (!$appointment) {
            return response()->json(
                [
                    'message' => 'نوبتی با این شماره موبایل و کد پیگیری یافت نشد. لطفاً اطلاعات واردشده را بررسی کنید.',
                ],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $evaluation = $appointment->evaluateCustomerCancellation();
        if (!$evaluation['can_cancel']) {
            return response()->json(
                [
                    'message' => $evaluation['reason'] ?: 'مهلت لغو این نوبت به پایان رسیده است.',
                    'appointment' => $appointment,
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $appointment->status = 'cancelled';
        $appointment->save();
        $appointment->load([
            'service:id,name,price,duration',
            'services:id,name,price,duration',
        ]);

        return response()->json(
            [
                'message' => 'نوبت شما با موفقیت لغو شد.',
                'appointment' => $appointment,
            ],
            200,
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
        $appointment->load([
            'service:id,name,price,duration',
            'services:id,name,price,duration',
            'customer:id,name,phone',
        ]);

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
