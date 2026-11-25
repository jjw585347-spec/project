<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    /**
     * همگام‌سازی و دریافت حساب مدیر بر اساس اطلاعات اولیه‌ی فایل تنظیمات (config/admin.php که از .env می‌خواند)
     */
    public static function ensureConfiguredAdmin(): ?Admin
    {
        $configuredPhone = CustomerController::normalizeDigits((string) config('admin.phone', ''));
        $configuredPassword = CustomerController::normalizeDigits((string) config('admin.password', ''));

        if ($configuredPhone === '' || $configuredPassword === '') {
            return null;
        }

        $admin = Admin::where('phone', $configuredPhone)->first();

        if (!$admin) {
            return Admin::create([
                'phone' => $configuredPhone,
                'password' => Hash::make($configuredPassword),
            ]);
        }

        // اگر رمز در تنظیمات تغییر کرده باشد، هش رمز در دیتابیس به‌روز می‌شود
        if (!Hash::check($configuredPassword, (string) $admin->password)) {
            $admin->password = Hash::make($configuredPassword);
            $admin->save();
        }

        return $admin;
    }

    /**
     * ورود صاحب آرایشگاه به پنل مدیریت: POST /api/admin/login
     */
    public function login(Request $request): JsonResponse
    {
        $rawIdentifier = $request->input('phone', $request->input('username', ''));
        $phone = trim(CustomerController::normalizeDigits((string) $rawIdentifier));
        $password = trim(CustomerController::normalizeDigits((string) $request->input('password', '')));

        $errors = [];

        if ($phone === '') {
            $errors['phone'] = 'لطفاً نام کاربری مدیر را وارد کنید.';
        }

        if ($password === '') {
            $errors['password'] = 'لطفاً رمز عبور را وارد کنید.';
        }

        if (!empty($errors)) {
            return response()->json(
                [
                    'message' => reset($errors),
                    'errors' => $errors,
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        self::ensureConfiguredAdmin();

        $admin = Admin::where('phone', $phone)->first();

        if (!$admin || !Hash::check($password, (string) $admin->password)) {
            $invalidMsg = 'شماره موبایل یا رمز عبور مدیر اشتباه است.';
            return response()->json(
                [
                    'message' => $invalidMsg,
                    'errors' => [
                        'password' => $invalidMsg,
                    ],
                ],
                401,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $plainToken = Str::random(48);
        $admin->api_token = hash('sha256', $plainToken);
        $admin->save();

        return response()->json(
            [
                'message' => 'ورود به پنل مدیریت با موفقیت انجام شد.',
                'token' => $plainToken,
                'admin' => [
                    'id' => $admin->id,
                    'phone' => $admin->phone,
                ],
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * بررسی وضعیت ورود مدیر: GET /api/admin/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Admin|null $admin */
        $admin = $request->attributes->get('authenticated_admin');

        return response()->json(
            [
                'admin' => [
                    'id' => $admin?->id,
                    'phone' => $admin?->phone,
                ],
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * خروج از پنل مدیریت: POST /api/admin/logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var Admin|null $admin */
        $admin = $request->attributes->get('authenticated_admin');

        if ($admin) {
            $admin->api_token = null;
            $admin->save();
        }

        return response()->json(
            [
                'message' => 'خروج از پنل مدیریت انجام شد.',
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
