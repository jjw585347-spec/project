<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ایجاد جداول ساعت کاری هفتگی (working_hours) و تعطیلی‌ها/مرخصی‌ها (time_offs)
     */
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            // 0 = شنبه، 1 = یکشنبه، 2 = دوشنبه، 3 = سه‌شنبه، 4 = چهارشنبه، 5 = پنج‌شنبه، 6 = جمعه
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->string('start_time', 5)->default('10:00');
            $table->string('end_time', 5)->default('20:00');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('time_offs', function (Blueprint $table) {
            $table->id();
            $table->date('off_date');
            // full_day = تعطیلی کل روز (یک تاریخ خاص) | hourly = مرخصی چندساعته در یک روز
            $table->string('type', 20);
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['off_date', 'type']);
        });
    }

    /**
     * بازگردانی تغییرات
     */
    public function down(): void
    {
        Schema::dropIfExists('time_offs');
        Schema::dropIfExists('working_hours');
    }
};
