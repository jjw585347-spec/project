<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * دریافت لیست خدمات فعال آرایشگاه برای مشتری (صفحه خدمات و فرم رزرو): GET /api/services
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Service::where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'name', 'price', 'duration']),
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * شرط شمارش فقط نوبت‌های فعال (نادیده گرفتن نوبت‌های لغوشده)
     */
    private static function activeAppointmentsCountConstraint(): array
    {
        return [
            'appointments' => function ($query) {
                $query->where('status', '!=', 'cancelled');
            },
        ];
    }

    /**
     * استخراج داده‌های ورودی از Request (حتی اگر هدر Content-Type ارسال نشده باشد)
     */
    private static function extractInput(Request $request, string $key, mixed $default = ''): mixed
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
     * دریافت لیست تمام خدمات (فعال و غیرفعال) به همراه تعداد نوبت‌های فعال برای پنل مدیریت: GET /api/admin/services
     */
    public function adminIndex(): JsonResponse
    {
        $services = Service::withCount(self::activeAppointmentsCountConstraint())
            ->orderBy('id')
            ->get();

        return response()->json(
            [
                'services' => $services,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * اعتبارسنجی ورودی‌های افزودن و ویرایش خدمت (نام، قیمت به تومان و مدت به دقیقه)
     */
    private function validateServiceInput(Request $request): array
    {
        $errors = [];

        $name = trim((string) self::extractInput($request, 'name', ''));
        $rawPrice = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'price', ''))
        );
        $rawDuration = CustomerController::normalizeDigits(
            trim((string) self::extractInput($request, 'duration', ''))
        );

        if ($name === '') {
            $errors['name'] = 'لطفاً نام خدمت را وارد کنید.';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'] = 'نام خدمت نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.';
        }

        if ($rawPrice === '') {
            $errors['price'] = 'لطفاً قیمت خدمت را به تومان وارد کنید.';
        } elseif (!preg_match('/^\d+$/', $rawPrice)) {
            $errors['price'] = 'قیمت خدمت باید یک عدد صحیح معتبر (به تومان) باشد.';
        }

        if ($rawDuration === '') {
            $errors['duration'] = 'لطفاً مدت زمان خدمت را به دقیقه وارد کنید.';
        } elseif (!preg_match('/^\d+$/', $rawDuration) || (int) $rawDuration < 1 || (int) $rawDuration > 600) {
            $errors['duration'] = 'مدت زمان خدمت باید یک عدد صحیح مثبت به دقیقه (بین ۱ تا ۶۰۰ دقیقه) باشد.';
        }

        return [
            $errors,
            [
                'name' => $name,
                'price' => $rawPrice !== '' && preg_match('/^\d+$/', $rawPrice) ? (int) $rawPrice : 0,
                'duration' => $rawDuration !== '' && preg_match('/^\d+$/', $rawDuration) ? (int) $rawDuration : 0,
            ],
        ];
    }

    /**
     * افزودن خدمت جدید توسط صاحب آرایشگاه: POST /api/admin/services
     */
    public function store(Request $request): JsonResponse
    {
        [$errors, $validated] = $this->validateServiceInput($request);

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

        $service = Service::create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'duration' => $validated['duration'],
            'is_active' => true,
        ]);

        $service->loadCount(self::activeAppointmentsCountConstraint());

        return response()->json(
            [
                'message' => 'خدمت جدید با موفقیت اضافه شد.',
                'service' => $service,
            ],
            201,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * ویرایش نام، قیمت و مدت زمان خدمت توسط صاحب آرایشگاه: PUT /api/admin/services/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json(
                [
                    'message' => 'خدمت مورد نظر یافت نشد.',
                ],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        [$errors, $validated] = $this->validateServiceInput($request);

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

        $service->name = $validated['name'];
        $service->price = $validated['price'];
        $service->duration = $validated['duration'];
        $service->save();
        $service->loadCount(self::activeAppointmentsCountConstraint());

        return response()->json(
            [
                'message' => 'اطلاعات خدمت با موفقیت ویرایش شد.',
                'service' => $service,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * غیرفعال کردن یا فعال کردن مجدد خدمت توسط صاحب آرایشگاه: PATCH /api/admin/services/{id}/status
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json(
                [
                    'message' => 'خدمت مورد نظر یافت نشد.',
                ],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $rawIsActive = self::extractInput($request, 'is_active', null);
        if ($rawIsActive !== null) {
            $service->is_active = filter_var($rawIsActive, FILTER_VALIDATE_BOOLEAN);
        } else {
            $service->is_active = !$service->is_active;
        }

        $service->save();
        $service->loadCount(self::activeAppointmentsCountConstraint());

        return response()->json(
            [
                'message' => $service->is_active
                    ? 'خدمت با موفقیت فعال شد.'
                    : 'خدمت با موفقیت غیرفعال شد.',
                'service' => $service,
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * حذف خدمت بدون نوبت توسط صاحب آرایشگاه (خدمت دارای نوبت حذف نمی‌شود و فقط باید غیرفعال شود):
     * DELETE /api/admin/services/{id}
     */
    public function destroy($id): JsonResponse
    {
        $service = Service::withCount(self::activeAppointmentsCountConstraint())->find($id);

        if (!$service) {
            return response()->json(
                [
                    'message' => 'خدمت مورد نظر یافت نشد.',
                ],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        if ($service->appointments_count > 0) {
            return response()->json(
                [
                    'message' => 'این خدمت دارای نوبت ثبت‌شده است و قابل حذف نیست؛ فقط می‌توانید آن را غیرفعال کنید.',
                ],
                409,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        // اگر فقط نوبت‌های لغوشده قدیمی برای این خدمت وجود داشت، قبل از حذف خدمت پاک می‌شوند
        $service->appointments()->where('status', 'cancelled')->delete();
        $service->delete();

        return response()->json(
            [
                'message' => 'خدمت با موفقیت حذف شد.',
            ],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
