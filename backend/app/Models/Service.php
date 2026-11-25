<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// مدل خدمات آرایشگاه (جدول services)
class Service extends Model
{
    protected $fillable = [
        'name',
        'price',
        'duration',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'duration' => 'integer',
        'is_active' => 'boolean',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function multiServiceAppointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_service');
    }
}

