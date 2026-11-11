<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// مدل خدمات آرایشگاه (جدول services)
class Service extends Model
{
    protected $fillable = [
        'name',
        'price',
        'duration',
    ];
}
