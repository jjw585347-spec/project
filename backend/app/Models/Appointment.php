<?php

namespace App\Models;

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
    ];

    protected $appends = [
        'total_duration',
        'total_price',
    ];

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

