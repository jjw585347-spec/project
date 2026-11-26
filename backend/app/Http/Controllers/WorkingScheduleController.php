<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkingScheduleController extends Controller
{
    /**
     * نام فارسی روزهای هفته به ترتیب شماره 0 (شنبه) تا 6 (جمعه)
     */
    public const DAY_NAMES = [
        0 => 'شنبه',
        1 => 'یکشنبه',
        2 => 'دوشنبه',
        3 => 'سه‌شنبه',
        4 => 'چهارشنبه',
        5 => 'پنج‌شنبه',
        6 => 'جمعه',
    ];

    /**
     * تبدیل تاریخ میلادی (Carbon) به شماره روز هفته ایرانی (0 = شنبه ... 6 = جمعه)
     */
    public static function getIranianDayOfWeek(Carbon $date): int
    {
        // در Carbon: 0 = یکشنبه ... 6 = شنبه -> با (dayOfWeek + 1) % 7 داریم: 0 = شنبه ... 6 = جمعه
        return ($date->dayOfWeek + 1) % 7;
    }

    /**
     * اطمینان از وجود ۷ ردیف پیش‌فرض ساعت کاری هفتگی در دیتابیس بر اساس مقادیر اولیه فایل تنظیمات (config/app.php)
     */
    public static function ensureDefaultWeeklyHours(): void
    {
        $existingDays = WorkingHour::pluck('day_of_week')->all();
        if (count($existingDays) === 7) {
            return;
        }

        $defaultStart = (string) config('app.default_work_start_time');
        $defaultEnd = (string) config('app.default_work_end_time');
        $defaultClosedDay = (int) config('app.default_closed_day_of_week');

        for ($day = 0; $day <= 6; $day++) {
            if (!in_array($day, $existingDays, true)) {
                WorkingHour::create([
                    'day_of_week' => $day,
                    'start_time' => $defaultStart,
                    'end_time' => $defaultEnd,
                    'is_closed' => $day === $defaultClosedDay,
                ]);
            }
        }
    }

    /**
     * تولید بازه‌های زمانی شروع نوبت بین دو ساعت (از start_time تا قبل از end_time طبق R6)
     */
    public static function generateSlotsBetween(string $startTime, string $endTime): array
    {
        if (!self::isValidHalfHourTime($startTime) || !self::isValidHalfHourTime($endTime)) {
            return [];
        }

        $startMinutes = self::timeToMinutes($startTime);
        $endMinutes = self::timeToMinutes($endTime);
        $stepMinutes = max(1, (int) config('app.slot_step_minutes'));

        if ($startMinutes >= $endMinutes) {
            return [];
        }

        $slots = [];
        for ($m = $startMinutes; $m < $endMinutes; $m += $stepMinutes) {
            $slots[] = self::minutesToTime($m);
        }

        return $slots;
    }

    /**
     * تبدیل دقیقه از ابتدای روز به رشته HH:MM
     */
    public static function minutesToTime(int $minutes): string
    {
        $h = str_pad((string) intdiv($minutes, 60), 2, '0', STR_PAD_LEFT);
        $min = str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);

        return "{$h}:{$min}";
    }

    /**
     * بررسی هم‌پوشانی دو بازه زمانی نیم‌باز [startA, endA) و [startB, endB) به دقیقه
     */
    public static function intervalsOverlap(int $startA, int $endA, int $startB, int $endB): bool
    {
        return $startA < $endB && $endA > $startB;
    }

    /**
     * بررسی اینکه رشته ساعت با فرمت HH:00 یا HH:30 معتبر است (از 00:00 تا 23:30)
     */
    public static function isValidHalfHourTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):(00|30)$/', $time);
    }

    /**
     * تبدیل رشته HH:MM به دقیقه از ابتدای روز
     */
    public static function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return ($h * 60) + $m;
    }

    /**
     * بررسی اینکه آیا یک ساعت مشخص (مثلاً 14:30) داخل بازه مرخصی ساعتی [start_time, end_time] قرار می‌گیرد یا خیر
     */
    public static function isSlotInsideLeave(string $slot, string $leaveStart, string $leaveEnd): bool
    {
        $slotMin = self::timeToMinutes($slot);
        $startMin = self::timeToMinutes($leaveStart);
        $endMin = self::timeToMinutes($leaveEnd);

        // اگر مرخصی از ۱۴:۰۰ تا ۱۶:۰۰ باشد، ساعت‌های شروع ۱۴:۰۰ تا ۱۵:۳۰ (یا اگر شروع=پایان باشد همان ساعت) مسدود می‌شوند
        if ($startMin === $endMin) {
            return $slotMin === $startMin;
        }

        return $slotMin >= $startMin && $slotMin < $endMin;
    }

    /**
     * استخراج داده‌های ورودی از Request (حتی اگر Content-Type ارسال نشده باشد)
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
     * دریافت وضعیت کامل کاری یک تاریخ مشخص (YYYY-MM-DD):
     * - آیا روز هفته تعطیل است؟
     * - آیا تاریخ تعطیل خاص (full_day) ثبت شده است؟
     * - بازه‌های کاری آن روز هفته چیست؟
     * - کدام ساعت‌ها در مرخصی ساعتی (hourly) هستند؟
     */
    public static function getDateScheduleStatus(string $date): array
    {
        self::ensureDefaultWeeklyHours();

        $carbonDate = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Tehran')->startOfDay();
        $dayOfWeek = self::getIranianDayOfWeek($carbonDate);
        $dayName = self::DAY_NAMES[$dayOfWeek] ?? '';

        /** @var WorkingHour|null $weeklyHour */
        $weeklyHour = WorkingHour::where('day_of_week', $dayOfWeek)->first();
        $defaultStart = (string) config('app.default_work_start_time');
        $defaultEnd = (string) config('app.default_work_end_time');
        $defaultClosedDay = (int) config('app.default_closed_day_of_week');

        $startTime = $weeklyHour ? $weeklyHour->start_time : $defaultStart;
        $endTime = $weeklyHour ? $weeklyHour->end_time : $defaultEnd;
        $isWeeklyClosed = $weeklyHour ? (bool) $weeklyHour->is_closed : ($dayOfWeek === $defaultClosedDay);

        $fullDayHoliday = TimeOff::whereDate('off_date', $date)
            ->where('type', 'full_day')
            ->first();

        $hourlyLeaves = TimeOff::whereDate('off_date', $date)
            ->where('type', 'hourly')
            ->orderBy('start_time', 'asc')
            ->get();

        $isClosed = false;
        $closeReason = null;

        if ($fullDayHoliday) {
            $isClosed = true;
            $closeReason = $fullDayHoliday->reason
                ? "این تاریخ به دلیل «{$fullDayHoliday->reason}» تعطیل است."
                : 'این تاریخ در تقویم کاری آرایشگاه تعطیل اعلام شده است.';
        } elseif ($isWeeklyClosed) {
            $isClosed = true;
            $closeReason = "آرایشگاه در روزهای {$dayName} تعطیل است.";
        }

        $workingSlots = $isClosed ? [] : self::generateSlotsBetween($startTime, $endTime);

        $leaveSlots = [];
        if (!$isClosed && $hourlyLeaves->isNotEmpty()) {
            foreach ($workingSlots as $slot) {
                foreach ($hourlyLeaves as $leave) {
                    if ($leave->start_time && $leave->end_time && self::isSlotInsideLeave($slot, $leave->start_time, $leave->end_time)) {
                        $leaveSlots[] = $slot;
                        break;
                    }
                }
            }
        }

        return [
            'date' => $date,
            'day_of_week' => $dayOfWeek,
            'day_name' => $dayName,
            'is_closed' => $isClosed,
            'is_weekly_closed' => $isWeeklyClosed,
            'is_holiday' => (bool) $fullDayHoliday,
            'close_reason' => $closeReason,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'working_slots' => $workingSlots,
            'leave_slots' => array_values(array_unique($leaveSlots)),
            'hourly_leaves' => $hourlyLeaves->map(fn (TimeOff $l) => [
                'id' => $l->id,
                'start_time' => $l->start_time,
                'end_time' => $l->end_time,
                'reason' => $l->reason,
            ])->values()->all(),
        ];
    }

    /**
     * دریافت کل تنظیمات ساعت کاری هفتگی، تعطیلی‌های خاص و مرخصی‌های ساعتی برای پنل مدیریت:
     * GET /api/admin/schedule
     */
    public function index(): JsonResponse
    {
        self::ensureDefaultWeeklyHours();

        $weeklyHours = WorkingHour::orderBy('day_of_week', 'asc')
            ->get()
            ->map(fn (WorkingHour $wh) => [
                'id' => $wh->id,
                'day_of_week' => $wh->day_of_week,
                'day_name' => self::DAY_NAMES[$wh->day_of_week] ?? '',
                'start_time' => $wh->start_time,
                'end_time' => $wh->end_time,
                'is_closed' => (bool) $wh->is_closed,
            ])
            ->values();

        $holidays = TimeOff::where('type', 'full_day')
            ->orderBy('off_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $leaves = TimeOff::where('type', 'hourly')
            ->orderBy('off_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json(
            [
                'weekly_hours' => $weeklyHours,
                'holidays' => $holidays,
                'leaves' => $leaves,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * ذخیره ساعت کاری روزهای هفته (شنبه تا جمعه) توسط صاحب کار:
     * PUT /api/admin/schedule/weekly
     * اگر تغییر ساعت کاری یا تعطیل کردن یک روز هفته با نوبت‌های فعال از قبل ثبت‌شده تداخل داشته باشد،
     * به صاحب کار هشدار می‌دهد و هیچ نوبتی را خودکار لغو نمی‌کند.
     */
    public function updateWeekly(Request $request): JsonResponse
    {
        self::ensureDefaultWeeklyHours();

        $daysInput = self::extractInput($request, 'weekly_hours', []);
        if (!is_array($daysInput) || count($daysInput) !== 7) {
            return response()->json(
                [
                    'message' => 'اطلاعات ساعت کاری هر ۷ روز هفته (شنبه تا جمعه) باید ارسال شود.',
                    'errors' => [
                        'weekly_hours' => 'اطلاعات ساعت کاری هر ۷ روز هفته (شنبه تا جمعه) باید ارسال شود.',
                    ],
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $errors = [];
        $normalizedDays = [];

        foreach ($daysInput as $idx => $item) {
            if (!is_array($item) || !array_key_exists('day_of_week', $item)) {
                $errors['weekly_hours'] = 'فرمت اطلاعات روزهای هفته معتبر نیست.';
                break;
            }

            $dayOfWeek = (int) $item['day_of_week'];
            if ($dayOfWeek < 0 || $dayOfWeek > 6) {
                $errors["day_{$idx}"] = 'شماره روز هفته باید بین ۰ (شنبه) تا ۶ (جمعه) باشد.';
                continue;
            }

            $defaultStart = (string) config('app.default_work_start_time');
            $defaultEnd = (string) config('app.default_work_end_time');
            $isClosed = filter_var($item['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $startTime = CustomerController::normalizeDigits(trim((string) ($item['start_time'] ?? $defaultStart)));
            $endTime = CustomerController::normalizeDigits(trim((string) ($item['end_time'] ?? $defaultEnd)));
            $dayName = self::DAY_NAMES[$dayOfWeek];

            if (!$isClosed) {
                if (!self::isValidHalfHourTime($startTime) || !self::isValidHalfHourTime($endTime)) {
                    $errors["day_{$dayOfWeek}"] = "ساعت شروع و پایان روز {$dayName} باید با فرمت معتبر نیم‌ساعته باشد.";
                    continue;
                }

                if (self::timeToMinutes($startTime) >= self::timeToMinutes($endTime)) {
                    $errors["day_{$dayOfWeek}"] = "در روز {$dayName} ساعت پایان باید بعد از ساعت شروع باشد.";
                    continue;
                }
            } else {
                if (!self::isValidHalfHourTime($startTime)) {
                    $startTime = $defaultStart;
                }
                if (!self::isValidHalfHourTime($endTime)) {
                    $endTime = $defaultEnd;
                }
            }

            $normalizedDays[$dayOfWeek] = [
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_closed' => $isClosed,
            ];
        }

        if (empty($errors) && count($normalizedDays) !== 7) {
            $errors['weekly_hours'] = 'لطفاً تمام ۷ روز هفته (شنبه تا جمعه) را مشخص کنید.';
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

        foreach ($normalizedDays as $dayData) {
            WorkingHour::updateOrCreate(
                ['day_of_week' => $dayData['day_of_week']],
                [
                    'start_time' => $dayData['start_time'],
                    'end_time' => $dayData['end_time'],
                    'is_closed' => $dayData['is_closed'],
                ]
            );
        }

        // بررسی تداخل با نوبت‌های فعال از امروز به بعد (بدون لغو خودکار - با احتساب مدت واقعی خدمات نوبت طبق R5 و R6)
        $todayStr = Carbon::now('Asia/Tehran')->toDateString();
        $activeFutureAppointments = Appointment::with(['service:id,name,duration', 'services:id,name,duration'])
            ->whereDate('appointment_date', '>=', $todayStr)
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        $conflictingAppointments = [];
        foreach ($activeFutureAppointments as $appt) {
            $apptDateStr = Carbon::parse($appt->appointment_date)->toDateString();
            $apptCarbon = Carbon::createFromFormat('Y-m-d', $apptDateStr, 'Asia/Tehran');
            $dow = self::getIranianDayOfWeek($apptCarbon);
            $rule = $normalizedDays[$dow] ?? null;
            if (!$rule) {
                continue;
            }

            $apptStartMin = self::timeToMinutes($appt->appointment_time);
            $apptEndMin = $apptStartMin + max(1, $appt->total_duration);
            $dayStartMin = self::timeToMinutes($rule['start_time']);
            $dayEndMin = self::timeToMinutes($rule['end_time']);

            if ($rule['is_closed'] || $apptStartMin < $dayStartMin || $apptEndMin > $dayEndMin) {
                $conflictingAppointments[] = [
                    'id' => $appt->id,
                    'customer_name' => $appt->customer_name,
                    'customer_phone' => $appt->customer_phone,
                    'appointment_date' => $apptDateStr,
                    'appointment_time' => $appt->appointment_time,
                    'service_name' => $appt->services->isNotEmpty()
                        ? $appt->services->pluck('name')->implode('، ')
                        : $appt->service?->name,
                    'day_name' => self::DAY_NAMES[$dow] ?? '',
                ];
            }
        }

        $warning = null;
        if (!empty($conflictingAppointments)) {
            $count = count($conflictingAppointments);
            $warning = "هشدار: {$count} نوبت فعال از قبل در زمان‌هایی که اکنون تعطیل یا خارج از ساعت کاری جدید هستند ثبت شده است. این نوبت‌ها به صورت خودکار لغو نشدند؛ در صورت نیاز می‌توانید آن‌ها را به صورت دستی مدیریت کنید.";
        }

        $weeklyHours = WorkingHour::orderBy('day_of_week', 'asc')
            ->get()
            ->map(fn (WorkingHour $wh) => [
                'id' => $wh->id,
                'day_of_week' => $wh->day_of_week,
                'day_name' => self::DAY_NAMES[$wh->day_of_week] ?? '',
                'start_time' => $wh->start_time,
                'end_time' => $wh->end_time,
                'is_closed' => (bool) $wh->is_closed,
            ])
            ->values();

        return response()->json(
            [
                'message' => 'ساعت کاری هفتگی با موفقیت ذخیره شد.',
                'weekly_hours' => $weeklyHours,
                'warning' => $warning,
                'conflicting_appointments' => $conflictingAppointments,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * ثبت یک تاریخ خاص به عنوان تعطیل کامل (مثلاً سه‌شنبه هفته بعد):
     * POST /api/admin/schedule/holidays
     * اگر برای آن تاریخ از قبل نوبت فعال وجود داشته باشد، هشدار می‌دهد و نوبت‌ها را خودکار لغو نمی‌کند.
     */
    public function storeHoliday(Request $request): JsonResponse
    {
        $rawDate = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'off_date', ''))
        );
        $reason = trim((string) self::extractInput($request, 'reason', ''));

        $errors = [];

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $matches) || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            $errors['off_date'] = 'لطفاً یک تاریخ معتبر برای تعطیلی انتخاب کنید.';
        } else {
            $alreadyExists = TimeOff::whereDate('off_date', $rawDate)
                ->where('type', 'full_day')
                ->exists();

            if ($alreadyExists) {
                $errors['off_date'] = 'این تاریخ قبلاً به عنوان روز تعطیل ثبت شده است.';
            }
        }

        if (mb_strlen($reason) > 255) {
            $errors['reason'] = 'توضیح تعطیلی نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.';
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

        $holiday = TimeOff::create([
            'off_date' => $rawDate,
            'type' => 'full_day',
            'start_time' => null,
            'end_time' => null,
            'reason' => $reason !== '' ? $reason : null,
        ]);

        // بررسی وجود نوبت‌های فعال از قبل ثبت‌شده در این تاریخ (بدون لغو خودکار)
        $conflictingAppointments = Appointment::with('service:id,name')
            ->whereDate('appointment_date', $rawDate)
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_time', 'asc')
            ->get()
            ->map(fn (Appointment $appt) => [
                'id' => $appt->id,
                'customer_name' => $appt->customer_name,
                'customer_phone' => $appt->customer_phone,
                'appointment_date' => $rawDate,
                'appointment_time' => $appt->appointment_time,
                'service_name' => $appt->service?->name,
            ])
            ->values()
            ->all();

        $warning = null;
        if (!empty($conflictingAppointments)) {
            $count = count($conflictingAppointments);
            $warning = "هشدار: در این تاریخ {$count} نوبت فعال از قبل ثبت شده است. این نوبت‌ها به صورت خودکار لغو نشدند.";
        }

        return response()->json(
            [
                'message' => 'تاریخ تعطیل با موفقیت ثبت شد.',
                'holiday' => $holiday,
                'warning' => $warning,
                'conflicting_appointments' => $conflictingAppointments,
            ],
            201,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * ثبت مرخصی چندساعته در یک روز مشخص (مثلاً امروز از ۱۴:۰۰ تا ۱۶:۰۰):
     * POST /api/admin/schedule/leaves
     * اگر در بازه مرخصی از قبل نوبت فعال وجود داشته باشد، هشدار می‌دهد و نوبت‌ها را خودکار لغو نمی‌کند.
     */
    public function storeLeave(Request $request): JsonResponse
    {
        $rawDate = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'off_date', ''))
        );
        $startTime = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'start_time', ''))
        );
        $endTime = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'end_time', ''))
        );
        $reason = trim((string) self::extractInput($request, 'reason', ''));

        $errors = [];

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $matches) || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            $errors['off_date'] = 'لطفاً یک تاریخ معتبر برای مرخصی انتخاب کنید.';
        }

        if (!self::isValidHalfHourTime($startTime)) {
            $errors['start_time'] = 'لطفاً ساعت شروع مرخصی را با فرمت معتبر (مثلاً ۱۴:۰۰) انتخاب کنید.';
        }

        if (!self::isValidHalfHourTime($endTime)) {
            $errors['end_time'] = 'لطفاً ساعت پایان مرخصی را با فرمت معتبر (مثلاً ۱۶:۰۰) انتخاب کنید.';
        }

        if (empty($errors['start_time']) && empty($errors['end_time'])) {
            if (self::timeToMinutes($startTime) >= self::timeToMinutes($endTime)) {
                $errors['end_time'] = 'ساعت پایان مرخصی باید بعد از ساعت شروع مرخصی باشد.';
            }
        }

        if (mb_strlen($reason) > 255) {
            $errors['reason'] = 'توضیح مرخصی نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.';
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

        $leave = TimeOff::create([
            'off_date' => $rawDate,
            'type' => 'hourly',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => $reason !== '' ? $reason : null,
        ]);

        // بررسی وجود نوبت‌های فعال از قبل ثبت‌شده در بازه مرخصی (بدون لغو خودکار - با احتساب مدت واقعی خدمات نوبت)
        $activeOnDate = Appointment::with(['service:id,name,duration', 'services:id,name,duration'])
            ->whereDate('appointment_date', $rawDate)
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_time', 'asc')
            ->get();

        $leaveStartMin = self::timeToMinutes($startTime);
        $leaveEndMin = self::timeToMinutes($endTime);

        $conflictingAppointments = [];
        foreach ($activeOnDate as $appt) {
            $apptStartMin = self::timeToMinutes($appt->appointment_time);
            $apptEndMin = $apptStartMin + max(1, $appt->total_duration);

            if (self::intervalsOverlap($apptStartMin, $apptEndMin, $leaveStartMin, $leaveEndMin)) {
                $conflictingAppointments[] = [
                    'id' => $appt->id,
                    'customer_name' => $appt->customer_name,
                    'customer_phone' => $appt->customer_phone,
                    'appointment_date' => $rawDate,
                    'appointment_time' => $appt->appointment_time,
                    'service_name' => $appt->services->isNotEmpty()
                        ? $appt->services->pluck('name')->implode('، ')
                        : $appt->service?->name,
                ];
            }
        }

        $warning = null;
        if (!empty($conflictingAppointments)) {
            $count = count($conflictingAppointments);
            $warning = "هشدار: در بازه ساعت {$startTime} تا {$endTime} این روز، {$count} نوبت فعال از قبل ثبت شده است. این نوبت‌ها به صورت خودکار لغو نشدند.";
        }

        return response()->json(
            [
                'message' => 'مرخصی ساعتی با موفقیت ثبت شد.',
                'leave' => $leave,
                'warning' => $warning,
                'conflicting_appointments' => $conflictingAppointments,
            ],
            201,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * حذف یک تاریخ تعطیل خاص یا مرخصی ساعتی توسط صاحب کار:
     * DELETE /api/admin/schedule/time-offs/{id}
     */
    public function destroyTimeOff($id): JsonResponse
    {
        $timeOff = TimeOff::find($id);

        if (!$timeOff) {
            return response()->json(
                [
                    'message' => 'مورد تعطیلی یا مرخصی یافت نشد.',
                ],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $type = $timeOff->type;
        $timeOff->delete();

        return response()->json(
            [
                'message' => $type === 'full_day'
                    ? 'تاریخ تعطیل با موفقیت حذف شد.'
                    : 'مرخصی ساعتی با موفقیت حذف شد.',
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
