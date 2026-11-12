<?php

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// لیست خدمات: http://localhost:8000/api/services
Route::get('/services', function () {
    return response()->json(
        Service::orderBy('id')->get(['id', 'name', 'price', 'duration']),
        200,
        [],
        JSON_UNESCAPED_UNICODE
    );
});

// ثبت نوبت جدید: http://localhost:8000/api/appointments
Route::post('/appointments', function (Request $request) {
    $validated = $request->validate([
        'customer_name' => ['required', 'string', 'max:255'],
        'customer_phone' => ['required', 'string', 'max:20'],
        'service_id' => ['required', 'exists:services,id'],
        'appointment_date' => ['required', 'date'],
        'appointment_time' => ['required', 'string'],
    ]);

    $appointment = Appointment::create($validated);
    $appointment->load('service:id,name,price,duration');

    return response()->json(
        [
            'message' => 'نوبت شما با موفقیت ثبت شد.',
            'appointment' => $appointment,
        ],
        201,
        [],
        JSON_UNESCAPED_UNICODE
    );
});
