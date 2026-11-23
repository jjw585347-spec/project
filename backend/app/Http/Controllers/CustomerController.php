<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    /**
     * تبدیل اعداد فارسی و عربی به انگلیسی
     */
    public static function normalizeDigits(string $value): string
    {
        return strtr(
            trim($value),
            [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]
        );
    }

    /**
     * ایجاد حساب مشتری جدید: POST /api/customers/register
     * امکان ثبت‌نام دو نفر با نام و شماره موبایل یکسان به شرط متفاوت بودن رمز ۴ رقمی
     */
    public function register(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        $phone = self::normalizeDigits((string) $request->input('phone', ''));
        $password = self::normalizeDigits((string) $request->input('password', ''));

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'لطفاً نام و نام خانوادگی را وارد کنید.';
        }

        if ($phone === '') {
            $errors['phone'] = 'لطفاً شماره موبایل را وارد کنید.';
        } elseif (!preg_match('/^09\d{9}$/', $phone)) {
            $errors['phone'] = 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود (مثلاً 09123456789) وارد کنید.';
        }

        if ($password === '') {
            $errors['password'] = 'لطفاً رمز عبور ۴ رقمی را وارد کنید.';
        } elseif (!preg_match('/^\d{4}$/', $password)) {
            $errors['password'] = 'رمز عبور باید فقط و فقط شامل ۴ رقم عدد باشد (مثلاً 1234).';
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

        // بررسی اینکه آیا حسابی با همین نام و شماره موبایل و همین رمز ۴ رقمی از قبل وجود دارد یا خیر
        $existingAccounts = Customer::where('name', $name)
            ->where('phone', $phone)
            ->get();

        foreach ($existingAccounts as $account) {
            $samePassword =
                Hash::check($password, (string) $account->password) ||
                $account->password === $password;

            if ($samePassword) {
                $duplicateMessage = 'این حساب با همین نام، شماره موبایل و رمز عبور قبلاً ثبت شده است. لطفاً رمز عبور ۴ رقمی متفاوتی انتخاب کنید یا از بخش ورود وارد شوید.';
                return response()->json(
                    [
                        'message' => $duplicateMessage,
                        'errors' => [
                            'phone' => $duplicateMessage,
                        ],
                    ],
                    422,
                    [],
                    JSON_UNESCAPED_UNICODE
                );
            }
        }

        $customer = Customer::create([
            'name' => $name,
            'phone' => $phone,
            'password' => Hash::make($password),
        ]);

        return response()->json(
            [
                'message' => 'حساب شما با موفقیت ایجاد شد.',
                'customer' => $customer,
            ],
            201,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * ورود مشتری قبلی: POST /api/customers/login
     */
    public function login(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        $phone = self::normalizeDigits((string) $request->input('phone', ''));
        $password = self::normalizeDigits((string) $request->input('password', ''));

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'لطفاً نام و نام خانوادگی را وارد کنید.';
        }

        if ($phone === '') {
            $errors['phone'] = 'لطفاً شماره موبایل را وارد کنید.';
        } elseif (!preg_match('/^09\d{9}$/', $phone)) {
            $errors['phone'] = 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود (مثلاً 09123456789) وارد کنید.';
        }

        if ($password === '') {
            $errors['password'] = 'لطفاً رمز عبور ۴ رقمی را وارد کنید.';
        } elseif (!preg_match('/^\d{4}$/', $password)) {
            $errors['password'] = 'رمز عبور باید فقط و فقط شامل ۴ رقم عدد باشد.';
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

        $accountsByName = Customer::where('name', $name)->get();

        if ($accountsByName->isEmpty()) {
            $notFoundMessage = 'نام واردشده در سیستم وجود ندارد. اگر هنوز ثبت‌نام نکرده‌اید، روی دکمه «ایجاد حساب» در پایین کلیک کنید.';
            return response()->json(
                [
                    'message' => $notFoundMessage,
                    'errors' => [
                        'name' => $notFoundMessage,
                    ],
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $accountsByNameAndPhone = $accountsByName->where('phone', $phone);

        if ($accountsByNameAndPhone->isEmpty()) {
            $phoneMismatchMessage = 'شماره موبایل واردشده با این نام مطابقت ندارد.';
            return response()->json(
                [
                    'message' => $phoneMismatchMessage,
                    'errors' => [
                        'phone' => $phoneMismatchMessage,
                    ],
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $matchedCustomer = $accountsByNameAndPhone->first(function (Customer $account) use ($password) {
            return Hash::check($password, (string) $account->password) || $account->password === $password;
        });

        if (!$matchedCustomer) {
            $wrongPasswordMessage = 'رمز عبور ۴ رقمی واردشده اشتباه است.';
            return response()->json(
                [
                    'message' => $wrongPasswordMessage,
                    'errors' => [
                        'password' => $wrongPasswordMessage,
                    ],
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        return response()->json(
            [
                'message' => 'ورود با موفقیت انجام شد.',
                'customer' => $matchedCustomer,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * دریافت اطلاعات مشتری از دیتابیس: GET /api/customers/{id}
     */
    public function show($id): JsonResponse
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(
                [
                    'message' => 'حساب مشتری یافت نشد.',
                ],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        return response()->json(
            [
                'customer' => $customer,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
