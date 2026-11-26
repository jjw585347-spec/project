<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// مدل نوبت‌ها (جدول appointments)
class Appointment extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_phone',
        'service_id',
        'appointment_date',
        'appointment_time',
        'status',
        'tracking_code',
    ];

    protected $appends = [
        'total_duration',
        'total_price',
        'cancel_deadline_hours',
        'can_customer_cancel',
        'cancel_block_reason',
    ];

    /**
     * تولید کد پیگیری ۶ رقمی عددی یکتا برای نوبت جدید (R9)
     */
    public static function generateUniqueTrackingCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (self::where('tracking_code', $code)->exists());

        return $code;
    }

    /**
     * مهلت لغو نوبت توسط مشتری بر حسب ساعت قبل از زمان نوبت (قابل تغییر از صفحه تنظیمات پنل مدیریت - قانون R9)
     */
    public static function getCancelDeadlineHours(): int
    {
        try {
            $setting = ShopSetting::current();
            if ($setting && (int) $setting->cancel_deadline_hours > 0) {
                return (int) $setting->cancel_deadline_hours;
            }
        } catch (\Throwable) {
            // در صورت عدم وجود جدول در لحظه اجرای مایگریشن اولیه، از فایل تنظیمات خوانده می‌شود
        }

        $configured = (int) config('app.cancel_deadline_hours');

        return $configured > 0 ? $configured : 1;
    }

    public function getCancelDeadlineHoursAttribute(): int
    {
        return self::getCancelDeadlineHours();
    }

    /**
     * بررسی وضعیت امکان لغو نوبت توسط خود مشتری و دلیل غیرفعال بودن آن (R9)
     *
     * @return array{can_cancel: bool, reason: ?string}
     */
    public function evaluateCustomerCancellation(?Carbon $now = null): array
    {
        $deadlineHours = self::getCancelDeadlineHours();

        if ($this->status === 'cancelled') {
            return [
                'can_cancel' => false,
                'reason' => 'این نوبت قبلاً لغو شده است.',
            ];
        }

        if (!$this->appointment_date || !$this->appointment_time) {
            return [
                'can_cancel' => false,
                'reason' => 'اطلاعات زمان نوبت معتبر نیست.',
            ];
        }

        $now = $now ?: Carbon::now('Asia/Tehran');
        $dateStr = substr((string) $this->appointment_date, 0, 10);
        $timeStr = substr((string) $this->appointment_time, 0, 5);

        try {
            $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$timeStr}", 'Asia/Tehran');
        } catch (\Throwable) {
            return [
                'can_cancel' => false,
                'reason' => 'اطلاعات زمان نوبت معتبر نیست.',
            ];
        }

        if ($now->greaterThanOrEqualTo($appointmentDateTime)) {
            return [
                'can_cancel' => false,
                'reason' => "زمان این نوبت گذشته است و امکان لغو آن وجود ندارد (مهلت لغو تا {$deadlineHours} ساعت قبل از شروع نوبت است).",
            ];
        }

        $latestCancelTime = $appointmentDateTime->copy()->subHours($deadlineHours);
        if ($now->greaterThan($latestCancelTime)) {
            return [
                'can_cancel' => false,
                'reason' => "امکان لغو نوبت فقط تا {$deadlineHours} ساعت قبل از زمان نوبت وجود دارد و مهلت لغو این نوبت به پایان رسیده است.",
            ];
        }

        return [
            'can_cancel' => true,
            'reason' => null,
        ];
    }

    public function getCanCustomerCancelAttribute(): bool
    {
        return $this->evaluateCustomerCancellation()['can_cancel'];
    }

    public function getCancelBlockReasonAttribute(): ?string
    {
        return $this->evaluateCustomerCancellation()['reason'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * لیست خدمات انتخاب‌شده برای این نوبت (یک یا چند خدمت طبق R8)
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'appointment_service')
            ->withTimestamps()
            ->orderBy('appointment_service.id');
    }

    /**
     * محاسبه مجموع مدت زمان (به دقیقه) تمام خدمات انتخاب‌شده در این نوبت (R5 و R8)
     */
    public function getTotalDurationAttribute(): int
    {
        if ($this->relationLoaded('services') && $this->services->isNotEmpty()) {
            return (int) $this->services->sum('duration');
        }

        if ($this->relationLoaded('service') && $this->service) {
            return (int) $this->service->duration;
        }

        $servicesDuration = (int) $this->services()->sum('services.duration');
        if ($servicesDuration > 0) {
            return $servicesDuration;
        }

        return (int) ($this->service()->value('duration') ?? 30);
    }

    /**
     * محاسبه مجموع قیمت (به تومان) تمام خدمات انتخاب‌شده در این نوبت
     */
    public function getTotalPriceAttribute(): int
    {
        if ($this->relationLoaded('services') && $this->services->isNotEmpty()) {
            return (int) $this->services->sum('price');
        }

        if ($this->relationLoaded('service') && $this->service) {
            return (int) $this->service->price;
        }

        $servicesPrice = (int) $this->services()->sum('services.price');
        if ($servicesPrice > 0) {
            return $servicesPrice;
        }

        return (int) ($this->service()->value('price') ?? 0);
    }
}

