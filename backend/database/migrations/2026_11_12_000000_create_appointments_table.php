<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ساخت جدول نوبت‌ها
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');                  // نام مشتری
            $table->string('customer_phone');                 // شماره موبایل مشتری
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete(); // خدمت انتخاب‌شده
            $table->date('appointment_date');                 // روز نوبت
            $table->string('appointment_time', 5);            // ساعت نوبت (مثلاً 10:00)
            $table->timestamps();
        });
    }

    /**
     * حذف جدول نوبت‌ها
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
