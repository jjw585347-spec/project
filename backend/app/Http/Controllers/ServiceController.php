<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    /**
     * دریافت لیست خدمات آرایشگاه: GET /api/services
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Service::orderBy('id')->get(['id', 'name', 'price', 'duration']),
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
