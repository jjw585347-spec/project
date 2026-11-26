<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ایجاد جدول تنظیمات آرایشگاه (نام آرایشگاه و مهلت لغو نوبت توسط مشتری)
     */
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name', 255);
            $table->unsignedInteger('cancel_deadline_hours');
            $table->timestamps();
        });
    }

    /**
     * بازگردانی تغییرات
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
