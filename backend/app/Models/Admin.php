<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// مدل مدیر (صاحب آرایشگاه - جدول admins)
class Admin extends Model
{
    protected $fillable = [
        'phone',
        'password',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'api_token',
    ];
}
