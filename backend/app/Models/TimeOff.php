<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// مدل تاریخ‌های تعطیل خاص و مرخصی‌های ساعتی (جدول time_offs)
class TimeOff extends Model
{
    protected $fillable = [
        'off_date',
        'type',
        'start_time',
        'end_time',
        'reason',
    ];
}
