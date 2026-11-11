<?php

use Illuminate\Support\Facades\Route;

// آدرس تست: http://localhost:8000/api/test
Route::get('/test', function () {
    return response()->json([
        'message' => 'سلام من بک اند هستم و به درستی کار میکنم',
    ], 200, [], JSON_UNESCAPED_UNICODE);
});
