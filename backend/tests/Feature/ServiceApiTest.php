<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use DatabaseTransactions;

    private function getAdminAuthHeaders(): array
    {
        $plainToken = 'test_admin_token_for_service_tests';
        Admin::updateOrCreate(
            ['phone' => '09120000000'],
            [
                'password' => Hash::make('1234'),
                'api_token' => hash('sha256', $plainToken),
            ]
        );

        return [
            'Authorization' => "Bearer {$plainToken}",
        ];
    }

    /**
     * پاک‌سازی موقت نوبت‌ها و خدمات در داخل تراکنش تست (در پایان هر تست Rollback می‌شود)
     */
    private function clearServicesForTest(): void
    {
        Appointment::query()->delete();
        Service::query()->delete();
    }

    /**
     * حالت درست: GET /api/services لیست خدمات را به ترتیب شناسه برمی‌گرداند
     */
    public function test_get_services_returns_ordered_services_list(): void
    {
        $this->clearServicesForTest();

        $first = Service::create([
            'name' => 'اصلاح مو',
            'price' => 250000,
            'duration' => 30,
        ]);

        $second = Service::create([
            'name' => 'اصلاح مو و ریش',
            'price' => 350000,
            'duration' => 60,
        ]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $first->id)
            ->assertJsonPath('0.name', 'اصلاح مو')
            ->assertJsonPath('0.price', 250000)
            ->assertJsonPath('0.duration', 30)
            ->assertJsonPath('1.id', $second->id)
            ->assertJsonPath('1.name', 'اصلاح مو و ریش');
    }

    /**
     * حالت خطا / مرزی: ارسال متد نامعتبر POST به /api/services با خطای 405 رد می‌شود
     * و در صورت خالی بودن جدول خدمات، آرایه خالی با کد 200 برمی‌گردد
     */
    public function test_services_endpoint_rejects_invalid_method_and_handles_empty_state(): void
    {
        $this->clearServicesForTest();

        $emptyResponse = $this->getJson('/api/services');
        $emptyResponse->assertStatus(200)->assertExactJson([]);

        $methodNotAllowedResponse = $this->postJson('/api/services', [
            'name' => 'خدمت تستی',
        ]);
        $methodNotAllowedResponse->assertStatus(405);
    }

    /**
     * خدمت غیرفعال در لیست عمومی خدمات (صفحه اصلی خدمات و فرم رزرو) دیده نمی‌شود
     * اما در لیست مدیریتی صاحب کار همراه با وضعیت و تعداد نوبت‌ها دیده می‌شود
     */
    public function test_inactive_services_are_hidden_from_public_list_but_visible_to_admin(): void
    {
        $this->clearServicesForTest();

        $activeService = Service::create([
            'name' => 'اصلاح مو',
            'price' => 250000,
            'duration' => 20,
            'is_active' => true,
        ]);

        $inactiveService = Service::create([
            'name' => 'اصلاح مو و ریش',
            'price' => 350000,
            'duration' => 30,
            'is_active' => false,
        ]);

        $publicResponse = $this->getJson('/api/services');
        $publicResponse->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $activeService->id)
            ->assertJsonPath('0.name', 'اصلاح مو')
            ->assertJsonPath('0.duration', 20);

        $adminResponse = $this->withHeaders($this->getAdminAuthHeaders())
            ->getJson('/api/admin/services');

        $adminResponse->assertStatus(200)
            ->assertJsonCount(2, 'services')
            ->assertJsonPath('services.0.id', $activeService->id)
            ->assertJsonPath('services.0.is_active', true)
            ->assertJsonPath('services.1.id', $inactiveService->id)
            ->assertJsonPath('services.1.is_active', false);
    }

    /**
     * صاحب کار می‌تواند خدمت جدید اضافه کند و نام، قیمت و مدت به دقیقه (مثلاً ۲۰ یا ۳۰ دقیقه) را ویرایش کند
     */
    public function test_admin_can_add_and_edit_service_with_minutes_duration(): void
    {
        $this->clearServicesForTest();

        // الف) افزودن خدمت با ارقام فارسی (مثلاً اصلاح مو ۲۰ دقیقه)
        $createResponse = $this->withHeaders($this->getAdminAuthHeaders())
            ->postJson('/api/admin/services', [
                'name' => 'اصلاح مو',
                'price' => '۲۵۰۰۰۰',
                'duration' => '۲۰',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('message', 'خدمت جدید با موفقیت اضافه شد.')
            ->assertJsonPath('service.name', 'اصلاح مو')
            ->assertJsonPath('service.price', 250000)
            ->assertJsonPath('service.duration', 20)
            ->assertJsonPath('service.is_active', true);

        $serviceId = $createResponse->json('service.id');

        // ب) ویرایش خدمت (مثلاً اصلاح مو و ریش ۳۰ دقیقه)
        $updateResponse = $this->withHeaders($this->getAdminAuthHeaders())
            ->putJson("/api/admin/services/{$serviceId}", [
                'name' => 'اصلاح مو و ریش',
                'price' => 350000,
                'duration' => 30,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('message', 'اطلاعات خدمت با موفقیت ویرایش شد.')
            ->assertJsonPath('service.name', 'اصلاح مو و ریش')
            ->assertJsonPath('service.price', 350000)
            ->assertJsonPath('service.duration', 30);
    }

    /**
     * اعتبارسنجی فیلدهای نام، قیمت و مدت زمان هنگام افزودن یا ویرایش خدمت با کد 422
     */
    public function test_admin_service_validation_rejects_invalid_inputs_with_422(): void
    {
        $response = $this->withHeaders($this->getAdminAuthHeaders())
            ->postJson('/api/admin/services', [
                'name' => '   ',
                'price' => '-5000',
                'duration' => '0',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['name', 'price', 'duration'],
            ]);
    }

    /**
     * خدمتی که نوبت دارد پاک نمی‌شود و فقط غیرفعال می‌شود؛ اما خدمت بدون نوبت قابل حذف است
     */
    public function test_service_with_appointments_cannot_be_deleted_and_can_only_be_deactivated(): void
    {
        $this->clearServicesForTest();

        $customer = Customer::create([
            'name' => 'مشتری تست',
            'phone' => '09121112233',
            'password' => Hash::make('1234'),
        ]);

        $serviceWithAppointment = Service::create([
            'name' => 'اصلاح مو',
            'price' => 250000,
            'duration' => 20,
            'is_active' => true,
        ]);

        $serviceWithoutAppointment = Service::create([
            'name' => 'پاکسازی پوست',
            'price' => 400000,
            'duration' => 45,
            'is_active' => true,
        ]);

        Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $serviceWithAppointment->id,
            'appointment_date' => Carbon::now('Asia/Tehran')->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'status' => 'active',
        ]);

        // ۱. تلاش برای حذف خدمتی که نوبت دارد -> باید با کد 409 رد شود و پاک نشود
        $deleteBookedServiceResponse = $this->withHeaders($this->getAdminAuthHeaders())
            ->deleteJson("/api/admin/services/{$serviceWithAppointment->id}");

        $deleteBookedServiceResponse->assertStatus(409)
            ->assertJsonPath(
                'message',
                'این خدمت دارای نوبت ثبت‌شده است و قابل حذف نیست؛ فقط می‌توانید آن را غیرفعال کنید.'
            );

        $this->assertDatabaseHas('services', [
            'id' => $serviceWithAppointment->id,
            'is_active' => true,
        ]);

        // ۲. غیرفعال کردن خدمتی که نوبت دارد -> موفق می‌شود و از لیست مشتری مخفی می‌گردد
        $deactivateResponse = $this->withHeaders($this->getAdminAuthHeaders())
            ->patchJson("/api/admin/services/{$serviceWithAppointment->id}/status", [
                'is_active' => false,
            ]);

        $deactivateResponse->assertStatus(200)
            ->assertJsonPath('message', 'خدمت با موفقیت غیرفعال شد.')
            ->assertJsonPath('service.is_active', false);

        // بررسی عدم نمایش خدمت غیرفعال در GET /api/services
        $publicListAfterDeactivate = $this->getJson('/api/services');
        $publicListAfterDeactivate->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $serviceWithoutAppointment->id);

        // ۳. حذف خدمتی که هیچ نوبت فعالی ندارد (حتی اگر نوبت لغوشده داشته باشد) -> موفق می‌شود
        Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $serviceWithoutAppointment->id,
            'appointment_date' => Carbon::now('Asia/Tehran')->addDay()->toDateString(),
            'appointment_time' => '11:30',
            'status' => 'cancelled',
        ]);

        $adminListWithCancelled = $this->withHeaders($this->getAdminAuthHeaders())
            ->getJson('/api/admin/services');
        $freeServiceInList = collect($adminListWithCancelled->json('services'))
            ->firstWhere('id', $serviceWithoutAppointment->id);
        $this->assertSame(0, $freeServiceInList['appointments_count']);

        $deleteFreeServiceResponse = $this->withHeaders($this->getAdminAuthHeaders())
            ->deleteJson("/api/admin/services/{$serviceWithoutAppointment->id}");

        $deleteFreeServiceResponse->assertStatus(200)
            ->assertJsonPath('message', 'خدمت با موفقیت حذف شد.');

        $this->assertDatabaseMissing('services', [
            'id' => $serviceWithoutAppointment->id,
        ]);
    }

    /**
     * مسیرهای مدیریت خدمات بدون توکن مدیر با خطای 401 رد می‌شوند
     */
    public function test_admin_service_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/services')->assertStatus(401);
        $this->postJson('/api/admin/services', [
            'name' => 'اصلاح مو',
            'price' => 200000,
            'duration' => 20,
        ])->assertStatus(401);
    }
}
