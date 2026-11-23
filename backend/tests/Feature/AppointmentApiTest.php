<?php

namespace Tests\Feature;

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

        $response = $this->getJson("/api/appointments?date={$date}");

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

        $response = $this->getJson("/api/appointments?date={$emptyDate}");

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

        $response = $this->patchJson("/api/appointments/{$appointment->id}/cancel");

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
        $response = $this->patchJson('/api/appointments/99999999/cancel');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'نوبت مورد نظر یافت نشد.');
    }
}
