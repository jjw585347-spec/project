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

class AdminAuthApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // تنظیم اطلاعات اولیه مدیر از طریق config (مطابق خواندن از .env)
        config([
            'admin.phone' => '09120001122',
            'admin.password' => 'SecretAdmin987',
        ]);
    }

    /**
     * تست ۱: ورود درست صاحب آرایشگاه با شماره موبایل و رمز، هش شدن رمز در دیتابیس و دسترسی به پنل مدیریت
     */
    public function test_admin_login_succeeds_with_correct_credentials_and_hashes_password(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'phone' => '۰۹۱۲۰۰۰۱۱۲۲',
            'password' => 'SecretAdmin987',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'admin' => ['id', 'phone'],
            ])
            ->assertJsonPath('admin.phone', '09120001122');

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // بررسی اینکه رمز در دیتابیس به صورت متن ساده ذخیره نشده و هش شده است
        $admin = Admin::where('phone', '09120001122')->first();
        $this->assertNotNull($admin);
        $this->assertNotEquals('SecretAdmin987', $admin->password);
        $this->assertTrue(Hash::check('SecretAdmin987', $admin->password));

        // بررسی اینکه توکن صادرشده امکان دسترسی به درخواست‌های مدیریتی (لیست نوبت‌ها و لغو نوبت) را می‌دهد
        $customer = Customer::create([
            'name' => 'مشتری تست',
            'phone' => '09123334455',
            'password' => Hash::make('1234'),
        ]);
        $service = Service::create([
            'name' => 'اصلاح مو',
            'price' => 250000,
            'duration' => 30,
        ]);
        $futureDate = Carbon::now('Asia/Tehran')->addDay()->toDateString();
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '15:00',
            'status' => 'active',
        ]);

        $listResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/appointments?date={$futureDate}");

        $listResponse->assertStatus(200);

        $cancelResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->patchJson("/api/appointments/{$appointment->id}/cancel");

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('appointment.status', 'cancelled');
    }

    /**
     * تست ۲: رمز غلط هنگام ورود صاحب آرایشگاه رد می‌شود
     */
    public function test_admin_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'phone' => '09120001122',
            'password' => 'WrongPassword123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'شماره موبایل یا رمز عبور مدیر اشتباه است.')
            ->assertJsonMissingPath('token');
    }

    /**
     * تست ۳: درخواست‌های مدیریتی (مشاهده لیست نوبت‌ها و لغو نوبت) بدون ورود رد می‌شوند
     */
    public function test_admin_endpoints_reject_unauthenticated_requests_including_cancel(): void
    {
        $customer = Customer::create([
            'name' => 'مشتری تست',
            'phone' => '09123334455',
            'password' => Hash::make('1234'),
        ]);
        $service = Service::create([
            'name' => 'اصلاح مو',
            'price' => 250000,
            'duration' => 30,
        ]);
        $futureDate = Carbon::now('Asia/Tehran')->addDay()->toDateString();
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '16:00',
            'status' => 'active',
        ]);

        // الف) درخواست مشاهده لیست کامل نوبت‌ها بدون توکن ورود
        $unauthListResponse = $this->getJson("/api/appointments?date={$futureDate}");
        $unauthListResponse->assertStatus(401)
            ->assertJsonPath('message', 'برای دسترسی به این بخش، ابتدا باید وارد پنل مدیریت شوید.');

        // ب) درخواست لغو نوبت بدون توکن ورود
        $unauthCancelResponse = $this->patchJson("/api/appointments/{$appointment->id}/cancel");
        $unauthCancelResponse->assertStatus(401)
            ->assertJsonPath('message', 'برای دسترسی به این بخش، ابتدا باید وارد پنل مدیریت شوید.');

        // ج) درخواست لغو نوبت با توکن نامعتبر
        $invalidTokenCancelResponse = $this->withHeaders([
            'Authorization' => 'Bearer invalid_fake_token_12345',
        ])->patchJson("/api/appointments/{$appointment->id}/cancel");
        $invalidTokenCancelResponse->assertStatus(401);

        // اطمینان از اینکه نوبت لغو نشده و همچنان فعال است
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'active',
        ]);
    }

    /**
     * تست ۴: ورود صاحب آرایشگاه با نام کاربری متنی (غیر از شماره موبایل) تعریف‌شده در تنظیمات
     */
    public function test_admin_login_succeeds_with_username_identifier(): void
    {
        config([
            'admin.phone' => 'custom_admin_user',
            'admin.password' => 'TestPass123',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'phone' => 'custom_admin_user',
            'password' => 'TestPass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'token', 'admin' => ['id', 'phone']])
            ->assertJsonPath('admin.phone', 'custom_admin_user');
    }
}
