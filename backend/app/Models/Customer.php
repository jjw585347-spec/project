<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// مدل مشتری‌ها (جدول customers)
class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
    ];
}
