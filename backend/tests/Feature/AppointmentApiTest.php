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

class AppointmentApiTest extends TestCase
{
    use DatabaseTransactions;

    private function getAdminAuthHeaders(): array
    {
        $plainToken = 'test_admin_token_for_appointment_tests';
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

    private function createTestCustomerAndService(): array
    {
        $customer = Customer::create([
            'name' => 'امیر محمدی',
            'phone' => '09121234567',
            'password' => Hash::make('1234'),
        ]);

        $service = Service::create([
            'name' => 'اصلاح مو',
            'price' => 250000,
            'duration' => 30,
        ]);

        return [$customer, $service];
    }

    /**
     * حالت درست: GET /api/appointments?date=YYYY-MM-DD دریافت لیست نوبت‌های یک تاریخ به ترتیب ساعت
     */
    public function test_get_appointments_returns_sorted_appointments_for_date(): void
    {
        [$customer, $service] = $this->createTestCustomerAndService();
        $date = Carbon::now('Asia/Tehran')->addDays(2)->toDateString();
        Appointment::whereDate('appointment_date', $date)->delete();

        Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $date,
            'appointment_time' => '14:00',
            'status' => 'active',
        ]);

        Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $date,
            'appointment_time' => '10:30',
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->getAdminAuthHeaders())
            ->getJson("/api/appointments?date={$date}");

        $response->assertStatus(200)
            ->assertJsonPath('date', $date)
            ->assertJsonCount(2, 'appointments')
            ->assertJsonPath('appointments.0.appointment_time', '10:30')
            ->assertJsonPath('appointments.1.appointment_time', '14:00');
    }

    /**
     * حالت خطا / مرزی: GET /api/appointments برای تاریخی که هیچ نوبتی ندارد لیست خالی برمی‌گرداند
     */
    public function test_get_appointments_returns_empty_list_for_date_without_bookings(): void
    {
        $emptyDate = '2099-01-01';

        $response = $this->withHeaders($this->getAdminAuthHeaders())
            ->getJson("/api/appointments?date={$emptyDate}");

        $response->assertStatus(200)
            ->assertJsonPath('date', $emptyDate)
            ->assertJsonPath('appointments', []);
    }

    /**
     * حالت درست: POST /api/appointments ثبت نوبت معتبر در آینده
     */
    public function test_store_appointment_succeeds_for_available_future_slot(): void
    {
        [$customer, $service] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDay()->toDateString();
        Appointment::whereDate('appointment_date', $futureDate)->delete();

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'نوبت شما با موفقیت ثبت شد.')
            ->assertJsonPath('appointment.appointment_date', $futureDate)
            ->assertJsonPath('appointment.appointment_time', '11:00')
            ->assertJsonPath('appointment.status', 'active');
    }

    /**
     * حالت خطا ۱ (رزرو ساعت پر): POST /api/appointments رد کردن رزرو در ساعتی که قبلاً رزرو فعال دارد
     */
    public function test_store_appointment_fails_when_slot_is_already_booked(): void
    {
        [$customer, $service] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDay()->toDateString();

        Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '12:00',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری دوم',
            'customer_phone' => '09127654321',
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '12:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath(
                'message',
                'ثبت نوبت ناموفق بود: این ساعت در این روز قبلاً رزرو شده است. لطفاً ساعت یا روز دیگری را انتخاب کنید.'
            );
    }

    /**
     * حالت خطا ۲ (رزرو در گذشته): POST /api/appointments رد کردن رزرو در تاریخ گذشته و ساعت گذشته امروز
     */
    public function test_store_appointment_fails_when_booking_in_the_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 15:00:00', 'Asia/Tehran'));

        try {
            [$customer, $service] = $this->createTestCustomerAndService();

            // الف) تاریخ روز گذشته
            $pastDateResponse = $this->postJson('/api/appointments', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'service_id' => $service->id,
                'appointment_date' => '2026-11-22',
                'appointment_time' => '16:00',
            ]);

            $pastDateResponse->assertStatus(422)
                ->assertJsonPath(
                    'message',
                    'ثبت نوبت ناموفق بود: تاریخ انتخاب‌شده مربوط به گذشته است.'
                );

            // ب) همان روز (امروز) اما ساعت گذشته
            $pastTimeTodayResponse = $this->postJson('/api/appointments', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'service_id' => $service->id,
                'appointment_date' => '2026-11-23',
                'appointment_time' => '14:00',
            ]);

            $pastTimeTodayResponse->assertStatus(422)
                ->assertJsonPath(
                    'message',
                    'ثبت نوبت ناموفق بود: ساعت انتخاب‌شده در امروز گذشته است. لطفاً ساعت دیگری در ادامه امروز یا روز دیگری را انتخاب کنید.'
                );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * حالت درست: PATCH /api/appointments/{id}/cancel لغو نوبت فعال بدون حذف از دیتابیس
     */
    public function test_cancel_appointment_succeeds_and_keeps_record(): void
    {
        [$customer, $service] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDay()->toDateString();

        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '16:30',
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->getAdminAuthHeaders())
            ->patchJson("/api/appointments/{$appointment->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'رزرو با موفقیت لغو شد.')
            ->assertJsonPath('appointment.status', 'cancelled');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * حالت خطا: PATCH /api/appointments/{id}/cancel بازگشت 404 برای نوبت ناموجود
     */
    public function test_cancel_appointment_returns_404_when_not_found(): void
    {
        $response = $this->withHeaders($this->getAdminAuthHeaders())
            ->patchJson('/api/appointments/99999999/cancel');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'نوبت مورد نظر یافت نشد.');
    }

    /**
     * حالت خطا ۳ (نام خالی): POST /api/appointments رد کردن ورودی با نام خالی با کد 422 و پیام فارسی برای فیلد customer_name
     */
    public function test_store_appointment_fails_when_customer_name_is_empty(): void
    {
        [$customer, $service] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDay()->toDateString();

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => '   ',
            'customer_phone' => '09121234567',
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['customer_name']])
            ->assertJsonPath('errors.customer_name', 'لطفاً نام و نام خانوادگی را وارد کنید.');
    }

    /**
     * حالت خطا ۴ (موبایل نامعتبر طبق R10): POST /api/appointments رد کردن موبایل خالی، غیر ۱۱ رقمی یا شروع بدون 09 با کد 422 و پیام فارسی برای فیلد customer_phone
     */
    public function test_store_appointment_fails_when_customer_phone_is_invalid(): void
    {
        [$customer, $service] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDay()->toDateString();

        // الف) موبایل خالی
        $emptyPhoneResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => '',
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);

        $emptyPhoneResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['customer_phone']])
            ->assertJsonPath('errors.customer_phone', 'لطفاً شماره موبایل را وارد کنید.');

        // ب) موبایل که با 09 شروع نمی‌شود
        $wrongPrefixResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => '08121234567',
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);

        $wrongPrefixResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['customer_phone']])
            ->assertJsonPath(
                'errors.customer_phone',
                'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود (مثلاً 09123456789) وارد کنید.'
            );

        // ج) موبایل کمتر یا بیشتر از ۱۱ رقم
        $shortPhoneResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => '091212345',
            'service_id' => $service->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);

        $shortPhoneResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['customer_phone']]);
    }

    /**
     * حالت خطا ۵ (خدمت نامعتبر): POST /api/appointments رد کردن خدمت خالی یا ناموجود با کد 422 و پیام فارسی برای فیلد service_id
     */
    public function test_store_appointment_fails_when_service_is_invalid(): void
    {
        [$customer] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDay()->toDateString();

        // الف) خدمت خالی
        $emptyServiceResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => '',
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);

        $emptyServiceResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['service_id']])
            ->assertJsonPath('errors.service_id', 'لطفاً یکی از خدمات معتبر آرایشگاه را انتخاب کنید.');

        // ب) شناسه خدمت ناموجود در دیتابیس
        $nonExistentServiceResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => 99999999,
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);

        $nonExistentServiceResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['service_id']])
            ->assertJsonPath('errors.service_id', 'لطفاً یکی از خدمات معتبر آرایشگاه را انتخاب کنید.');
    }

    /**
     * حالت خطا ۶ (تاریخ نامعتبر): POST /api/appointments رد کردن تاریخ خالی یا فرمت/تقویم نامعتبر با کد 422 و پیام فارسی برای فیلد appointment_date
     */
    public function test_store_appointment_fails_when_date_is_invalid(): void
    {
        [$customer, $service] = $this->createTestCustomerAndService();

        // الف) تاریخ خالی
        $emptyDateResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => '',
            'appointment_time' => '11:00',
        ]);

        $emptyDateResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_date']])
            ->assertJsonPath('errors.appointment_date', 'لطفاً یک تاریخ معتبر برای نوبت انتخاب کنید.');

        // ب) تاریخ تقویمی نامعتبر (مثلاً ۳۰ فوریه)
        $impossibleDateResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => '2027-02-30',
            'appointment_time' => '11:00',
        ]);

        $impossibleDateResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_date']])
            ->assertJsonPath('errors.appointment_date', 'لطفاً یک تاریخ معتبر برای نوبت انتخاب کنید.');
    }

    /**
     * حالت خطا ۷ (چند فیلد هم‌زمان نامعتبر): POST /api/appointments بازگرداندن پیام فارسی مجزا برای تمام فیلدهای نامعتبر در یک پاسخ 422
     */
    public function test_store_appointment_returns_all_field_errors_when_multiple_inputs_are_invalid(): void
    {
        [$customer] = $this->createTestCustomerAndService();

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => '',
            'customer_phone' => '12345',
            'service_id' => 99999999,
            'appointment_date' => 'invalid-date',
            'appointment_time' => '23:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'customer_name',
                    'customer_phone',
                    'service_id',
                    'appointment_date',
                    'appointment_time',
                ],
            ]);
    }
}
