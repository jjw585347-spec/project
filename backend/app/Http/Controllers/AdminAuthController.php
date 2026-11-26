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
     * استخراج داده‌های ورودی از Request (حتی اگر هدر Content-Type ارسال نشده باشد)
     */
    private static function extractInput(Request $request, string $key, mixed $default = null): mixed
    {
        $val = $request->input($key);
        if ($val !== null) {
            return $val;
        }

        $rawContent = (string) $request->getContent();
        if ($rawContent !== '') {
            $decoded = json_decode($rawContent, true);
            if (is_array($decoded) && array_key_exists($key, $decoded)) {
                return $decoded[$key];
            }
        }

        return $default;
    }

    /**
     * ایجاد حساب اولیه مدیر بر اساس فایل تنظیمات (config/admin.php که از .env می‌خواند)
     * اگر حساب مدیر از قبل در دیتابیس وجود داشته باشد، اطلاعات ذخیره‌شده حفظ می‌شود.
     */
    public static function ensureConfiguredAdmin(): ?Admin
    {
        $existingAdmin = Admin::query()->orderBy('id', 'asc')->first();
        if ($existingAdmin) {
            // اگر رکورد قدیمی هنوز username نداشت، مقدار اولیه از تنظیمات برای آن پر شود
            if (empty($existingAdmin->username)) {
                $configuredUsername = trim(CustomerController::normalizeDigits((string) config('admin.username', '')));
                $existingAdmin->username = $configuredUsername !== '' ? $configuredUsername : $existingAdmin->phone;
                $existingAdmin->save();
            }
            return $existingAdmin;
        }

        $configuredUsername = trim(CustomerController::normalizeDigits((string) config('admin.username', '')));
        $configuredPhone = trim(CustomerController::normalizeDigits((string) config('admin.phone', '')));
        $configuredPassword = trim(CustomerController::normalizeDigits((string) config('admin.password', '')));

        if ($configuredPhone === '' && $configuredUsername === '') {
            return null;
        }
        if ($configuredPassword === '') {
            return null;
        }

        // اگر در .env قبلی، نام کاربری داخل ADMIN_PHONE گذاشته شده بود (غیر ۱۱ رقمی با ۰۹)
        if ($configuredPhone !== '' && !preg_match('/^09\d{9}$/', $configuredPhone)) {
            $resolvedUsername = $configuredPhone;
            $resolvedPhone = '09120000000';
        } else {
            $resolvedUsername = $configuredUsername !== '' ? $configuredUsername : $configuredPhone;
            $resolvedPhone = $configuredPhone !== '' ? $configuredPhone : '09120000000';
        }

        return Admin::create([
            'username' => $resolvedUsername,
            'phone' => $resolvedPhone,
            'password' => Hash::make($configuredPassword),
        ]);
    }

    /**
     * ورود صاحب آرایشگاه به پنل مدیریت با نام کاربری، شماره موبایل و رمز عبور:
     * POST /api/admin/login
     */
    public function login(Request $request): JsonResponse
    {
        $username = trim(CustomerController::normalizeDigits((string) self::extractInput($request, 'username', '')));
        $phone = trim(CustomerController::normalizeDigits((string) self::extractInput($request, 'phone', '')));
        $password = trim(CustomerController::normalizeDigits((string) self::extractInput($request, 'password', '')));

        $errors = [];

        if ($username === '') {
            $errors['username'] = 'لطفاً نام کاربری مدیر را وارد کنید.';
        }

        if ($phone === '') {
            $errors['phone'] = 'لطفاً شماره موبایل مدیر را وارد کنید.';
        } elseif (!preg_match('/^09\d{9}$/', $phone)) {
            $errors['phone'] = 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود (مثلاً 09123456789) وارد کنید.';
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

        $admin = Admin::where('username', $username)
            ->where('phone', $phone)
            ->first();

        if (!$admin || !Hash::check($password, (string) $admin->password)) {
            $invalidMsg = 'نام کاربری، شماره موبایل یا رمز عبور مدیر اشتباه است.';
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
                    'username' => $admin->username,
                    'phone' => $admin->phone,
                ],
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * تغییر نام کاربری، شماره موبایل و رمز ورود مدیر توسط صاحب آرایشگاه با پرسیدن رمز فعلی:
     * PUT /api/admin/settings/credentials
     */
    public function updateCredentials(Request $request): JsonResponse
    {
        /** @var Admin|null $admin */
        $admin = $request->attributes->get('authenticated_admin');

        if (!$admin) {
            return response()->json(
                [
                    'message' => 'برای دسترسی به این بخش، ابتدا باید وارد پنل مدیریت شوید.',
                ],
                401,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $rawUsername = self::extractInput($request, 'username', $admin->username);
        $newUsername = trim(CustomerController::normalizeDigits((string) $rawUsername));
        $rawPhone = self::extractInput($request, 'phone', '');
        $newPhone = trim(CustomerController::normalizeDigits((string) $rawPhone));
        $currentPassword = trim(CustomerController::normalizeDigits((string) self::extractInput($request, 'current_password', '')));
        $newPassword = trim(CustomerController::normalizeDigits((string) self::extractInput($request, 'new_password', '')));

        $errors = [];

        if ($newUsername === '') {
            $errors['username'] = 'لطفاً نام کاربری مدیر را وارد کنید.';
        } elseif (mb_strlen($newUsername) > 100) {
            $errors['username'] = 'نام کاربری نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.';
        } else {
            $duplicateUsername = Admin::where('username', $newUsername)
                ->where('id', '!=', $admin->id)
                ->exists();
            if ($duplicateUsername) {
                $errors['username'] = 'این نام کاربری قبلاً ثبت شده است.';
            }
        }

        if ($newPhone === '') {
            $errors['phone'] = 'لطفاً شماره موبایل مدیر را وارد کنید.';
        } elseif (!preg_match('/^09\d{9}$/', $newPhone)) {
            $errors['phone'] = 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود (مثلاً 09123456789) وارد کنید.';
        } else {
            $duplicatePhone = Admin::where('phone', $newPhone)
                ->where('id', '!=', $admin->id)
                ->exists();
            if ($duplicatePhone) {
                $errors['phone'] = 'این شماره موبایل قبلاً ثبت شده است.';
            }
        }

        if ($currentPassword === '') {
            $errors['current_password'] = 'لطفاً رمز عبور فعلی خود را وارد کنید.';
        } elseif (!Hash::check($currentPassword, (string) $admin->password)) {
            $errors['current_password'] = 'رمز عبور فعلی اشتباه است.';
        }

        $wantsPasswordChange = $newPassword !== '';
        if ($request->boolean('require_new_password') && !$wantsPasswordChange) {
            $errors['new_password'] = 'لطفاً رمز عبور جدید را وارد کنید.';
        } elseif ($wantsPasswordChange && mb_strlen($newPassword) < 4) {
            $errors['new_password'] = 'رمز عبور جدید باید حداقل ۴ کاراکتر باشد.';
        }

        if ($newUsername === (string) $admin->username && $newPhone === (string) $admin->phone && !$wantsPasswordChange && empty($errors)) {
            $errors['new_password'] = 'لطفاً رمز عبور جدید را وارد کنید یا نام کاربری / شماره موبایل را تغییر دهید.';
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

        $admin->username = $newUsername;
        $admin->phone = $newPhone;
        if ($wantsPasswordChange) {
            $admin->password = Hash::make($newPassword);
        }
        $admin->save();

        return response()->json(
            [
                'message' => $wantsPasswordChange
                    ? 'نام کاربری، شماره موبایل و رمز ورود مدیر با موفقیت تغییر کرد.'
                    : 'نام کاربری و شماره موبایل مدیر با موفقیت تغییر کرد.',
                'admin' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
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
                    'username' => $admin?->username,
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
