<?php

use App\Models\Service;
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
