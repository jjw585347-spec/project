<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// مدل ساعت کاری روزهای هفته (جدول working_hours)
class WorkingHour extends Model
{
    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];
}
