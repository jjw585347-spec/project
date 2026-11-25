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
        // در تست‌های عمومی نوبت، همه روزهای هفته را باز می‌گذاریم تا مستقل از روز اجرای تست (مثلاً اگر فردا جمعه باشد) کار کنند
        for ($d = 0; $d <= 6; $d++) {
            \App\Models\WorkingHour::updateOrCreate(
                ['day_of_week' => $d],
                [
                    'start_time' => '10:00',
                    'end_time' => '20:00',
                    'is_closed' => false,
                ]
            );
        }

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

        // ج) خدمت غیرفعال در دیتابیس قابل رزرو نیست
        $inactiveService = Service::create([
            'name' => 'خدمت غیرفعال تستی',
            'price' => 300000,
            'duration' => 30,
            'is_active' => false,
        ]);

        $inactiveServiceResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $inactiveService->id,
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);

        $inactiveServiceResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['service_id']])
            ->assertJsonPath(
                'errors.service_id',
                'خدمت انتخاب‌شده در حال حاضر غیرفعال است و امکان رزرو آن وجود ندارد.'
            );
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

    /**
     * سناریوی عددی ۱ (FRD): انتخاب چند خدمت و تداخل با نوبت قبلی (R5 و R8)
     * - نوبت قبلی در ساعت 11:00 برای کراتین مو (90 دقیقه: اشغال 11:00 تا 12:30)
     * - مشتری جدید دو خدمت (اصلاح مو 30 + پاکسازی پوست 45 = مجموع 75 دقیقه) انتخاب می‌کند:
     *   - ساعت 10:00 (تا 11:15) با نوبت 11:00 هم‌پوشانی دارد -> در available_slots نیست و بک‌اند هم با 422 رد می‌کند
     *   - ساعت‌های 10:30, 11:00, 11:30, 12:00 هم‌پوشانی دارند -> در available_slots نیستند و بک‌اند رد می‌کند
     *   - ساعت 12:30 (تا 13:45) جا می‌شود -> در available_slots هست و بک‌اند با 201 ثبت می‌کند
     *   - برای تک‌خدمت 30 دقیقه‌ای، ساعت 10:00 و 10:30 جا می‌شوند چون تا 11:00 تمام می‌شوند.
     */
    public function test_scenario_1_multi_service_overlap_with_existing_90_min_booking(): void
    {
        [$customer] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDays(2)->toDateString();
        Appointment::whereDate('appointment_date', $futureDate)->delete();
        \App\Models\TimeOff::whereDate('off_date', $futureDate)->delete();

        $haircut30 = Service::create(['name' => 'اصلاح مو سناریو ۱', 'price' => 250000, 'duration' => 30, 'is_active' => true]);
        $facial45 = Service::create(['name' => 'پاکسازی پوست سناریو ۱', 'price' => 350000, 'duration' => 45, 'is_active' => true]);
        $keratin90 = Service::create(['name' => 'کراتین مو سناریو ۱', 'price' => 900000, 'duration' => 90, 'is_active' => true]);

        // ۱. ثبت نوبت ۹۰ دقیقه‌ای در ساعت 11:00 (اشغال 11:00 تا 12:30)
        $firstBooking = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری اول کراتین',
            'customer_phone' => '09121112233',
            'service_ids' => [$keratin90->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '11:00',
        ]);
        $firstBooking->assertStatus(201)
            ->assertJsonPath('appointment.total_duration', 90);

        // ۲. بررسی زمان‌های ممکن (available_slots) برای دو خدمت با مجموع 75 دقیقه (30 + 45)
        $slots75Response = $this->getJson(
            "/api/appointments/booked-slots?date={$futureDate}&service_ids={$haircut30->id},{$facial45->id}"
        );
        $slots75Response->assertStatus(200)
            ->assertJsonPath('requested_duration', 75);

        $availableFor75 = $slots75Response->json('available_slots');
        $this->assertNotContains('10:00', $availableFor75); // 10:00 تا 11:15 با 11:00 تداخل دارد
        $this->assertNotContains('10:30', $availableFor75);
        $this->assertNotContains('11:00', $availableFor75);
        $this->assertNotContains('11:30', $availableFor75);
        $this->assertNotContains('12:00', $availableFor75);
        $this->assertContains('12:30', $availableFor75);    // 12:30 تا 13:45 کاملاً آزاد است

        // ۳. بررسی زمان‌های ممکن برای تک‌خدمت 30 دقیقه‌ای: ساعت 10:00 و 10:30 باید مجاز باشند
        $slots30Response = $this->getJson(
            "/api/appointments/booked-slots?date={$futureDate}&service_ids={$haircut30->id}"
        );
        $availableFor30 = $slots30Response->json('available_slots');
        $this->assertContains('10:00', $availableFor30);
        $this->assertContains('10:30', $availableFor30);
        $this->assertNotContains('11:00', $availableFor30);
        $this->assertNotContains('11:30', $availableFor30);
        $this->assertNotContains('12:00', $availableFor30);
        $this->assertContains('12:30', $availableFor30);

        // ۴. اگر کسی فرم را دور بزند و برای دو خدمت (75 دقیقه) در ساعت 10:00 یا 11:30 درخواست بفرستد، بک‌اند رد می‌کند
        $bypassAt1000 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری دورزننده فرم',
            'customer_phone' => '09122223344',
            'service_ids' => [$haircut30->id, $facial45->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '10:00',
        ]);
        $bypassAt1000->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_time']]);

        $bypassAt1130 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری دورزننده فرم',
            'customer_phone' => '09122223344',
            'service_ids' => [$haircut30->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '11:30',
        ]);
        $bypassAt1130->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_time']]);

        // ۵. ثبت در ساعت 12:30 برای همان دو خدمت (75 دقیقه) با موفقیت انجام می‌شود
        $validMultiService = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری دوخدمتی',
            'customer_phone' => '09122223344',
            'service_ids' => [$haircut30->id, $facial45->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '12:30',
        ]);
        $validMultiService->assertStatus(201)
            ->assertJsonPath('appointment.total_duration', 75)
            ->assertJsonPath('appointment.total_price', 600000)
            ->assertJsonCount(2, 'appointment.services');
    }

    /**
     * سناریوی عددی ۲ (FRD): جا شدن کامل داخل ساعت کاری و بیرون نزدن از پایان کار 20:00 (R6 و R8)
     * - برای خدمت 30 دقیقه‌ای: آخرین ساعت مجاز 19:30 است و 20:00 هرگز مجاز نیست.
     * - برای چند خدمت با مجموع 90 دقیقه (سه خدمت 30 دقیقه‌ای):
     *   - ساعت 18:30 (پایان 20:00) مجاز است
     *   - ساعت 19:00 (پایان 20:30) و 19:30 (پایان 21:00) در available_slots نیستند و بک‌اند با 422 رد می‌کند.
     */
    public function test_scenario_2_appointment_must_finish_within_working_hours(): void
    {
        [$customer] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDays(3)->toDateString();
        Appointment::whereDate('appointment_date', $futureDate)->delete();
        \App\Models\TimeOff::whereDate('off_date', $futureDate)->delete();

        $srv1 = Service::create(['name' => 'خدمت الف ۳۰', 'price' => 200000, 'duration' => 30, 'is_active' => true]);
        $srv2 = Service::create(['name' => 'خدمت ب ۳۰', 'price' => 150000, 'duration' => 30, 'is_active' => true]);
        $srv3 = Service::create(['name' => 'خدمت ج ۳۰', 'price' => 150000, 'duration' => 30, 'is_active' => true]);

        // ۱. بررسی available_slots برای مجموع 90 دقیقه در روز با پایان کار 20:00
        $slots90Response = $this->getJson(
            "/api/appointments/booked-slots?date={$futureDate}&service_ids={$srv1->id},{$srv2->id},{$srv3->id}"
        );
        $slots90Response->assertStatus(200)
            ->assertJsonPath('requested_duration', 90);

        $availableFor90 = $slots90Response->json('available_slots');
        $this->assertContains('18:30', $availableFor90);    // 18:30 + 90m = 20:00
        $this->assertNotContains('19:00', $availableFor90); // 19:00 + 90m = 20:30 > 20:00
        $this->assertNotContains('19:30', $availableFor90); // 19:30 + 90m = 21:00 > 20:00
        $this->assertNotContains('20:00', $availableFor90);

        // ۲. تلاش برای دور زدن فرم و ثبت نوبت 90 دقیقه‌ای در ساعت 19:00 باید با 422 رد شود
        $overflowAt1900 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری آخر شب',
            'customer_phone' => '09123334455',
            'service_ids' => [$srv1->id, $srv2->id, $srv3->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '19:00',
        ]);
        $overflowAt1900->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_time']]);

        // ۳. تلاش برای ثبت حتی تک‌خدمت 30 دقیقه‌ای در ساعت 20:00 باید با 422 رد شود
        $overflowAt2000 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری ساعت ۲۰',
            'customer_phone' => '09123334455',
            'service_ids' => [$srv1->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '20:00',
        ]);
        $overflowAt2000->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_time']]);

        // ۴. ثبت نوبت 90 دقیقه‌ای در ساعت 18:30 (پایان دقیقاً 20:00) موفق می‌شود
        $validAt1830 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری ۱۸:۳۰',
            'customer_phone' => '09123334455',
            'service_ids' => [$srv1->id, $srv2->id, $srv3->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '18:30',
        ]);
        $validAt1830->assertStatus(201)
            ->assertJsonPath('appointment.total_duration', 90);
    }

    /**
     * سناریوی عددی ۳ (FRD): برخورد با مرخصی ساعتی 14:00 تا 16:00 برای نوبت 60 دقیقه‌ای (R7 و R8)
     * - مرخصی از 14:00 تا 16:00 ثبت شده است
     * - مشتری دو خدمت با مجموع 60 دقیقه (30 + 30) انتخاب می‌کند:
     *   - ساعت 13:00 (تا 14:00) مجاز است
     *   - ساعت 13:30 (تا 14:30) چون 30 دقیقه وارد مرخصی می‌شود غیرمجاز است (نه نمایش داده می‌شود و نه در بک‌اند ثبت می‌شود)
     *   - ساعت‌های 14:00 تا 15:30 غیرمجازند
     *   - ساعت 16:00 (تا 17:00) مجاز است.
     */
    public function test_scenario_3_multi_service_cannot_overlap_with_hourly_leave(): void
    {
        [$customer] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDays(4)->toDateString();
        Appointment::whereDate('appointment_date', $futureDate)->delete();
        \App\Models\TimeOff::whereDate('off_date', $futureDate)->delete();

        \App\Models\TimeOff::create([
            'off_date' => $futureDate,
            'type' => 'hourly',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'reason' => 'مرخصی ناهار و استراحت',
        ]);

        $haircut30 = Service::create(['name' => 'اصلاح مو سناریو ۳', 'price' => 250000, 'duration' => 30, 'is_active' => true]);
        $beard30 = Service::create(['name' => 'اصلاح ریش سناریو ۳', 'price' => 150000, 'duration' => 30, 'is_active' => true]);

        // ۱. بررسی available_slots برای مجموع 60 دقیقه
        $slots60Response = $this->getJson(
            "/api/appointments/booked-slots?date={$futureDate}&service_ids={$haircut30->id},{$beard30->id}"
        );
        $slots60Response->assertStatus(200)
            ->assertJsonPath('requested_duration', 60);

        $availableFor60 = $slots60Response->json('available_slots');
        $this->assertContains('13:00', $availableFor60);    // 13:00 تا 14:00 مجاز
        $this->assertNotContains('13:30', $availableFor60); // 13:30 تا 14:30 وارد مرخصی می‌شود
        $this->assertNotContains('14:00', $availableFor60);
        $this->assertNotContains('14:30', $availableFor60);
        $this->assertNotContains('15:00', $availableFor60);
        $this->assertNotContains('15:30', $availableFor60);
        $this->assertContains('16:00', $availableFor60);    // 16:00 تا 17:00 مجاز

        // ۲. تلاش برای دور زدن فرم در ساعت 13:30 با نوبت 60 دقیقه‌ای باید با 422 رد شود
        $bypassAt1330 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری قبل مرخصی',
            'customer_phone' => '09124445566',
            'service_ids' => [$haircut30->id, $beard30->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '13:30',
        ]);
        $bypassAt1330->assertStatus(422)
            ->assertJsonPath(
                'errors.appointment_time',
                'ثبت نوبت ناموفق بود: در این بازه زمانی مرخصی ثبت شده است و امکان رزرو نوبت وجود ندارد.'
            );

        // ۳. ثبت در ساعت 13:00 برای همان دو خدمت (60 دقیقه، پایان دقیقاً 14:00) موفق می‌شود
        $validAt1300 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری ۱۳:۰۰',
            'customer_phone' => '09124445566',
            'service_ids' => [$haircut30->id, $beard30->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '13:00',
        ]);
        $validAt1300->assertStatus(201);
    }

    /**
     * سناریوی عددی ۴ و ۵ (FRD): خدمت ۴۵ دقیقه‌ای (غیرمضرب ۳۰) و آزاد شدن کل بازه پس از لغو نوبت (R4 و R5)
     * - نوبت 45 دقیقه‌ای در ساعت 10:00 ثبت می‌شود (اشغال 10:00 تا 10:45)
     * - ساعت 10:00 و 10:30 مسدود می‌شوند و اولین ساعت آزاد بعدی 11:00 است
     * - پس از لغو نوبت توسط مدیر، ساعت‌های 10:00 و 10:30 دوباره آزاد و قابل رزرو می‌شوند.
     */
    public function test_scenario_4_and_5_non_multiple_of_30_duration_and_cancelling_frees_all_overlapped_slots(): void
    {
        [$customer] = $this->createTestCustomerAndService();
        $futureDate = Carbon::now('Asia/Tehran')->addDays(5)->toDateString();
        Appointment::whereDate('appointment_date', $futureDate)->delete();
        \App\Models\TimeOff::whereDate('off_date', $futureDate)->delete();

        $facial45 = Service::create(['name' => 'پاکسازی ۴۵ دقیقه‌ای', 'price' => 350000, 'duration' => 45, 'is_active' => true]);
        $haircut30 = Service::create(['name' => 'اصلاح ۳۰ دقیقه‌ای', 'price' => 250000, 'duration' => 30, 'is_active' => true]);

        // ۱. ثبت نوبت 45 دقیقه‌ای در ساعت 10:00
        $createRes = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری ۴۵ دقیقه‌ای',
            'customer_phone' => '09125556677',
            'service_ids' => [$facial45->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '10:00',
        ]);
        $createRes->assertStatus(201);
        $appointmentId = $createRes->json('appointment.id');

        // ۲. ساعت 10:30 باید مسدود باشد و 11:00 آزاد باشد
        $slotsAfterBook = $this->getJson("/api/appointments/booked-slots?date={$futureDate}&duration=30");
        $availableWhileActive = $slotsAfterBook->json('available_slots');
        $this->assertNotContains('10:00', $availableWhileActive);
        $this->assertNotContains('10:30', $availableWhileActive);
        $this->assertContains('11:00', $availableWhileActive);

        // تلاش برای رزرو در ساعت 10:30 رد می‌شود
        $conflictAt1030 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری ۱۰:۳۰',
            'customer_phone' => '09126667788',
            'service_ids' => [$haircut30->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '10:30',
        ]);
        $conflictAt1030->assertStatus(422);

        // ۳. لغو نوبت 10:00 توسط صاحب آرایشگاه (سناریوی ۵)
        $cancelRes = $this->withHeaders($this->getAdminAuthHeaders())
            ->patchJson("/api/appointments/{$appointmentId}/cancel");
        $cancelRes->assertStatus(200);

        // ۴. اکنون ساعت‌های 10:00 و 10:30 دوباره در available_slots هستند و رزرو در 10:30 موفق می‌شود
        $slotsAfterCancel = $this->getJson("/api/appointments/booked-slots?date={$futureDate}&duration=30");
        $availableAfterCancel = $slotsAfterCancel->json('available_slots');
        $this->assertContains('10:00', $availableAfterCancel);
        $this->assertContains('10:30', $availableAfterCancel);

        $rebookAt1030 = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری ۱۰:۳۰ بعد از لغو',
            'customer_phone' => '09126667788',
            'service_ids' => [$haircut30->id],
            'appointment_date' => $futureDate,
            'appointment_time' => '10:30',
        ]);
        $rebookAt1030->assertStatus(201);
    }
}
