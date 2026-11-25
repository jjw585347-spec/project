<?php

namespace Tests\Feature;

use App\Http\Controllers\WorkingScheduleController;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkingScheduleApiTest extends TestCase
{
    use DatabaseTransactions;

    private function getAdminAuthHeaders(): array
    {
        $plainToken = 'test_admin_token_for_schedule_tests';
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
            'name' => 'مشتری تست ساعت کاری',
            'phone' => '09121234567',
            'password' => Hash::make('1234'),
        ]);

        $service = Service::create([
            'name' => 'اصلاح مو',
            'price' => 250000,
            'duration' => 30,
            'is_active' => true,
        ]);

        return [$customer, $service];
    }

    /**
     * یافتن اولین تاریخ آینده که منطبق با شماره روز هفته ایرانی (0 = شنبه ... 6 = جمعه) باشد
     */
    private function findNextDateForIranianDay(int $targetDayOfWeek): string
    {
        $cursor = Carbon::now('Asia/Tehran')->addDay()->startOfDay();
        for ($i = 0; $i < 14; $i++) {
            if (WorkingScheduleController::getIranianDayOfWeek($cursor) === $targetDayOfWeek) {
                return $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $cursor->toDateString();
    }

    /**
     * حالت پیش‌فرض: شنبه تا پنج‌شنبه ۱۰:۰۰ تا ۲۰:۰۰ باز و جمعه تعطیل است
     */
    public function test_default_weekly_schedule_is_sat_to_thu_10_to_20_and_friday_closed(): void
    {
        WorkingHour::query()->delete();

        $response = $this->withHeaders($this->getAdminAuthHeaders())
            ->getJson('/api/admin/schedule');

        $response->assertStatus(200)
            ->assertJsonCount(7, 'weekly_hours');

        $weekly = $response->json('weekly_hours');
        for ($day = 0; $day <= 5; $day++) {
            $this->assertSame($day, $weekly[$day]['day_of_week']);
            $this->assertSame('10:00', $weekly[$day]['start_time']);
            $this->assertSame('20:00', $weekly[$day]['end_time']);
            $this->assertFalse($weekly[$day]['is_closed']);
        }

        // جمعه (6) باید به صورت پیش‌فرض تعطیل باشد
        $this->assertSame(6, $weekly[6]['day_of_week']);
        $this->assertSame('جمعه', $weekly[6]['day_name']);
        $this->assertTrue($weekly[6]['is_closed']);

        // تلاش مشتری برای ثبت نوبت در روز جمعه باید توسط بک‌اند رد شود (R7)
        [$customer, $service] = $this->createTestCustomerAndService();
        $nextFriday = $this->findNextDateForIranianDay(6);

        $bookingOnFriday = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $nextFriday,
            'appointment_time' => '11:00',
        ]);

        $bookingOnFriday->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_date']]);
    }

    /**
     * صاحب کار می‌تواند ساعت شروع و پایان هر روز هفته را تغییر دهد یا آن روز را تعطیل کند
     * و بک‌اند ثبت نوبت خارج از ساعت کاری جدید یا در روز تعطیل‌شده را رد می‌کند (R7)
     */
    public function test_owner_can_update_weekly_hours_and_backend_rejects_out_of_hours_bookings(): void
    {
        WorkingHour::query()->delete();
        WorkingScheduleController::ensureDefaultWeeklyHours();

        [$customer, $service] = $this->createTestCustomerAndService();
        $nextSaturday = $this->findNextDateForIranianDay(0); // شنبه
        $nextSunday = $this->findNextDateForIranianDay(1);   // یکشنبه

        // تغییر شنبه به ۱۲:۰۰ تا ۱۸:۰۰ و تعطیل کردن یکشنبه
        $payload = [];
        for ($d = 0; $d <= 6; $d++) {
            $payload[] = [
                'day_of_week' => $d,
                'start_time' => $d === 0 ? '12:00' : '10:00',
                'end_time' => $d === 0 ? '18:00' : '20:00',
                'is_closed' => $d === 1 || $d === 6,
            ];
        }

        $updateResponse = $this->withHeaders($this->getAdminAuthHeaders())
            ->putJson('/api/admin/schedule/weekly', [
                'weekly_hours' => $payload,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('message', 'ساعت کاری هفتگی با موفقیت ذخیره شد.');

        // ۱. ثبت نوبت در شنبه ساعت ۱۰:۰۰ (خارج از ساعت ۱۲:۰۰ تا ۱۸:۰۰) باید رد شود
        $earlySaturdayResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $nextSaturday,
            'appointment_time' => '10:00',
        ]);
        $earlySaturdayResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_time']]);

        // ۲. ثبت نوبت در شنبه ساعت ۱۳:۰۰ (داخل بازه ۱۲:۰۰ تا ۱۸:۰۰) باید موفق شود
        $validSaturdayResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $nextSaturday,
            'appointment_time' => '13:00',
        ]);
        $validSaturdayResponse->assertStatus(201);

        // ۳. ثبت نوبت در یکشنبه (که تعطیل هفتگی شده) باید رد شود
        $closedSundayResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'service_id' => $service->id,
            'appointment_date' => $nextSunday,
            'appointment_time' => '13:00',
        ]);
        $closedSundayResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_date']]);
    }

    /**
     * صاحب کار می‌تواند یک تاریخ خاص را تعطیل کند (مثلاً سه‌شنبه هفته بعد)
     * در آن تاریخ هیچ نوبتی گرفته نمی‌شود و اگر از قبل نوبت داشت، هشدار می‌دهد و نوبت را خودکار لغو نمی‌کند
     */
    public function test_owner_can_close_specific_date_with_warning_for_existing_appointments_without_auto_cancel(): void
    {
        WorkingHour::query()->delete();
        TimeOff::query()->delete();
        WorkingScheduleController::ensureDefaultWeeklyHours();

        [$customer, $service] = $this->createTestCustomerAndService();
        $nextTuesday = $this->findNextDateForIranianDay(3); // سه‌شنبه

        // ثبت یک نوبت فعال از قبل در آن سه‌شنبه
        $existingAppointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری قبلی سه‌شنبه',
            'customer_phone' => '09121112233',
            'service_id' => $service->id,
            'appointment_date' => $nextTuesday,
            'appointment_time' => '11:00',
            'status' => 'active',
        ]);

        // صاحب کار آن سه‌شنبه را تعطیل می‌کند
        $holidayResponse = $this->withHeaders($this->getAdminAuthHeaders())
            ->postJson('/api/admin/schedule/holidays', [
                'off_date' => $nextTuesday,
                'reason' => 'تعطیلی سه‌شنبه هفته بعد',
            ]);

        $holidayResponse->assertStatus(201)
            ->assertJsonPath('message', 'تاریخ تعطیل با موفقیت ثبت شد.')
            ->assertJsonCount(1, 'conflicting_appointments')
            ->assertJsonPath('conflicting_appointments.0.id', $existingAppointment->id);

        $this->assertNotNull($holidayResponse->json('warning'));

        // نوبت قبلی به هیچ وجه نباید خودکار لغو شده باشد
        $this->assertDatabaseHas('appointments', [
            'id' => $existingAppointment->id,
            'status' => 'active',
        ]);

        // بررسی booked-slots برای آن تاریخ: باید is_closed = true برگرداند
        $slotsResponse = $this->getJson("/api/appointments/booked-slots?date={$nextTuesday}");
        $slotsResponse->assertStatus(200)
            ->assertJsonPath('is_closed', true);

        // تلاش برای ثبت نوبت جدید در آن تاریخ تعطیل باید توسط بک‌اند رد شود (R7)
        $newBookingResponse = $this->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'customer_name' => 'مشتری جدید',
            'customer_phone' => '09129998877',
            'service_id' => $service->id,
            'appointment_date' => $nextTuesday,
            'appointment_time' => '15:00',
        ]);

        $newBookingResponse->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['appointment_date']]);
    }

    /**
     * صاحب کار می‌تواند در یک روز مرخصی چندساعته بگذارد (مثلاً امروز از ۱۴:۰۰ تا ۱۶:۰۰)
     * در آن ساعات هیچ نوبتی گرفته نمی‌شود و اگر از قبل نوبت داشت، هشدار می‌دهد و نوبت را خودکار لغو نمی‌کند
     */
    public function test_owner_can_set_hourly_leave_with_warning_for_existing_appointments_and_blocks_new_bookings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-21 09:00:00', 'Asia/Tehran')); // شنبه ۹ صبح

        try {
            WorkingHour::query()->delete();
            TimeOff::query()->delete();
            WorkingScheduleController::ensureDefaultWeeklyHours();

            [$customer, $service] = $this->createTestCustomerAndService();
            $todayDate = '2026-11-21';
            Appointment::whereDate('appointment_date', $todayDate)->delete();

            // ثبت یک نوبت فعال از قبل در ساعت ۱۴:۳۰ امروز
            $existingAppointment = Appointment::create([
                'customer_id' => $customer->id,
                'customer_name' => 'مشتری ساعت ۱۴:۳۰',
                'customer_phone' => '09123334455',
                'service_id' => $service->id,
                'appointment_date' => $todayDate,
                'appointment_time' => '14:30',
                'status' => 'active',
            ]);

            // صاحب کار برای امروز از ۱۴:۰۰ تا ۱۶:۰۰ مرخصی ثبت می‌کند (با ارقام فارسی)
            $leaveResponse = $this->withHeaders($this->getAdminAuthHeaders())
                ->postJson('/api/admin/schedule/leaves', [
                    'off_date' => $todayDate,
                    'start_time' => '۱۴:۰۰',
                    'end_time' => '۱۶:۰۰',
                    'reason' => 'مرخصی ساعتی امروز',
                ]);

            $leaveResponse->assertStatus(201)
                ->assertJsonPath('message', 'مرخصی ساعتی با موفقیت ثبت شد.')
                ->assertJsonCount(1, 'conflicting_appointments')
                ->assertJsonPath('conflicting_appointments.0.id', $existingAppointment->id);

            $this->assertNotNull($leaveResponse->json('warning'));

            // نوبت قبلی نباید خودکار لغو شده باشد
            $this->assertDatabaseHas('appointments', [
                'id' => $existingAppointment->id,
                'status' => 'active',
            ]);

            // بررسی booked-slots: ساعت‌های 14:00, 14:30, 15:00, 15:30 باید در leave_slots باشند
            $slotsResponse = $this->getJson("/api/appointments/booked-slots?date={$todayDate}");
            $slotsResponse->assertStatus(200);
            $leaveSlots = $slotsResponse->json('leave_slots');
            $this->assertContains('14:00', $leaveSlots);
            $this->assertContains('14:30', $leaveSlots);
            $this->assertContains('15:00', $leaveSlots);
            $this->assertContains('15:30', $leaveSlots);
            $this->assertNotContains('16:00', $leaveSlots);

            // تلاش برای ثبت نوبت جدید در ساعت ۱۵:۰۰ (داخل مرخصی ۱۴ تا ۱۶) باید توسط بک‌اند رد شود (R7)
            $blockedBooking = $this->postJson('/api/appointments', [
                'customer_id' => $customer->id,
                'customer_name' => 'مشتری جدید',
                'customer_phone' => '09127778899',
                'service_id' => $service->id,
                'appointment_date' => $todayDate,
                'appointment_time' => '15:00',
            ]);

            $blockedBooking->assertStatus(422)
                ->assertJsonPath(
                    'errors.appointment_time',
                    'ثبت نوبت ناموفق بود: در این بازه زمانی مرخصی ثبت شده است و امکان رزرو نوبت وجود ندارد.'
                );

            // اما ثبت نوبت در ساعت ۱۶:۰۰ (بلافاصله بعد از پایان مرخصی) مجاز است
            $allowedBooking = $this->postJson('/api/appointments', [
                'customer_id' => $customer->id,
                'customer_name' => 'مشتری ساعت ۱۶',
                'customer_phone' => '09127778899',
                'service_id' => $service->id,
                'appointment_date' => $todayDate,
                'appointment_time' => '16:00',
            ]);

            $allowedBooking->assertStatus(201);
        } finally {
            Carbon::setTestNow();
        }
    }
}
