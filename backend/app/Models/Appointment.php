<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
