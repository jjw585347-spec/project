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

        if ($name === '' || $phone === '' || $password === '') {
            return response()->json(
                [
                    'message' => 'لطفاً نام، شماره موبایل و رمز عبور ۴ رقمی خود را کامل وارد کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (mb_strlen($name) > 40) {
            return response()->json(
                [
                    'message' => 'نام واردشده خیلی طولانی است. لطفاً نام را حداکثر تا ۴۰ کاراکتر وارد کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (!preg_match('/^09\d{9}$/', $phone)) {
            return response()->json(
                [
                    'message' => 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با 09 شروع می‌شود (مثلاً 09123456789) وارد کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (!preg_match('/^\d{4}$/', $password)) {
            return response()->json(
                [
                    'message' => 'رمز عبور باید فقط و فقط شامل ۴ رقم عدد باشد (مثلاً 1234).',
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
                return response()->json(
                    [
                        'message' => 'این حساب با همین نام، شماره موبایل و رمز عبور قبلاً ثبت شده است. لطفاً رمز عبور ۴ رقمی متفاوتی انتخاب کنید یا از بخش ورود وارد شوید.',
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

        if ($name === '' || $phone === '' || $password === '') {
            return response()->json(
                [
                    'message' => 'لطفاً نام، شماره موبایل و رمز عبور ۴ رقمی خود را وارد کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (mb_strlen($name) > 40) {
            return response()->json(
                [
                    'message' => 'نام واردشده خیلی طولانی است. لطفاً نام را حداکثر تا ۴۰ کاراکتر وارد کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (!preg_match('/^09\d{9}$/', $phone)) {
            return response()->json(
                [
                    'message' => 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با 09 شروع می‌شود (مثلاً 09123456789) وارد کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (!preg_match('/^\d{4}$/', $password)) {
            return response()->json(
                [
                    'message' => 'رمز عبور باید فقط و فقط شامل ۴ رقم عدد باشد.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $accountsByName = Customer::where('name', $name)->get();

        if ($accountsByName->isEmpty()) {
            return response()->json(
                [
                    'message' => 'نام واردشده در سیستم وجود ندارد. اگر هنوز ثبت‌نام نکرده‌اید، روی دکمه «ایجاد حساب» در پایین کلیک کنید.',
                ],
                422,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $accountsByNameAndPhone = $accountsByName->where('phone', $phone);

        if ($accountsByNameAndPhone->isEmpty()) {
            return response()->json(
                [
                    'message' => 'شماره موبایل واردشده با این نام مطابقت ندارد.',
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
            return response()->json(
                [
                    'message' => 'رمز عبور ۴ رقمی واردشده اشتباه است.',
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
