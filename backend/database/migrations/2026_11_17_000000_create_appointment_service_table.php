<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ساخت جدول رابط نوبت و خدمات (برای پشتیبانی از انتخاب یک یا چند خدمت در هر نوبت طبق R8)
     */
    public function up(): void
    {
        Schema::create('appointment_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['appointment_id', 'service_id']);
        });

        // انتقال رکوردهای قبلی جدول appointments به جدول رابط appointment_service
        $existingAppointments = DB::table('appointments')
            ->whereNotNull('service_id')
            ->get(['id', 'service_id', 'created_at', 'updated_at']);

        foreach ($existingAppointments as $appt) {
            DB::table('appointment_service')->insertOrIgnore([
                'appointment_id' => $appt->id,
                'service_id' => $appt->service_id,
                'created_at' => $appt->created_at ?? now(),
                'updated_at' => $appt->updated_at ?? now(),
            ]);
        }
    }

    /**
     * حذف جدول رابط نوبت و خدمات
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_service');
    }
};
