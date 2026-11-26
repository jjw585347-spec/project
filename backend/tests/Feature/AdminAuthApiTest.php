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

        Admin::query()->delete();

        // تنظیم اطلاعات اولیه مدیر از طریق config (مطابق خواندن از .env)
        config([
            'admin.username' => 'aref',
            'admin.phone' => '09120001122',
            'admin.password' => 'SecretAdmin987',
        ]);
    }

    /**
     * تست ۱: ورود درست صاحب آرایشگاه با نام کاربری، شماره موبایل و رمز، هش شدن رمز در دیتابیس و دسترسی به پنل مدیریت
     */
    public function test_admin_login_succeeds_with_correct_credentials_and_hashes_password(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'username' => 'aref',
            'phone' => '۰۹۱۲۰۰۰۱۱۲۲',
            'password' => 'SecretAdmin987',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'admin' => ['id', 'username', 'phone'],
            ])
            ->assertJsonPath('admin.username', 'aref')
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
     * تست ۲: رمز غلط، نام کاربری غلط یا شماره موبایل غلط هنگام ورود صاحب آرایشگاه رد می‌شود
     */
    public function test_admin_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'username' => 'aref',
            'phone' => '09120001122',
            'password' => 'WrongPassword123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'نام کاربری، شماره موبایل یا رمز عبور مدیر اشتباه است.')
            ->assertJsonMissingPath('token');

        // ارسال بدون نام کاربری یا بدون شماره موبایل با 422 رد می‌شود
        $missingFieldsRes = $this->postJson('/api/admin/login', [
            'username' => '',
            'phone' => '',
            'password' => '',
        ]);
        $missingFieldsRes->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['username', 'phone', 'password']]);
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
     * تست ۴: ورود صاحب آرایشگاه فقط وقتی هر سه مورد (نام کاربری + شماره موبایل + رمز عبور) درست باشند موفق می‌شود
     */
    public function test_admin_login_succeeds_with_username_identifier(): void
    {
        config([
            'admin.username' => 'custom_admin_user',
            'admin.phone' => '09125556677',
            'admin.password' => 'TestPass123',
        ]);

        // الف) نام کاربری غلط با شماره و رمز درست -> 401
        $wrongUsernameRes = $this->postJson('/api/admin/login', [
            'username' => 'wrong_user',
            'phone' => '09125556677',
            'password' => 'TestPass123',
        ]);
        $wrongUsernameRes->assertStatus(401);

        // ب) هر سه درست -> 200
        $response = $this->postJson('/api/admin/login', [
            'username' => 'custom_admin_user',
            'phone' => '09125556677',
            'password' => 'TestPass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'token', 'admin' => ['id', 'username', 'phone']])
            ->assertJsonPath('admin.username', 'custom_admin_user')
            ->assertJsonPath('admin.phone', '09125556677');
    }

    /**
     * تست ۵: تغییر نام کاربری، شماره موبایل و رمز عبور مدیر از صفحه تنظیمات با پرسیدن رمز فعلی
     * - اگر رمز فعلی غلط یا خالی باشد رد می‌شود (422) و رمز/شماره عوض نمی‌شود
     * - با رمز فعلی صحیح، نام کاربری، شماره و رمز جدید هش‌شده ذخیره می‌شوند و ورود فقط با اطلاعات جدید کار می‌کند
     */
    public function test_admin_can_change_phone_and_password_only_with_correct_current_password(): void
    {
        $loginRes = $this->postJson('/api/admin/login', [
            'username' => 'aref',
            'phone' => '09120001122',
            'password' => 'SecretAdmin987',
        ]);
        $loginRes->assertStatus(200);
        $token = $loginRes->json('token');

        // الف) رمز فعلی اشتباه -> باید با 422 رد شود و رمز تغییر نکند
        $wrongCurrentRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/admin/settings/credentials', [
            'username' => 'aref_new',
            'phone' => '09129998877',
            'current_password' => 'WrongCurrentPass',
            'new_password' => 'NewSecret1234',
        ]);

        $wrongCurrentRes->assertStatus(422)
            ->assertJsonPath('errors.current_password', 'رمز عبور فعلی اشتباه است.');

        // ب) رمز فعلی خالی -> باید با 422 رد شود
        $emptyCurrentRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/admin/settings/credentials', [
            'username' => 'aref_new',
            'phone' => '09129998877',
            'current_password' => '',
            'new_password' => 'NewSecret1234',
        ]);

        $emptyCurrentRes->assertStatus(422)
            ->assertJsonPath('errors.current_password', 'لطفاً رمز عبور فعلی خود را وارد کنید.');

        // ج) رمز فعلی درست + نام کاربری جدید، شماره موبایل جدید و رمز جدید -> ذخیره موفق و هش شدن رمز جدید
        $validChangeRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/admin/settings/credentials', [
            'username' => 'aref_new',
            'phone' => '۰۹۱۲۹۹۹۸۸۷۷',
            'current_password' => 'SecretAdmin987',
            'new_password' => 'NewSecret1234',
        ]);

        $validChangeRes->assertStatus(200)
            ->assertJsonPath('admin.username', 'aref_new')
            ->assertJsonPath('admin.phone', '09129998877');

        $updatedAdmin = Admin::where('phone', '09129998877')->first();
        $this->assertNotNull($updatedAdmin);
        $this->assertSame('aref_new', $updatedAdmin->username);
        $this->assertTrue(Hash::check('NewSecret1234', $updatedAdmin->password));

        // د) تلاش برای ورود با اطلاعات قبلی رد می‌شود و ورود با نام کاربری، شماره و رمز جدید موفق است
        $oldLoginRes = $this->postJson('/api/admin/login', [
            'username' => 'aref',
            'phone' => '09120001122',
            'password' => 'SecretAdmin987',
        ]);
        $oldLoginRes->assertStatus(401);

        $newLoginRes = $this->postJson('/api/admin/login', [
            'username' => 'aref_new',
            'phone' => '09129998877',
            'password' => 'NewSecret1234',
        ]);
        $newLoginRes->assertStatus(200)
            ->assertJsonPath('admin.username', 'aref_new')
            ->assertJsonPath('admin.phone', '09129998877');
    }

    /**
     * تست ۶: تغییر نام آرایشگاه و مهلت لغو از صفحه تنظیمات و اعمال فوری مهلت لغو جدید روی لغو نوبت مشتری
     */
    public function test_admin_can_update_shop_name_and_cancel_deadline_and_it_applies_to_customer_cancellation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 10:00:00', 'Asia/Tehran'));

        try {
            $loginRes = $this->postJson('/api/admin/login', [
                'username' => 'aref',
                'phone' => '09120001122',
                'password' => 'SecretAdmin987',
            ]);
            $token = $loginRes->json('token');

            // ۱. تغییر نام آرایشگاه به «پیرایش مدرن» و مهلت لغو از ۲ ساعت به ۴ ساعت
            $updateSettingsRes = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->putJson('/api/admin/settings/general', [
                'shop_name' => 'پیرایش مدرن',
                'cancel_deadline_hours' => '۴',
            ]);

            $updateSettingsRes->assertStatus(200)
                ->assertJsonPath('shop_name', 'پیرایش مدرن')
                ->assertJsonPath('cancel_deadline_hours', 4);

            // ۲. بررسی GET /api/settings (عمومی) و GET /api/admin/settings (مدیریتی)
            $publicSettings = $this->getJson('/api/settings');
            $publicSettings->assertStatus(200)
                ->assertJsonPath('shop_name', 'پیرایش مدرن')
                ->assertJsonPath('cancel_deadline_hours', 4);

            $adminSettings = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/admin/settings');
            $adminSettings->assertStatus(200)
                ->assertJsonPath('shop_name', 'پیرایش مدرن')
                ->assertJsonPath('cancel_deadline_hours', 4)
                ->assertJsonStructure(['shop_name', 'cancel_deadline_hours', 'admin', 'weekly_hours', 'holidays', 'leaves', 'services']);

            // ۳. ثبت یک نوبت برای ساعت 13:00 امروز (۳ ساعت دیگر؛ با مهلت ۲ ساعت مجاز بود ولی با مهلت ۴ ساعت جدید باید غیرمجاز باشد)
            for ($d = 0; $d <= 6; $d++) {
                \App\Models\WorkingHour::updateOrCreate(
                    ['day_of_week' => $d],
                    ['start_time' => '10:00', 'end_time' => '20:00', 'is_closed' => false]
                );
            }
            $customer = Customer::create([
                'name' => 'مشتری تست تنظیمات',
                'phone' => '09121112233',
                'password' => Hash::make('1234'),
            ]);
            $service = Service::create([
                'name' => 'اصلاح مو',
                'price' => 250000,
                'duration' => 30,
                'is_active' => true,
            ]);
            Appointment::whereDate('appointment_date', '2026-11-26')->delete();

            $bookRes = $this->postJson('/api/appointments', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'service_ids' => [$service->id],
                'appointment_date' => '2026-11-26',
                'appointment_time' => '13:00',
            ]);
            $bookRes->assertStatus(201);
            $trackingCode = $bookRes->json('appointment.tracking_code');

            // چون ۳ ساعت تا نوبت مانده و مهلت لغو در تنظیمات ۴ ساعت شده، لغو توسط مشتری رد می‌شود (422)
            $cancelRes = $this->postJson('/api/appointments/track/cancel', [
                'customer_phone' => $customer->phone,
                'tracking_code' => $trackingCode,
            ]);
            $cancelRes->assertStatus(422)
                ->assertJsonPath(
                    'message',
                    'امکان لغو نوبت فقط تا 4 ساعت قبل از زمان نوبت وجود دارد و مهلت لغو این نوبت به پایان رسیده است.'
                );
        } finally {
            Carbon::setTestNow();
        }
    }
}
