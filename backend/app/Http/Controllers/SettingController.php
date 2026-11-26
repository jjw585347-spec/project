<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
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
     * دریافت تنظیمات عمومی آرایشگاه برای نمایش در هدر سایت، صفحه خدمات، رزرو و پیگیری نوبت:
     * GET /api/settings
     */
    public function publicIndex(): JsonResponse
    {
        $setting = ShopSetting::current();
        WorkingScheduleController::ensureDefaultWeeklyHours();

        $weeklyHours = WorkingHour::orderBy('day_of_week', 'asc')
            ->get()
            ->map(fn (WorkingHour $wh) => [
                'day_of_week' => $wh->day_of_week,
                'day_name' => WorkingScheduleController::DAY_NAMES[$wh->day_of_week] ?? '',
                'start_time' => $wh->start_time,
                'end_time' => $wh->end_time,
                'is_closed' => (bool) $wh->is_closed,
            ])
            ->values();

        return response()->json(
            [
                'shop_name' => $setting->shop_name,
                'cancel_deadline_hours' => (int) $setting->cancel_deadline_hours,
                'weekly_hours' => $weeklyHours,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * دریافت یکپارچه تمام تنظیمات برای صفحه «تنظیمات کامل» پنل مدیریت:
     * GET /api/admin/settings
     * شامل: نام آرایشگاه، شماره موبایل مدیر، مهلت لغو، ساعت کاری هفته، تعطیلی‌ها و مرخصی‌ها، و لیست خدمات
     */
    public function adminIndex(Request $request): JsonResponse
    {
        /** @var Admin|null $admin */
        $admin = $request->attributes->get('authenticated_admin');
        $setting = ShopSetting::current();
        WorkingScheduleController::ensureDefaultWeeklyHours();

        $weeklyHours = WorkingHour::orderBy('day_of_week', 'asc')
            ->get()
            ->map(fn (WorkingHour $wh) => [
                'id' => $wh->id,
                'day_of_week' => $wh->day_of_week,
                'day_name' => WorkingScheduleController::DAY_NAMES[$wh->day_of_week] ?? '',
                'start_time' => $wh->start_time,
                'end_time' => $wh->end_time,
                'is_closed' => (bool) $wh->is_closed,
            ])
            ->values();

        $holidays = TimeOff::where('type', 'full_day')
            ->orderBy('off_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $leaves = TimeOff::where('type', 'hourly')
            ->orderBy('off_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $services = Service::withCount([
            'appointments' => function ($query) {
                $query->where('status', '!=', 'cancelled')
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('appointment_service')
                            ->join('appointments as a2', 'a2.id', '=', 'appointment_service.appointment_id')
                            ->whereColumn('appointment_service.service_id', 'services.id')
                            ->where('a2.status', '!=', 'cancelled');
                    });
            },
        ])
            ->orderBy('id')
            ->get();

        return response()->json(
            [
                'shop_name' => $setting->shop_name,
                'cancel_deadline_hours' => (int) $setting->cancel_deadline_hours,
                'admin' => [
                    'id' => $admin?->id,
                    'username' => $admin?->username,
                    'phone' => $admin?->phone,
                ],
                'weekly_hours' => $weeklyHours,
                'holidays' => $holidays,
                'leaves' => $leaves,
                'services' => $services,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * ویرایش تنظیمات عمومی آرایشگاه (نام آرایشگاه و مهلت لغو نوبت توسط مشتری):
     * PUT /api/admin/settings/general
     */
    public function updateGeneral(Request $request): JsonResponse
    {
        $errors = [];

        $shopName = trim((string) self::extractInput($request, 'shop_name', ''));
        $rawDeadline = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'cancel_deadline_hours', ''))
        );

        if ($shopName === '') {
            $errors['shop_name'] = 'لطفاً نام آرایشگاه را وارد کنید.';
        } elseif (mb_strlen($shopName) > 255) {
            $errors['shop_name'] = 'نام آرایشگاه نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.';
        }

        if ($rawDeadline === '') {
            $errors['cancel_deadline_hours'] = 'لطفاً مهلت لغو نوبت (به ساعت) را وارد کنید.';
        } elseif (!preg_match('/^\d+$/', $rawDeadline) || (int) $rawDeadline < 1 || (int) $rawDeadline > 720) {
            $errors['cancel_deadline_hours'] = 'مهلت لغو نوبت باید یک عدد صحیح مثبت (بر حسب ساعت قبل از زمان نوبت) باشد.';
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

        $setting = ShopSetting::current();
        $setting->shop_name = $shopName;
        $setting->cancel_deadline_hours = (int) $rawDeadline;
        $setting->save();

        return response()->json(
            [
                'message' => 'تنظیمات نام آرایشگاه و مهلت لغو با موفقیت ذخیره شد.',
                'shop_name' => $setting->shop_name,
                'cancel_deadline_hours' => (int) $setting->cancel_deadline_hours,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
